<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── User hasMany relationships ───────────────────────────────────

test('user has many categories', function () {
    $user = User::factory()->create();
    Category::factory()->count(3)
        ->sequence(
            ['name' => 'Mercado', 'kind' => 'expense'],
            ['name' => 'Transporte', 'kind' => 'expense'],
            ['name' => 'Servicios', 'kind' => 'expense'],
        )->create(['user_id' => $user->id]);

    expect($user->categories)->toHaveCount(3);
    $user->categories->each(fn ($cat) => expect($cat->user_id)->toBe($user->id));
});

test('user has many accounts', function () {
    $user = User::factory()->create();
    Account::factory()->count(2)
        ->sequence(
            ['name' => 'BCP Corriente', 'kind' => 'bank'],
            ['name' => 'Billetera', 'kind' => 'wallet'],
        )->create(['user_id' => $user->id]);

    expect($user->accounts)->toHaveCount(2);
});

test('user has many movements', function () {
    $user = User::factory()->create();
    Movement::factory()->count(5)->create(['user_id' => $user->id]);

    expect($user->movements)->toHaveCount(5);
});

test('user has many recurring transactions', function () {
    $user = User::factory()->create();
    RecurringTransaction::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->recurringTransactions)->toHaveCount(2);
});

// ─── Model belongsTo relationships ────────────────────────────────

test('category belongs to user', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);

    expect($category->user)->toBeInstanceOf(User::class);
    expect($category->user->id)->toBe($user->id);
});

test('account belongs to user', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    expect($account->user)->toBeInstanceOf(User::class);
});

test('recurring transaction belongs to user', function () {
    $user = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $user->id]);

    expect($recurring->user)->toBeInstanceOf(User::class);
});

test('recurring transaction belongs to category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $recurring = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
    ]);

    expect($recurring->category)->toBeInstanceOf(Category::class);
    expect($recurring->category->id)->toBe($category->id);
});

test('recurring transaction belongs to nullable category', function () {
    $user = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'category_id' => null,
    ]);

    expect($recurring->category)->toBeNull();
});

test('movement belongs to user', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create(['user_id' => $user->id]);

    expect($movement->user)->toBeInstanceOf(User::class);
});

test('movement belongs to category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
    ]);

    expect($movement->category)->toBeInstanceOf(Category::class);
    expect($movement->category->id)->toBe($category->id);
});

test('movement belongs to nullable category', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => null,
    ]);

    expect($movement->category)->toBeNull();
});

test('movement belongs to recurring transaction', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $recurring = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
    ]);
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'recurring_id' => $recurring->id,
    ]);

    expect($movement->recurringTransaction)->toBeInstanceOf(RecurringTransaction::class);
    expect($movement->recurringTransaction->id)->toBe($recurring->id);
});

test('movement belongs to nullable recurring transaction', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'recurring_id' => null,
    ]);

    expect($movement->recurringTransaction)->toBeNull();
});

// ─── Casts ────────────────────────────────────────────────────────

test('movement date is cast to carbon', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
    ]);

    expect($movement->date)->toBeInstanceOf(CarbonInterface::class);
});

test('movement amount is decimal cast to string', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'amount' => 1234.56,
    ]);

    expect($movement->amount)->toBeString();
    expect((float) $movement->amount)->toBe(1234.56);
});

test('recurring transaction dates are cast to carbon', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $recurring = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'start_month' => '2026-01-01',
    ]);

    expect($recurring->start_month)->toBeInstanceOf(CarbonInterface::class);
});

test('recurring transaction active is cast to boolean', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $recurring = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'active' => true,
    ]);

    expect($recurring->active)->toBeTrue();
});

test('account exclude_from_reconciliation is cast to boolean', function () {
    $account = Account::factory()->create(['exclude_from_reconciliation' => true]);

    expect($account->exclude_from_reconciliation)->toBeTrue();
});

// ─── Scopes ───────────────────────────────────────────────────────

test('forMonth scope returns only movements in the given month', function () {
    $user = User::factory()->create();

    // First day of the month — must be INCLUDED (whereBetween is inclusive)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
    ]);
    // July 2026
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-25',
    ]);
    // June 2026
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
    ]);
    // August 2026
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-08-05',
    ]);

    $movements = Movement::forMonth(Carbon::parse('2026-07-15'))->get();

    expect($movements)->toHaveCount(3);
    $movements->each(fn ($m) => expect($m->date->format('Y-m'))->toBe('2026-07'));
});

test('actual scope returns movements with date <= today', function () {
    $user = User::factory()->create();
    $today = now()->toDateString();

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => $today,
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => now()->subDay()->toDateString(),
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => now()->addDay()->toDateString(),
    ]);

    $actual = Movement::actual()->get();

    expect($actual)->toHaveCount(2);
});

test('projected scope returns movements with date > today', function () {
    $user = User::factory()->create();

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => now()->subDay()->toDateString(),
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => now()->addDay()->toDateString(),
    ]);
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => now()->addDays(2)->toDateString(),
    ]);

    $projected = Movement::projected()->get();

    expect($projected)->toHaveCount(2);
});

test('openingBalance returns sum of manual and import movements before month start', function () {
    $user = User::factory()->create();
    $monthStart = Carbon::parse('2026-07-01');

    // Before July, manual — should be counted
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Before July, import — should be counted
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-20',
        'amount' => -200,
        'source' => 'import',
    ]);

    // Before July, recurring — should be excluded
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 500,
        'source' => 'recurring',
    ]);

    // In July (not before) — should be excluded
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => 300,
        'source' => 'manual',
    ]);

    $balance = Movement::openingBalance($monthStart, $user->id);

    // 1000 + (-200) = 800
    expect((float) $balance)->toBe(800.0);
    expect($balance)->toBe('800.00');
});

