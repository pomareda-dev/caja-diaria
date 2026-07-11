<?php

use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Opening Balance from Prior Month ─────────────────────────────

test('movement_before_visible_month_contributes_to_opening_balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Movement on the last day of the prior month
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-30',
        'amount' => 500,
        'source' => 'manual',
    ]);

    // Movement in the visible month (July)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-05',
        'amount' => -100,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // GET July movements
    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 500)
        ->has('realMovements', 1)
        ->where('realMovements.0.running_balance', 400)
    );

    // GET June movements
    $response = $this->get(route('movimientos.index', ['month' => '2026-06']));

    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 0)
        ->has('realMovements', 1)
        ->where('realMovements.0.running_balance', 500)
    );

    Carbon::setTestNow();
});

// ─── Running Balance Recalculation After Deletion ──────────────────

test('delete_middle_movement_recalculates_running_balances', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-25'));

    // Create 3 movements in the same month with distinct dates
    $m1 = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    $m2 = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
    ]);

    $m3 = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => -300,
        'source' => 'manual',
    ]);

    // GET July — assert running balances are [1000, 800, 500]
    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 0)
        ->has('realMovements', 3)
        ->where('realMovements.0.running_balance', 1000)
        ->where('realMovements.1.running_balance', 800)
        ->where('realMovements.2.running_balance', 500)
    );

    // DELETE the middle movement (M2)
    $deleteResponse = $this->delete(route('movimientos.destroy', $m2));
    $deleteResponse->assertRedirect();
    $this->assertModelMissing($m2);

    // GET July again — assert 2 rows with recalculated balances
    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 0)
        ->has('realMovements', 2)
        ->where('realMovements.0.running_balance', 1000)
        ->where('realMovements.1.running_balance', 700)
    );

    Carbon::setTestNow();
});
