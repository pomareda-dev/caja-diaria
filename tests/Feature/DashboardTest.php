<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
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
        ->has('debtsOverview')
        ->has('goalsOverview')
        ->has('goalsSummary', fn ($goals) => $goals
            ->has('apartado')
            ->has('available_real')
            ->has('active_count')
        )
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

// ─── Active Debts ─────────────────────────────────────────────────

test('debts overview includes active debts with progress and next installment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $debt = Debt::factory()->create([
        'user_id' => $user->id,
        'name' => 'Préstamo personal',
        'principal_amount' => 1000,
        'installment_amount' => 250,
        'installments_count' => 4,
        'payment_dates' => [
            '2026-07-20',
            '2026-08-20',
            '2026-09-20',
            '2026-10-20',
        ],
    ]);

    // One real installment paid
    Movement::factory()->create([
        'user_id' => $user->id,
        'debt_id' => $debt->id,
        'date' => '2026-07-10',
        'amount' => -250,
        'is_projected' => false,
    ]);

    // Closed debts must be excluded
    Debt::factory()->closed()->create([
        'user_id' => $user->id,
        'name' => 'Deuda cerrada',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('debtsOverview', 1)
        ->where('debtsOverview.0.name', 'Préstamo personal')
        ->where('debtsOverview.0.paid_installments', 1)
        ->where('debtsOverview.0.installments_count', 4)
        ->where('debtsOverview.0.remaining', fn ($value) => (float) $value === 750.0)
        ->where('debtsOverview.0.rate_factor', fn ($value) => (float) $value === 1.0)
        ->where('debtsOverview.0.next_date', '2026-07-20')
        ->where('debtsOverview.0.next_amount', fn ($value) => (float) $value === 250.0)
    );

    Carbon::setTestNow();
});

test('debts overview is empty when user has no active debts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->has('debtsOverview', 0));
});

test('debts overview only includes the current user debts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    Debt::factory()->create([
        'user_id' => $user->id,
        'name' => 'Mi deuda',
    ]);

    Debt::factory()->create([
        'user_id' => $other->id,
        'name' => 'Deuda ajena',
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('debtsOverview', 1)
        ->where('debtsOverview.0.name', 'Mi deuda')
    );
});

// ─── Active Goals ─────────────────────────────────────────────────

test('goals summary computes available_real as realBalance minus apartado', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Real balance: 1300
    Movement::factory()->create([
        'user_id' => $user->id,
        'amount' => 1300,
        'is_projected' => false,
    ]);

    // Active goal with 300 apartado
    $activeGoal = Goal::factory()->create(['user_id' => $user->id, 'name' => 'Laptop nueva']);
    GoalContribution::factory()->create(['goal_id' => $activeGoal->id, 'amount' => 300]);

    // Completed goal must NOT reduce available_real
    $completedGoal = Goal::factory()->completed()->create(['user_id' => $user->id]);
    GoalContribution::factory()->create(['goal_id' => $completedGoal->id, 'amount' => 700]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('goalsSummary.apartado', 300)
        ->where('goalsSummary.available_real', 1000)
        ->where('goalsSummary.active_count', 1)
    );
});

test('goals overview includes active goals with progress and remaining', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'name' => 'Viaje a Bariloche',
        'target_amount' => 2000,
        'target_date' => now()->addMonths(2)->toDateString(),
    ]);
    GoalContribution::factory()->create(['goal_id' => $goal->id, 'amount' => 500]);

    // Completed goals must be excluded
    Goal::factory()->completed()->create(['user_id' => $user->id, 'name' => 'Meta completada']);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('goalsOverview', 1)
        ->where('goalsOverview.0.name', 'Viaje a Bariloche')
        ->where('goalsOverview.0.target_amount', fn ($value) => (float) $value === 2000.0)
        ->where('goalsOverview.0.progress_amount', fn ($value) => (float) $value === 500.0)
        ->where('goalsOverview.0.percent', 25)
        ->where('goalsOverview.0.remaining_amount', fn ($value) => (float) $value === 1500.0)
        ->where('goalsOverview.0.target_date', now()->addMonths(2)->toDateString())
        ->where('goalsOverview.0.days_to_target', fn ($value) => is_int($value) && $value > 0)
    );
});

test('goals overview is empty when user has no active goals', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('goalsOverview', 0)
        ->where('goalsSummary.apartado', 0)
        ->where('goalsSummary.active_count', 0)
    );
});

test('goals overview only includes the current user goals', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    Goal::factory()->create(['user_id' => $user->id, 'name' => 'Mi meta']);
    Goal::factory()->create(['user_id' => $other->id, 'name' => 'Meta ajena']);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('goalsOverview', 1)
        ->where('goalsOverview.0.name', 'Mi meta')
    );
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

// ─── Projection source semantics (per plan §3.2: projection = future date) ──

test('projected end of month includes recurring source future movements', function () {
    // Per plan §3.2 a movement is "projected" if date > today, regardless of source.
    // realBalance only sums date <= today (and is_projected=false), so a recurring
    // future row is excluded there by date; futureSum (date > today) must then
    // include it to be counted once in the projection. The two date ranges are
    // disjoint, so there is no double counting. This test locks that behavior.
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Real today: 1000
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-01',
        'amount' => 1000,
        'source' => 'manual',
    ]);

    // Recurring-generated future payment: -200 on day 25
    Movement::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-07-25',
        'amount' => -200,
        'source' => 'recurring',
        'is_projected' => true,
    ]);

    $response = $this->get(route('dashboard', ['month' => '2026-07']));

    // projectedEndOfMonth = 1000 + (-200) = 800, NOT 1000.
    $response->assertInertia(fn ($page) => $page
        ->where('cards.realBalance', 1000)
        ->where('cards.projectedEndOfMonth', 800)
    );

    Carbon::setTestNow();
});

test('reconciliation excludes other users accounts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'BCP',
        'balance' => 5000,
        'exclude_from_reconciliation' => false,
    ]);

    // Other user's account must NOT leak into this user's reconciliation total.
    Account::factory()->create([
        'user_id' => $other->id,
        'name' => 'Ajeno BCP',
        'balance' => 100000,
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
        ->where('reconciliation.totalAccounts', 5000)
        ->where('reconciliation.reconciled', true)
    );

    Carbon::setTestNow();
});
