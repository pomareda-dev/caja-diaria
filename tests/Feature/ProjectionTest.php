<?php

use App\Models\Category;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Authentication ───────────────────────────────────────────────

test('unauthenticated user cannot access projection', function () {
    $response = $this->get(route('proyeccion.index'));

    $response->assertRedirect(route('login'));
});

// ─── Projection View ──────────────────────────────────────────────

test('projection view shows future movements with running balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Past real movements → establish opening balance = 5000
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 5000,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    // Future manual movement
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => -200,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    // Another future movement
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-08-01',
        'amount' => 1000,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    $response = $this->get(route('proyeccion.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Proyeccion/Index')
        ->has('items', 2)
        ->has('openingBalance')
        ->has('horizonMonths')
        // openingBalance should be 5000 (only real movements)
        ->where('openingBalance', 5000)
        // First item: running_balance = 5000 - 200 = 4800
        ->where('items.0.amount', -200)
        ->where('items.0.running_balance', 4800)
        // Second item: running_balance = 4800 + 1000 = 5800
        ->where('items.1.amount', 1000)
        ->where('items.1.running_balance', 5800)
    );

    Carbon::setTestNow();
});

test('projection view includes recurring-generated movements', function () {
    $user = User::factory()->create();
    $service = app(ProjectionService::class);
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Past real movements → opening = 5000
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 5000,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    // Create recurring template and generate
    RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'name' => 'Falabella',
        'amount' => -300.96,
        'day_of_month' => 5,
        'start_month' => '2026-08-01',
        'end_month' => '2026-10-01',
        'active' => true,
    ]);

    $service->generateForUser($user->id);

    // Also add a future manual movement
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => -200,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    $response = $this->get(route('proyeccion.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Proyeccion/Index')
        ->has('items', 4) // 1 manual future + 3 recurring
        ->where('openingBalance', 5000)
        ->where('items.0.running_balance', fn ($v) => is_numeric($v))
        ->where('items.1.running_balance', fn ($v) => is_numeric($v))
        ->where('items.2.running_balance', fn ($v) => is_numeric($v))
        ->where('items.3.running_balance', fn ($v) => is_numeric($v))
    );

    Carbon::setTestNow();
});

test('projection view is ordered by date then sort_order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-25',
        'amount' => -50,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => true,
        'sort_order' => 2,
    ]);

    $response = $this->get(route('proyeccion.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('items', 3)
        ->where('items.0.date', '2026-07-20')
        ->where('items.1.date', '2026-07-20')
        ->where('items.2.date', '2026-07-25')
    );

    Carbon::setTestNow();
});

test('projection shows empty state when no future movements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Only past movements
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 5000,
        'source' => 'manual',
    ]);

    $response = $this->get(route('proyeccion.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('items', [])
        ->where('openingBalance', 5000)
    );

    Carbon::setTestNow();
});

test('projection includes category names', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Comida',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => -100,
        'category_id' => $category->id,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    $response = $this->get(route('proyeccion.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('items.0.category_name', 'Comida')
    );

    Carbon::setTestNow();
});
