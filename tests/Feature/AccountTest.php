<?php

use App\Models\Account;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Authentication ───────────────────────────────────────────────

test('unauthenticated user cannot access accounts index', function () {
    $response = $this->get(route('cuentas.index'));

    $response->assertRedirect(route('login'));
});

test('unauthenticated user cannot create an account', function () {
    $response = $this->post(route('cuentas.store'), [
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 1000,
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated user can view accounts index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'balance' => 5000,
    ]);

    $response = $this->get(route('cuentas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Cuentas/Index')
        ->has('accounts', 1)
        ->has('reconciliation')
    );
});

// ─── Create ───────────────────────────────────────────────────────

test('authenticated user can create a bank account', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 5000,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => '5000.00',
        'exclude_from_reconciliation' => false,
    ]);
});

test('authenticated user can create an account excluded from reconciliation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'Liquidación',
        'kind' => 'other',
        'balance' => 10000,
        'exclude_from_reconciliation' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Liquidación',
        'exclude_from_reconciliation' => true,
    ]);
});

test('authenticated user can create a credit card account with negative balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'Visa',
        'kind' => 'credit',
        'balance' => -1500.50,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Visa',
        'balance' => '-1500.50',
    ]);
});

// ─── Update ───────────────────────────────────────────────────────

test('authenticated user can update their own account', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'balance' => 5000,
    ]);

    $response = $this->put(route('cuentas.update', $account), [
        'name' => 'BCP Corriente',
        'kind' => 'bank',
        'balance' => 5500.00,
    ]);

    $response->assertRedirect();
    $account->refresh();

    expect($account->name)->toBe('BCP Corriente');
    expect($account->balance)->toBe('5500.00');
});

test('user cannot update another users account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->put(route('cuentas.update', $account), [
        'name' => 'Hacked',
        'kind' => 'bank',
        'balance' => 999999,
    ]);

    $response->assertForbidden();
});

// ─── Delete ───────────────────────────────────────────────────────

test('authenticated user can delete their own account', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create(['user_id' => $user->id]);

    $response = $this->delete(route('cuentas.destroy', $account));

    $response->assertRedirect();
    $this->assertModelMissing($account);
});

test('user cannot delete another users account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other);

    $response = $this->delete(route('cuentas.destroy', $account));

    $response->assertForbidden();
    $this->assertModelExists($account);
});

// ─── Validation ───────────────────────────────────────────────────

test('validation: name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'kind' => 'bank',
        'balance' => 1000,
    ]);

    $response->assertSessionHasErrors('name');
});

test('validation: kind must be valid', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'Test',
        'kind' => 'invalid-kind',
        'balance' => 1000,
    ]);

    $response->assertSessionHasErrors('kind');
});

test('validation: balance must be numeric', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'Test',
        'kind' => 'bank',
        'balance' => 'not-a-number',
    ]);

    $response->assertSessionHasErrors('balance');
});

test('validation: balance is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'Test',
        'kind' => 'bank',
    ]);

    $response->assertSessionHasErrors('balance');
});

test('validation: name must be unique per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
    ]);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 1000,
    ]);

    $response->assertSessionHasErrors('name');
});

test('validation: same name allowed for different users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Account::factory()->create([
        'user_id' => $user1->id,
        'name' => 'BCP',
    ]);

    $this->actingAs($user2);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 2000,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('accounts', [
        'user_id' => $user2->id,
        'name' => 'BCP',
    ]);
});

test('validation: update ignores self name uniqueness', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
    ]);

    $response = $this->put(route('cuentas.update', $account), [
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 5000,
    ]);

    $response->assertRedirect();
    $account->refresh();
    expect($account->name)->toBe('BCP');
});

// ─── Reconciliation Logic ─────────────────────────────────────────

test('reconciliation shows total accounts excluding excluded ones', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Yape',
        'kind' => 'wallet',
        'balance' => 300,
        'exclude_from_reconciliation' => false,
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Liquidación',
        'kind' => 'other',
        'balance' => 10000,
        'exclude_from_reconciliation' => true,
    ]);

    $response = $this->get(route('cuentas.index'));

    // Total accounts should only sum non-excluded: 5000 + 300 = 5300
    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.totalAccounts', 5300)
        ->has('accounts', 3)
    );
});

