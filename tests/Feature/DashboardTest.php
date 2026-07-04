<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Authentication ───────────────────────────────────────────────

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard has correct inertia props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('cards', fn ($cards) => $cards
            ->has('realBalance')
            ->has('monthIncome')
            ->has('monthExpense')
            ->has('projectedEndOfMonth')
        )
        ->has('budgetOverview')
        ->has('reconciliation', fn ($rec) => $rec
            ->has('totalAccounts')
            ->has('realBalance')
            ->has('difference')
            ->has('reconciled')
        )
        ->has('upcomingProjections')
        ->has('chartData')
        ->has('selectedMonth')
        ->has('currentMonth')
    );
});

// ─── Card Values ──────────────────────────────────────────────────

test('cards show correct values for current month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Opening: 1000 before month
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-30',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Income in month (real)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-05',
        'amount' => 500,
        'source' => 'manual',
    ]);

    // Expense in month (real)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
    ]);

    // Future movement (projected)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'amount' => 300,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        // realBalance = 1000 + 500 - 200 = 1300 (excludes future projected)
        ->where('cards.realBalance', 1300)
        // income = 500
        ->where('cards.monthIncome', 500)
        // expense = abs(-200) = 200
        ->where('cards.monthExpense', 200)
        // projectedEndOfMonth = 1300 + 300 = 1600
        ->where('cards.projectedEndOfMonth', 1600)
    );

    Carbon::setTestNow();
});

test('cards show zero values when no movements exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('cards.realBalance', 0)
        ->where('cards.monthIncome', 0)
        ->where('cards.monthExpense', 0)
        ->where('cards.projectedEndOfMonth', 0)
    );
});

test('projected end of month includes future movements this month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Real balance: 1000
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Future this month: +500
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-25',
        'amount' => 500,
        'source' => 'manual',
    ]);

    // Future outside month (next month) — should NOT be counted
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-08-05',
        'amount' => 9999,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    // projectedEndOfMonth = 1000 + 500 = 1500
    $response->assertInertia(fn ($page) => $page
        ->where('cards.projectedEndOfMonth', 1500)
    );

    Carbon::setTestNow();
});

// ─── Budget Overview ──────────────────────────────────────────────

test('budget overview shows top categories with spent vs limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $food = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Comida',
        'kind' => 'expense',
        'monthly_limit' => 500,
        'color' => '#ff0000',
    ]);

    $transport = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Transporte',
        'kind' => 'expense',
        'monthly_limit' => 200,
        'color' => '#00ff00',
    ]);

    // Income category — should NOT appear in budget overview
    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sueldo',
        'kind' => 'income',
        'monthly_limit' => null,
    ]);

    // Spent in Comida: 300
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-05',
        'amount' => -300,
        'category_id' => $food->id,
        'source' => 'manual',
    ]);

    // Spent in Transporte: 100
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -100,
        'category_id' => $transport->id,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->has('budgetOverview', 2)
        ->where('budgetOverview.0.name', 'Comida')
        ->where('budgetOverview.0.spent', 300)
        ->where('budgetOverview.0.monthly_limit', 500)
        ->where('budgetOverview.1.name', 'Transporte')
        ->where('budgetOverview.1.spent', 100)
        ->where('budgetOverview.1.monthly_limit', 200)
    );

    Carbon::setTestNow();
});

test('budget overview only includes expense categories with limit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sin límite',
        'kind' => 'expense',
        'monthly_limit' => null,
    ]);

    Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Con límite',
        'kind' => 'expense',
        'monthly_limit' => 1000,
    ]);

    $response = $this->get(route('dashboard'));

    // Only "Con límite" should appear
    $response->assertInertia(fn ($page) => $page
        ->has('budgetOverview', 1)
        ->where('budgetOverview.0.name', 'Con límite')
    );
});

test('budget overview limits to 5 items', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create 7 expense categories with limits
    for ($i = 1; $i <= 7; $i++) {
        Category::factory()->create([
            'user_id' => $user->id,
            'name' => "Cat {$i}",
            'kind' => 'expense',
            'monthly_limit' => 1000,
            'sort_order' => $i,
        ]);
    }

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('budgetOverview', 5)
    );
});

// ─── Reconciliation ───────────────────────────────────────────────

test('reconciliation shows reconciled when accounts equal real balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Account::factory()->create([
        'user_id' => $user,
        'name' => 'BCP',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 5000,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.difference', 0)
        ->where('reconciliation.reconciled', true)
    );

    Carbon::setTestNow();
});

