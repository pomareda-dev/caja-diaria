<?php

use App\Models\Debt;
use App\Models\Movement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Relationships ────────────────────────────────────────────────

test('debt belongs to user', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create(['user_id' => $user->id]);

    expect($debt->user)->toBeInstanceOf(User::class);
    expect($debt->user->id)->toBe($user->id);
});

test('user has many debts', function () {
    $user = User::factory()->create();
    Debt::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->debts)->toHaveCount(3);
    $user->debts->each(fn ($debt) => expect($debt->user_id)->toBe($user->id));
});

test('debt has many movements', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create(['user_id' => $user->id]);
    Movement::factory()->count(2)->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
    ]);

    expect($debt->movements)->toHaveCount(2);
});

test('movement belongs to debt', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create(['user_id' => $user->id]);
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
    ]);

    expect($movement->debt)->toBeInstanceOf(Debt::class);
    expect($movement->debt->id)->toBe($debt->id);
});

test('movement belongs to nullable debt', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => null,
    ]);

    expect($movement->debt)->toBeNull();
});

// ─── Casts ────────────────────────────────────────────────────────

test('debt principal_amount is decimal cast to string', function () {
    $debt = Debt::factory()->create(['principal_amount' => 5000.50]);

    expect($debt->principal_amount)->toBeString();
    expect((float) $debt->principal_amount)->toBe(5000.50);
});

test('debt installment_amount is decimal cast to string', function () {
    $debt = Debt::factory()->create(['installment_amount' => 250.75]);

    expect($debt->installment_amount)->toBeString();
    expect((float) $debt->installment_amount)->toBe(250.75);
});

test('debt disbursement_date is cast to carbon', function () {
    $debt = Debt::factory()->create(['disbursement_date' => '2026-08-01']);

    expect($debt->disbursement_date)->toBeInstanceOf(CarbonInterface::class);
    expect($debt->disbursement_date->format('Y-m-d'))->toBe('2026-08-01');
});

test('debt installments_count is cast to integer', function () {
    $debt = Debt::factory()->create(['installments_count' => 6]);

    expect($debt->installments_count)->toBeInt();
    expect($debt->installments_count)->toBe(6);
});

test('debt payment_dates is cast to array', function () {
    $dates = ['2026-09-01', '2026-10-01', '2026-11-01'];
    $debt = Debt::factory()->create(['payment_dates' => $dates]);

    expect($debt->payment_dates)->toBeArray();
    expect($debt->payment_dates)->toBe($dates);
});

test('debt closed_at is cast to carbon or null', function () {
    $debt = Debt::factory()->create(['closed_at' => '2026-08-15 10:00:00']);

    expect($debt->closed_at)->toBeInstanceOf(CarbonInterface::class);

    $openDebt = Debt::factory()->create(['closed_at' => null]);

    expect($openDebt->closed_at)->toBeNull();
});

// ─── Accessors ────────────────────────────────────────────────────

test('rateFactor returns derived factor', function () {
    $debt = Debt::factory()->create([
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 6,
    ]);

    // (250 × 6) / 1000 = 1.5
    expect($debt->rate_factor)->toBe(1.5);
});

test('rateFactor returns zero when principal is zero', function () {
    $debt = Debt::factory()->create([
        'principal_amount' => 0,
        'installment_amount' => 250,
        'installments_count' => 6,
    ]);

    expect($debt->rate_factor)->toBe(0.0);
});

test('totalToPay returns installment × count', function () {
    $debt = Debt::factory()->create([
        'installment_amount' => 250,
        'installments_count' => 6,
    ]);

    expect($debt->total_to_pay)->toBe('1500.00');
});

test('paidInstallments counts only real installment payments', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create(['user_id' => $user->id]);

    // 2 real installment payments
    Movement::factory()->count(2)->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => false,
    ]);

    // 3 projected movements
    Movement::factory()->count(3)->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => true,
    ]);

    expect($debt->paid_installments)->toBe(2);
});

test('paidInstallments ignores the disbursement movement', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
    ]);

    // Real disbursement (+1000) is not an installment payment
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => 1000,
        'is_projected' => false,
    ]);

    // One real installment payment
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => false,
    ]);

    expect($debt->paid_installments)->toBe(1);
});

test('remaining decreases with real payments', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
    ]);

    // total_to_pay = 250 × 4 = 1000.00
    expect($debt->remaining)->toBe('1000.00');

    // Pay one installment (-250)
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => false,
    ]);

    expect($debt->fresh()->remaining)->toBe('750.00');
});

test('remaining ignores the disbursement movement', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
    ]);

    // Real disbursement (+1000): money received, not a payment
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => 1000,
        'is_projected' => false,
    ]);

    // One real installment payment (-250)
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => false,
    ]);

    expect($debt->fresh()->remaining)->toBe('750.00');
});

test('paidAmount sums only real payments and ignores the disbursement', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
    ]);

    // Real disbursement (+1000): excluded
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => 1000,
        'is_projected' => false,
    ]);

    // Real installment payments (-250 and a partial -100)
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => false,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -100,
        'is_projected' => false,
    ]);

    // Projected movement: excluded
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -250,
        'is_projected' => true,
    ]);

    expect($debt->paid_amount)->toBe('350.00');
});

test('remaining never goes below zero', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
    ]);

    // Overpay: pay -1500 when total is 1000
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'amount' => -1500,
        'is_projected' => false,
    ]);

    expect($debt->fresh()->remaining)->toBe('0.00');
});

test('isActive is true when closed_at is null', function () {
    $debt = Debt::factory()->create(['closed_at' => null]);

    expect($debt->is_active)->toBeTrue();
});

test('isActive is false when closed_at is set', function () {
    $debt = Debt::factory()->create(['closed_at' => now()]);

    expect($debt->is_active)->toBeFalse();
});

// ─── Factory ──────────────────────────────────────────────────────

test('factory generates coherent payment_dates matching installments_count', function () {
    $debt = Debt::factory()->create();

    expect($debt->payment_dates)->toHaveCount($debt->installments_count);
});

test('factory closed state sets closed_at', function () {
    $debt = Debt::factory()->closed()->create();

    expect($debt->closed_at)->not->toBeNull();
    expect($debt->is_active)->toBeFalse();
});

// ─── FK cascade / nullOnDelete ────────────────────────────────────

test('deleting a user cascades to debts', function () {
    $user = User::factory()->create();
    Debt::factory()->count(2)->create(['user_id' => $user->id]);

    $user->delete();

    expect(Debt::where('user_id', $user->id)->count())->toBe(0);
});

test('deleting a debt sets movement debt_id to null', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->create(['user_id' => $user->id]);
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
    ]);

    $debt->delete();

    expect($movement->fresh()->debt_id)->toBeNull();
});
