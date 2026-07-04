<?php

use App\Models\Category;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Authentication ───────────────────────────────────────────────

test('unauthenticated user cannot access movements index', function () {
    $response = $this->get(route('movimientos.index'));

    $response->assertRedirect(route('login'));
});

test('unauthenticated user cannot create a movement', function () {
    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Test',
        'amount' => 100,
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated user can view movements index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('movimientos.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Movimientos/Index')
        ->has('movements')
        ->has('categories')
        ->has('selectedMonth')
        ->has('openingBalance')
        ->has('currentMonth')
    );
});

// ─── Create ───────────────────────────────────────────────────────

test('authenticated user can create an income movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Sueldo del mes',
        'amount' => 2500,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('movements', [
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'description' => 'Sueldo del mes',
        'amount' => '2500.00',
        'source' => 'manual',
    ]);
});

test('authenticated user can create an expense movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Compra en mercado',
        'amount' => -150.50,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('movements', [
        'user_id' => $user->id,
        'description' => 'Compra en mercado',
        'amount' => '-150.50',
        'source' => 'manual',
    ]);
});

test('amount is stored with correct sign for income and expense', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Income — positive amount
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-01',
        'description' => 'Ingreso',
        'amount' => 1000,
    ]);

    // Expense — negative amount
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-02',
        'description' => 'Gasto',
        'amount' => -200,
    ]);

    expect(Movement::where('description', 'Ingreso')->first()->amount)->toBe('1000.00');
    expect(Movement::where('description', 'Gasto')->first()->amount)->toBe('-200.00');
});

test('movement can be created with category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Compra',
        'amount' => -50,
        'category_id' => $category->id,
    ]);

    $this->assertDatabaseHas('movements', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => '-50.00',
    ]);
});

// ─── Update ───────────────────────────────────────────────────────

test('authenticated user can update their own movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $movement = Movement::factory()->create(['user_id' => $user->id]);

    $response = $this->put(route('movimientos.update', $movement), [
        'date' => '2026-08-01',
        'description' => 'Descripción actualizada',
        'amount' => 500,
    ]);

    $response->assertRedirect();
    $movement->refresh();

    expect($movement->description)->toBe('Descripción actualizada');
    expect($movement->amount)->toBe('500.00');
    expect($movement->date->format('Y-m-d'))->toBe('2026-08-01');
});

test('user cannot update another users movement', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $movement = Movement::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->put(route('movimientos.update', $movement), [
        'date' => '2026-08-01',
        'description' => 'Hacked',
        'amount' => 999999,
    ]);

    $response->assertForbidden();
});

// ─── Delete ───────────────────────────────────────────────────────

test('authenticated user can delete their own movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $movement = Movement::factory()->create(['user_id' => $user->id]);

    $response = $this->delete(route('movimientos.destroy', $movement));

    $response->assertRedirect();
    $this->assertModelMissing($movement);
});

test('user cannot delete another users movement', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $movement = Movement::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->delete(route('movimientos.destroy', $movement));

    $response->assertForbidden();
    $this->assertModelExists($movement);
});

// ─── Validation ───────────────────────────────────────────────────

test('validation: date is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'description' => 'Test',
        'amount' => 100,
    ]);

    $response->assertSessionHasErrors('date');
});

test('validation: date must be a valid date format', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => 'not-a-date',
        'description' => 'Test',
        'amount' => 100,
    ]);

    $response->assertSessionHasErrors('date');
});

test('validation: description is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'amount' => 100,
    ]);

    $response->assertSessionHasErrors('description');
});

test('validation: amount must be numeric', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Test',
        'amount' => 'not-a-number',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('validation: amount must not be zero', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Test',
        'amount' => 0,
    ]);

    $response->assertSessionHasErrors('amount');
});

test('validation: amount is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Test',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('validation: category must belong to user when provided', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCategory = Category::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Test',
        'amount' => 100,
        'category_id' => $otherCategory->id,
    ]);

    $response->assertSessionHasErrors('category_id');
});

// ─── Running Balance ──────────────────────────────────────────────

test('running balance calculation is correct', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Opening: 1000 from previous month
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Movements in current month
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-05',
        'amount' => 500,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 300,
        'source' => 'manual',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 1000)
        ->where('movements.0.amount', 500)
        ->where('movements.0.running_balance', 1500)
        ->where('movements.1.amount', -200)
        ->where('movements.1.running_balance', 1300)
    );

    Carbon::setTestNow();
});

test('running balance starts from opening balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // 500 opening balance from previous month
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 500,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => 100,
        'source' => 'manual',
    ]);

    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 500)
        ->where('movements.0.running_balance', 600)
    );
});

// ─── Month Filtering ──────────────────────────────────────────────

test('movements index respects month filter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'description' => 'Junio',
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'description' => 'Julio',
        'source' => 'manual',
    ]);

    $response = $this->get(route('movimientos.index', ['month' => '2026-06']));

    $response->assertInertia(fn ($page) => $page
        ->where('selectedMonth', '2026-06')
        ->has('movements', 1)
        ->where('movements.0.description', 'Junio')
    );
});

test('defaults to current month when no month filter provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $now = now()->format('Y-m');

    $response = $this->get(route('movimientos.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('selectedMonth', $now)
    );
});

// ─── Projected Movements ──────────────────────────────────────────

test('projected movements are marked correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => 100,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 200,
        'source' => 'manual',
    ]);

    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('movements.0.is_projected', false)
        ->where('movements.1.is_projected', true)
    );

    Carbon::setTestNow();
});

test('opening balance excludes projected and recurring movements from previous months', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Manual before month — should be counted
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Recurring before month — should be excluded by openingBalance method
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 500,
        'source' => 'recurring',
    ]);

    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    // openingBalance only sums manual + import, so recurring 500 is excluded
    $response->assertInertia(fn ($page) => $page
        ->where('openingBalance', 1000)
    );

    Carbon::setTestNow();
});

// ─── Categories in response ───────────────────────────────────────

test('movements index includes categories for dropdown', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Comida',
        'sort_order' => 1,
    ]);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Transporte',
        'sort_order' => 2,
    ]);

    $response = $this->get(route('movimientos.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 2)
        ->where('categories.0.name', 'Comida')
        ->where('categories.1.name', 'Transporte')
    );
});