// ─── Unique constraints ───────────────────────────────────────────

test('cannot create two categories with same name for same user', function () {
    $user = User::factory()->create();

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]);

    expect(fn () => Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
    ]))->toThrow(QueryException::class);
});

test('same category name allowed for different users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Category::factory()->create([
        'user_id' => $userA->id,
        'name' => 'Mercado',
    ]);
    Category::factory()->create([
        'user_id' => $userB->id,
        'name' => 'Mercado',
    ]);

    expect(Category::count())->toBe(2);
});

test('cannot create two accounts with same name for same user', function () {
    $user = User::factory()->create();

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
    ]);

    expect(fn () => Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
    ]))->toThrow(QueryException::class);
});

// ─── FK cascade on delete ─────────────────────────────────────────

test('deleting a user cascades to categories, accounts, movements, and recurring transactions', function () {
    $user = User::factory()->create();
    Category::factory()->create(['user_id' => $user->id]);
    Account::factory()->create(['user_id' => $user->id]);
    Movement::factory()->create(['user_id' => $user->id]);
    RecurringTransaction::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(Category::where('user_id', $user->id)->count())->toBe(0);
    expect(Account::where('user_id', $user->id)->count())->toBe(0);
    expect(Movement::where('user_id', $user->id)->count())->toBe(0);
    expect(RecurringTransaction::where('user_id', $user->id)->count())->toBe(0);
});

// ─── FK set-null on delete ────────────────────────────────────────

test('deleting a category sets movement category_id to null', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
    ]);

    $category->delete();

    expect($movement->fresh()->category_id)->toBeNull();
});

test('deleting a recurring transaction sets movement recurring_id to null', function () {
    $user = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $user->id]);
    $movement = Movement::factory()->create([
        'user_id' => $user->id,
        'recurring_id' => $recurring->id,
    ]);

    $recurring->delete();

    expect($movement->fresh()->recurring_id)->toBeNull();
});

test('deleting a category sets recurring transaction category_id to null', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);
    $recurring = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
    ]);

    $category->delete();

    expect($recurring->fresh()->category_id)->toBeNull();
});

// ─── Column defaults ──────────────────────────────────────────────

test('account balance defaults to zero and exclude_from_reconciliation defaults to false', function () {
    $user = User::factory()->create();
    $account = $user->accounts()->create([
        'name' => 'Test Account',
        'kind' => 'bank',
    ]);

    $fresh = $account->fresh();

    expect($fresh->balance)->toBe('0.00');
    expect($fresh->exclude_from_reconciliation)->toBeFalse();
});

test('recurring transaction active defaults to true', function () {
    $user = User::factory()->create();
    $recurring = $user->recurringTransactions()->create([
        'name' => 'Test Recurring',
        'amount' => 1000,
        'day_of_month' => 15,
        'start_month' => '2026-01-01',
    ]);

    expect($recurring->fresh()->active)->toBeTrue();
});

// ─── Enum rejection ───────────────────────────────────────────────

test('cannot create category with invalid kind', function () {
    $user = User::factory()->create();

    expect(fn () => $user->categories()->create([
        'name' => 'Test',
        'kind' => 'invalid',
    ]))->toThrow(QueryException::class);
});

test('cannot create movement with invalid source', function () {
    $user = User::factory()->create();

    expect(fn () => $user->movements()->create([
        'date' => '2026-07-15',
        'description' => 'Test',
        'amount' => 100,
        'source' => 'invalid',
    ]))->toThrow(QueryException::class);
});

// ─── forMonth year boundary ───────────────────────────────────────

test('forMonth scope handles year boundary correctly', function () {
    $user = User::factory()->create();

    Movement::factory()->create(['user_id' => $user->id, 'date' => '2026-12-15']);
    Movement::factory()->create(['user_id' => $user->id, 'date' => '2026-12-31']);
    Movement::factory()->create(['user_id' => $user->id, 'date' => '2027-01-05']);
    Movement::factory()->create(['user_id' => $user->id, 'date' => '2027-01-15']);

    $december = Movement::forMonth(Carbon::parse('2026-12-15'))->get();

    expect($december)->toHaveCount(2);
    $december->each(fn ($m) => expect($m->date->format('Y-m'))->toBe('2026-12'));
});

// ─── openingBalance edge cases ────────────────────────────────────

test('openingBalance returns zero formatted string for empty month', function () {
    $user = User::factory()->create();
    $monthStart = Carbon::parse('2026-07-01');

    $balance = Movement::openingBalance($monthStart, $user->id);

    expect($balance)->toBe('0.00');
});

test('openingBalance excludes movement dated exactly on month start', function () {
    $user = User::factory()->create();
    $monthStart = Carbon::parse('2026-07-01');

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 500,
        'source' => 'manual',
    ]);

    $balance = Movement::openingBalance($monthStart, $user->id);

    expect($balance)->toBe('0.00');
});

// ─── Account unique allows same name for different users ──────────

test('same account name allowed for different users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Account::factory()->create(['user_id' => $userA->id, 'name' => 'BCP']);
    Account::factory()->create(['user_id' => $userB->id, 'name' => 'BCP']);

    expect(Account::count())->toBe(2);
});

// ─── exclude_from_reconciliation can be set true ──────────────────

test('account can be excluded from reconciliation', function () {
    $user = User::factory()->create();
    $account = $user->accounts()->create([
        'name' => 'Liquidación',
        'kind' => 'other',
        'balance' => 0,
        'exclude_from_reconciliation' => true,
    ]);

    expect($account->fresh()->exclude_from_reconciliation)->toBeTrue();
});
