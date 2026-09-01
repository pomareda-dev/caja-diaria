<?php

use App\Models\Debt;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Index ────────────────────────────────────────────────────────

test('index renders the debts page with the debt payload', function () {
    $user = User::factory()->create();

    Debt::factory()->create([
        'user_id' => $user->id,
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'installment_amount' => 300,
        'installments_count' => 4,
        'payment_dates' => [
            now()->addMonth()->toDateString(),
            now()->addMonths(2)->toDateString(),
            now()->addMonths(3)->toDateString(),
            now()->addMonths(4)->toDateString(),
        ],
    ]);

    $response = $this->actingAs($user)->get(route('deudas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Deudas/Index')
        ->has('debts', 1)
        ->where('debts.0.name', 'Préstamo personal')
        ->where('debts.0.principal_amount', fn ($value) => (float) $value === 1000.0)
        ->where('debts.0.installment_amount', fn ($value) => (float) $value === 300.0)
        ->where('debts.0.installments_count', 4)
        ->where('debts.0.rate_factor', 1.2)
        ->where('debts.0.total_to_pay', fn ($value) => (float) $value === 1200.0)
        ->where('debts.0.paid_installments', 0)
        ->where('debts.0.remaining', fn ($value) => (float) $value === 1200.0)
        ->where('debts.0.can_delete', true)
        ->where('debts.0.is_active', true));
});

test('index flags can_delete false and counts paid installments when real payments exist', function () {
    $user = User::factory()->create();

    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
    ]);

    // Real disbursement (not an installment) + one real payment
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => 1000,
        'is_projected' => false,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => false,
    ]);

    $response = $this->actingAs($user)->get(route('deudas.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Deudas/Index')
        ->where('debts.0.paid_installments', 1)
        ->where('debts.0.remaining', fn ($value) => (float) $value === 750.0)
        ->where('debts.0.can_delete', false));
});

// ─── Validation ───────────────────────────────────────────────────

test('store validates payment_dates count matches installments_count', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('deudas.store'), [
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'disbursement_date' => '2026-08-01',
        'installment_amount' => 250,
        'installments_count' => 4,
        'payment_dates' => ['2026-09-01', '2026-10-01'], // Only 2 dates, should be 4
    ]);

    $response->assertSessionHasErrors('payment_dates');
    expect(Debt::count())->toBe(0);
});

test('store validates principal_amount is greater than zero', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('deudas.store'), [
        'name' => 'Préstamo personal',
        'principal_amount' => 0,
        'disbursement_date' => '2026-08-01',
        'installment_amount' => 250,
        'installments_count' => 4,
        'payment_dates' => ['2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01'],
    ]);

    $response->assertSessionHasErrors('principal_amount');
});

test('store validates installment_amount is greater than zero', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('deudas.store'), [
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'disbursement_date' => '2026-08-01',
        'installment_amount' => 0,
        'installments_count' => 4,
        'payment_dates' => ['2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01'],
    ]);

    $response->assertSessionHasErrors('installment_amount');
});

// ─── Store ────────────────────────────────────────────────────────

test('store creates debt with disbursement and installment movements', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('deudas.store'), [
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'disbursement_date' => now()->subMonth()->toDateString(),
        'installment_amount' => 250,
        'installments_count' => 4,
        'payment_dates' => [
            now()->subMonth()->toDateString(),
            now()->toDateString(),
            now()->addMonth()->toDateString(),
            now()->addMonths(2)->toDateString(),
        ],
    ]);

    $response->assertRedirect();

    expect(Debt::count())->toBe(1);
    $debt = Debt::first();
    expect($debt->name)->toBe('Préstamo personal');
    expect($debt->principal_amount)->toBe('1000.00');

    // 1 disbursement + 4 installments = 5 movements
    expect($debt->movements)->toHaveCount(5);

    // Disbursement movement
    $disbursement = $debt->movements->firstWhere('amount', '1000.00');
    expect($disbursement)->not->toBeNull();
    expect($disbursement->description)->toContain('Desembolso');

    // Installment movements
    $installments = $debt->movements->where('amount', '-250.00');
    expect($installments)->toHaveCount(4);
});

test('store marks past movements as non-projected and future as projected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('deudas.store'), [
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'disbursement_date' => now()->subMonth()->toDateString(),
        'installment_amount' => 250,
        'installments_count' => 3,
        'payment_dates' => [
            now()->subMonth()->toDateString(),
            now()->toDateString(),
            now()->addMonth()->toDateString(),
        ],
    ]);

    $debt = Debt::first();

    // Disbursement (past) + installment 1 (past) + installment 2 (today, not future) = 3 non-projected
    $nonProjected = $debt->movements()->where('is_projected', false)->count();
    expect($nonProjected)->toBe(3);

    // Installment 3 (future) = 1 projected
    $projected = $debt->movements()->where('is_projected', true)->count();
    expect($projected)->toBe(1);
});

// ─── Update ───────────────────────────────────────────────────────