test('reconciliation shows descuadre when accounts differ from real balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Account::factory()->create([
        'user_id' => $user,
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

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.difference', 500)
        ->where('reconciliation.reconciled', false)
    );

    Carbon::setTestNow();
});

test('reconciliation excludes accounts marked as excluded', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Account::factory()->create([
        'user_id' => $user,
        'name' => 'BCP',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    Account::factory()->create([
        'user_id' => $user,
        'name' => 'Liquidación',
        'balance' => 10000,
        'exclude_from_reconciliation' => true,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-01',
        'amount' => 5000,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard'));

    // totalAccounts should be 5000 only (excludes Liquidación's 10000)
    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.totalAccounts', 5000)
        ->where('reconciliation.difference', 0)
        ->where('reconciliation.reconciled', true)
    );

    Carbon::setTestNow();
});

// ─── Upcoming Projections ─────────────────────────────────────────

test('upcoming projections shows next 7 days', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Day +1 (today+1) — should appear
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'description' => 'Pago mañana',
        'amount' => -100,
        'source' => 'manual',
    ]);

    // Day +5 — should appear
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-20',
        'description' => 'Pago en 5 días',
        'amount' => 500,
        'source' => 'manual',
    ]);

    // Day +8 (outside window) — should NOT appear
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-23',
        'description' => 'Fuera de ventana',
        'amount' => 200,
        'source' => 'manual',
    ]);

    // Today — should NOT appear (date > today, not >=)
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-15',
        'description' => 'Hoy',
        'amount' => 100,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcomingProjections', 2)
        ->where('upcomingProjections.0.description', 'Pago mañana')
        ->where('upcomingProjections.1.description', 'Pago en 5 días')
    );

    Carbon::setTestNow();
});

test('upcoming projections empty when no future movements exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcomingProjections', 0)
    );
});

test('upcoming projections ordered by date', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-18',
        'description' => 'Segundo',
        'amount' => -50,
        'source' => 'manual',
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'description' => 'Primero',
        'amount' => 100,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcomingProjections', 2)
        ->where('upcomingProjections.0.description', 'Primero')
        ->where('upcomingProjections.1.description', 'Segundo')
    );

    Carbon::setTestNow();
});

// ─── Chart Data ───────────────────────────────────────────────────

test('chart data covers all days of the month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    // July has 31 days
    $response->assertInertia(fn ($page) => $page
        ->has('chartData', 31)
        ->where('chartData.0.date', '2026-07-01')
        ->where('chartData.30.date', '2026-07-31')
    );
});

test('chart data running balance accumulates correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Opening balance: 1000
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-30',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Day 5: +500 → running = 1500
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-05',
        'amount' => 500,
        'source' => 'manual',
    ]);

    // Day 10: -200 → running = 1300
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => -200,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        // Day 1: just opening effectively — no movements on day 1
        ->where('chartData.0.balance', 1000)
        // Day 4 (index 4 = July 5): after adding 500
        ->where('chartData.4.balance', 1500)
        // Day 9 (index 9 = July 10): after subtracting 200
        ->where('chartData.9.balance', 1300)
    );

    Carbon::setTestNow();
});

// ─── Month Filtering ──────────────────────────────────────────────

test('dashboard respects month filter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Movement in June
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-15',
        'amount' => 500,
        'source' => 'manual',
    ]);

    // Movement in July
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => 300,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-06']));

    // Income for June should be 500
    $response->assertInertia(fn ($page) => $page
        ->where('selectedMonth', '2026-06')
        ->where('cards.monthIncome', 500)
    );

    Carbon::setTestNow();
});

test('dashboard defaults to current month', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $now = now()->format('Y-m');

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('selectedMonth', $now)
    );
});

// ─── User Scoping ─────────────────────────────────────────────────

test('user only sees their own data on dashboard', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // User's movement
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-10',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Other user's movement
    Movement::factory()->create([
        'user_id' => $other->id,
        'date' => '2026-07-10',
        'amount' => 9999,
        'source' => 'manual',
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    $response->assertInertia(fn ($page) => $page
        ->where('cards.monthIncome', 1000)
    );

    Carbon::setTestNow();
});

// ─── Projected flag in upcoming ────────────────────────────────────

test('upcoming projections include is_projected flag', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-16',
        'description' => 'Real futuro',
        'amount' => 100,
        'source' => 'manual',
        'is_projected' => false,
    ]);

    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-17',
        'description' => 'Proyectado',
        'amount' => 200,
        'source' => 'manual',
        'is_projected' => true,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcomingProjections', 2)
        ->where('upcomingProjections.0.is_projected', false)
        ->where('upcomingProjections.1.is_projected', true)
    );

    Carbon::setTestNow();
});