test('reconciliation real balance uses all real movements up to today', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Opening balance: movements before July
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 2000,
        'source' => 'manual',
    ]);

    // Current month movements up to today
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

    // Future movement — should NOT be counted
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 300,
        'source' => 'manual',
    ]);

    // Recurring movement — should NOT be counted (not manual/import)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 100,
        'source' => 'recurring',
    ]);

    $response = $this->get(route('cuentas.index'));

    // realBalance = 2000 (opening) + 500 - 200 = 2300
    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.realBalance', 2300)
    );

    Carbon::setTestNow();
});

test('reconciliation shows reconciled when difference is zero', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Account balances = 5300
    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Yape',
        'balance' => 300,
        'exclude_from_reconciliation' => false,
    ]);

    // Real movements sum to 5300
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 5000,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 300,
        'source' => 'manual',
    ]);

    $response = $this->get(route('cuentas.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.difference', 0)
        ->where('reconciliation.reconciled', true)
    );

    Carbon::setTestNow();
});

test('reconciliation shows descuadre when total does not match real balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 4500,
        'source' => 'manual',
    ]);

    $response = $this->get(route('cuentas.index'));

    // totalAccounts = 5000, realBalance = 4500, difference = 500
    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.difference', 500)
        ->where('reconciliation.reconciled', false)
    );

    Carbon::setTestNow();
});

test('reconciliation is reconciled when difference is within epsilon', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Epsilon is 0.01 — difference of 0.005 should be considered reconciled
    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 5000.005,
        'source' => 'manual',
    ]);

    $response = $this->get(route('cuentas.index'));

    // difference = 5000 - 5000.005 = -0.005, rounded to -0.01, abs <= 0.01 → reconciled true
    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.reconciled', true)
    );

    Carbon::setTestNow();
});

// ─── Liquidación excluded scenario ────────────────────────────────

test('liquidacion account is excluded from reconciliation total but shown in list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'kind' => 'bank',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Liquidación',
        'kind' => 'other',
        'balance' => 10000,
        'exclude_from_reconciliation' => true,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 5000,
        'source' => 'manual',
    ]);

    $response = $this->get(route('cuentas.index'));

    // totalAccounts should exclude Liquidación: 5000 only
    // realBalance = 5000
    // difference = 0
    $response->assertInertia(fn ($page) => $page
        ->has('accounts', 2)
        ->where('reconciliation.totalAccounts', 5000)
        ->where('reconciliation.realBalance', 5000)
        ->where('reconciliation.difference', 0)
        ->where('reconciliation.reconciled', true)
    );

    Carbon::setTestNow();
});

// ─── User scoping ─────────────────────────────────────────────────

test('user only sees their own accounts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Account::factory()->create([
        'user_id' => $user1->id,
        'name' => 'De usuario 1',
    ]);

    Account::factory()->create([
        'user_id' => $user2->id,
        'name' => 'De usuario 2',
    ]);

    $this->actingAs($user1);

    $response = $this->get(route('cuentas.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('accounts', 1)
        ->where('accounts.0.name', 'De usuario 1')
    );
});

// ─── Account ordering ─────────────────────────────────────────────

test('accounts are ordered by sort_order then name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Segunda',
        'sort_order' => 1,
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Primera',
        'sort_order' => 0,
    ]);

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Tercera',
        'sort_order' => 2,
    ]);

    $response = $this->get(route('cuentas.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('accounts.0.name', 'Primera')
        ->where('accounts.1.name', 'Segunda')
        ->where('accounts.2.name', 'Tercera')
    );
});

// ─── Sort order default ────────────────────────────────────────────

test('sort_order empty string defaults to zero on create', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('cuentas.store'), [
        'name' => 'BCP Test',
        'kind' => 'bank',
        'balance' => 1000,
        'sort_order' => '',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'BCP Test',
        'sort_order' => 0,
    ]);
});