test('update regenerates projected movements and preserves real ones', function () {
    $user = User::factory()->create();

    // Create a debt with 4 installments (2 past, 2 future)
    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'disbursement_date' => now()->subMonths(2)->toDateString(),
        'installment_amount' => 250,
        'installments_count' => 4,
        'payment_dates' => [
            now()->subMonth()->toDateString(),
            now()->toDateString(),
            now()->addMonth()->toDateString(),
            now()->addMonths(2)->toDateString(),
        ],
    ]);

    // Create 2 real movements (past installments)
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'date' => now()->subMonth()->toDateString(),
        'amount' => -250,
        'is_projected' => false,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'date' => now()->toDateString(),
        'amount' => -250,
        'is_projected' => false,
    ]);

    // Create 2 projected movements (future installments)
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'date' => now()->addMonth()->toDateString(),
        'amount' => -250,
        'is_projected' => true,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'date' => now()->addMonths(2)->toDateString(),
        'amount' => -250,
        'is_projected' => true,
    ]);

    // Update: change installment_amount to 300
    $response = $this->actingAs($user)->put(route('deudas.update', $debt), [
        'installment_amount' => 300,
    ]);

    $response->assertRedirect();

    // Real movements preserved
    $realMovements = $debt->movements()->where('is_projected', false)->get();
    expect($realMovements)->toHaveCount(2);
    expect($realMovements->first()->amount)->toBe('-250.00');

    // Projected movements regenerated with new amount
    $projectedMovements = $debt->movements()->where('is_projected', true)->get();
    expect($projectedMovements)->toHaveCount(2);
    expect($projectedMovements->first()->amount)->toBe('-300.00');
});

// ─── Payoff ───────────────────────────────────────────────────────

test('payoff closes debt and deletes projected movements', function () {
    $user = User::factory()->create();

    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
        'payment_dates' => [
            now()->subMonth()->toDateString(),
            now()->toDateString(),
            now()->addMonth()->toDateString(),
            now()->addMonths(2)->toDateString(),
        ],
    ]);

    // Create 2 projected movements
    Movement::factory()->count(2)->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'is_projected' => true,
    ]);

    $response = $this->actingAs($user)->post(route('deudas.payoff', $debt), [
        'amount' => 500,
    ]);

    $response->assertRedirect();

    $debt->refresh();
    expect($debt->closed_at)->not->toBeNull();
    expect($debt->is_active)->toBeFalse();

    // Projected movements deleted
    expect($debt->movements()->where('is_projected', true)->count())->toBe(0);

    // Payoff movement created
    $payoffMovement = $debt->movements()
        ->where('description', 'like', '%Liquidación anticipada%')
        ->first();
    expect($payoffMovement)->not->toBeNull();
    expect($payoffMovement->amount)->toBe('-500.00');
    expect($payoffMovement->is_projected)->toBeFalse();
});

test('payoff validates amount is greater than zero', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('deudas.payoff', $debt), [
        'amount' => 0,
    ]);

    $response->assertSessionHasErrors('amount');
});

test('payoff fails if debt is already closed', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->closed()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('deudas.payoff', $debt), [
        'amount' => 500,
    ]);

    $response->assertStatus(422);
});

// ─── Destroy ──────────────────────────────────────────────────────

test('destroy returns 409 if debt has real payments', function () {
    $user = User::factory()->create();

    $debt = Debt::factory()->create(['user_id' => $user->id]);

    // Create a real payment
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'is_projected' => false,
    ]);

    $response = $this->actingAs($user)->delete(route('deudas.destroy', $debt));

    $response->assertStatus(409);
    expect(Debt::count())->toBe(1);
});

test('destroy deletes debt and projected movements if no real payments', function () {
    $user = User::factory()->create();

    $debt = Debt::factory()->create(['user_id' => $user->id]);

    // Create projected movements
    Movement::factory()->count(3)->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'is_projected' => true,
    ]);

    $response = $this->actingAs($user)->delete(route('deudas.destroy', $debt));

    $response->assertRedirect();
    expect(Debt::count())->toBe(0);
    expect(Movement::where('debt_id', $debt->id)->count())->toBe(0);
});

// ─── Authorization ────────────────────────────────────────────────

test('user cannot update another users debt', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $debt = Debt::factory()->create(['user_id' => $user1->id]);

    $response = $this->actingAs($user2)->put(route('deudas.update', $debt), [
        'name' => 'Updated name',
    ]);

    $response->assertStatus(403);
});

test('user cannot payoff another users debt', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $debt = Debt::factory()->create(['user_id' => $user1->id]);

    $response = $this->actingAs($user2)->post(route('deudas.payoff', $debt), [
        'amount' => 500,
    ]);

    $response->assertStatus(403);
});

test('user cannot delete another users debt', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $debt = Debt::factory()->create(['user_id' => $user1->id]);

    $response = $this->actingAs($user2)->delete(route('deudas.destroy', $debt));

    $response->assertStatus(403);
});
