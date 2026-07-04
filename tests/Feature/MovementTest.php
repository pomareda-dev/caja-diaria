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
        ->has('realMovements')
        ->has('projectedMovements')
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
        ->where('realMovements.0.amount', 500)
        ->where('realMovements.0.running_balance', 1500)
        ->where('realMovements.1.amount', -200)
        ->where('realMovements.1.running_balance', 1300)
        // July 20 is > today (July 15) → projected
        ->has('projectedMovements', 1)
    );

    Carbon::setTestNow();
});

test('running balance starts from opening balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

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
        ->where('realMovements.0.running_balance', 600)
    );

    Carbon::setTestNow();
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
        ->has('realMovements', 1)
        ->where('realMovements.0.description', 'Junio')
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
        ->where('realMovements.0.is_projected', false)
        ->where('projectedMovements.0.is_projected', true)
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

// ─── Projected Flag & Sort Order ──────────────────────────────────

test('balance_excludes_projected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Projected movement before month-start — excluded from openingBalance
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-30',
        'amount' => 500,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    // Real movement before month-start — included in openingBalance
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-28',
        'amount' => 1000,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    // openingBalance should be 1000 (projected 500 excluded)
    expect((float) Movement::openingBalance(Carbon::parse('2026-07-01'), $user->id))->toBe(1000.0);

    // Projected movement with date<=today — excluded from realBalance
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    // realBalance should be 1000 (projected -200 excluded)
    expect((float) Movement::realBalance($user->id))->toBe(1000.0);

    Carbon::setTestNow();
});

test('index_splits_real_projected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // 3 real movements (is_projected=false, date<=today)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => false,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -50,
        'source' => 'manual',
        'is_projected' => false,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    // 2 projected movements (is_projected=true, date<=today)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-12',
        'amount' => 300,
        'source' => 'manual',
        'is_projected' => true,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-14',
        'amount' => -100,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    $response = $this->get(route('movimientos.index', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->component('Movimientos/Index')
        ->has('realMovements', 3)
        ->has('projectedMovements', 2)
        ->has('categories')
        ->has('selectedMonth')
        ->has('openingBalance')
        ->has('currentMonth')
        // realMovements have running_balance
        ->where('realMovements.0.running_balance', fn ($v) => is_numeric($v))
        // projectedMovements DON'T have running_balance
        ->missing('projectedMovements.0.running_balance')
    );

    Carbon::setTestNow();
});

// ─── Reorder ─────────────────────────────────────────────────────

test('reorder_happy_path', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3 real movements same date: A=s1, B=s2, C=s3
    $a = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => false,
        'sort_order' => 1,
    ]);
    $b = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => false,
        'sort_order' => 2,
    ]);
    $c = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 300,
        'source' => 'manual',
        'is_projected' => false,
        'sort_order' => 3,
    ]);

    // Reorder: C, A, B
    $response = $this->patch(route('movimientos.reorder'), [
        'ids' => [$c->id, $a->id, $b->id],
    ]);

    $response->assertRedirect();

    $c->refresh();
    $a->refresh();
    $b->refresh();

    expect($c->sort_order)->toBe(1);
    expect($a->sort_order)->toBe(2);
    expect($b->sort_order)->toBe(3);
});

test('reorder_rejects_mixed_dates', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $movementA = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => false,
    ]);
    $movementB = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    $response = $this->patch(route('movimientos.reorder'), [
        'ids' => [$movementA->id, $movementB->id],
    ]);

    $response->assertStatus(422);
});

test('reorder_rejects_cross_user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $ownMovement = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => false,
    ]);
    $otherMovement = Movement::factory()->create([
        'user_id' => $other->id,
        'date' => '2026-07-15',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    $response = $this->patch(route('movimientos.reorder'), [
        'ids' => [$ownMovement->id, $otherMovement->id],
    ]);

    // Cross-user ids should be rejected
    $response->assertStatus(403);
});

test('reorder_single_row_idempotent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => false,
        'sort_order' => 5,
    ]);

    $response = $this->patch(route('movimientos.reorder'), [
        'ids' => [$movement->id],
    ]);

    $response->assertRedirect();

    $movement->refresh();
    expect($movement->sort_order)->toBe(5); // unchanged
});

test('flip_preserves_date_reassigns_sort', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // 5 real movements on 2026-07-20 with sort_order 1..5
    for ($i = 1; $i <= 5; $i++) {
        Movement::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-07-20',
            'amount' => 100,
            'source' => 'manual',
            'is_projected' => false,
            'sort_order' => $i,
        ]);
    }

    // 1 projected movement on 2026-07-20, sort_order=2
    $projected = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => true,
        'sort_order' => 2,
    ]);

    // Flip projected→real (is_projected=0, date unchanged)
    $this->put(route('movimientos.update', $projected), [
        'date' => '2026-07-20',
        'description' => $projected->description,
        'amount' => (float) $projected->amount,
        'is_projected' => 0,
    ]);

    $projected->refresh();
    expect($projected->is_projected)->toBeFalse();
    expect($projected->date->format('Y-m-d'))->toBe('2026-07-20');
    expect($projected->sort_order)->toBe(6); // MAX+1 among reales on that date
});

test('store_auto_sort_order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // First real movement on 2026-07-15 → sort_order 1
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Primero real',
        'amount' => 100,
    ]);

    $this->assertDatabaseHas('movements', [
        'description' => 'Primero real',
        'sort_order' => 1,
    ]);

    // Second real movement on same date → sort_order 2
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Segundo real',
        'amount' => 200,
    ]);

    $this->assertDatabaseHas('movements', [
        'description' => 'Segundo real',
        'sort_order' => 2,
    ]);

    // Projected movement on same date → sort_order 1 (independent group)
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Primero proyectado',
        'amount' => 300,
        'is_projected' => 1,
    ]);

    $this->assertDatabaseHas('movements', [
        'description' => 'Primero proyectado',
        'sort_order' => 1,
    ]);
});

test('movement_request_is_projected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // POST without is_projected — defaults to 0
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Sin proyectar',
        'amount' => 100,
    ]);

    $this->assertDatabaseHas('movements', [
        'user_id' => $user->id,
        'description' => 'Sin proyectar',
        'is_projected' => 0,
    ]);

    // POST with is_projected=1 — stored as 1
    $this->post(route('movimientos.store'), [
        'date' => '2026-07-15',
        'description' => 'Proyectado',
        'amount' => 200,
        'is_projected' => 1,
    ]);

    $this->assertDatabaseHas('movements', [
        'user_id' => $user->id,
        'description' => 'Proyectado',
        'is_projected' => 1,
    ]);
});

test('is_projected_defaults_to_false', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'amount' => 100,
        'source' => 'manual',
    ]);

    // DB defaults — is_projected=0, sort_order=0
    $this->assertDatabaseHas('movements', [
        'id' => $movement->id,
        'is_projected' => 0,
        'sort_order' => 0,
    ]);

    // Boolean cast — refresh for casts to apply
    $movement->refresh();
    expect($movement->is_projected)->toBeFalse();
    expect($movement->sort_order)->toBe(0);
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
