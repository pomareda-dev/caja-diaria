<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with monthly financial overview.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $month = $request->query('month');
        $selectedMonth = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m', $month)
            : Carbon::now();

        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();
        $today = Carbon::now()->toDateString();

        // ─── Card: Real balance up to today ───
        $realBalance = (float) Movement::realBalance($userId);

        // ─── Card: Income this month (real) ───
        $monthIncome = (float) Movement::where('user_id', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('date', '<=', $today)
            ->where('amount', '>', 0)
            ->whereIn('source', ['manual', 'import'])
            ->where('is_projected', false)
            ->sum('amount');

        // ─── Card: Expense this month (real, absolute value) ───
        $monthExpenseRaw = (float) Movement::where('user_id', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('date', '<=', $today)
            ->where('amount', '<', 0)
            ->whereIn('source', ['manual', 'import'])
            ->where('is_projected', false)
            ->sum('amount');

        $monthExpense = abs($monthExpenseRaw);

        // ─── Card: Projected end of month ───
        $futureSum = (float) Movement::where('user_id', $userId)
            ->where('date', '>', $today)
            ->where('date', '<=', $monthEnd)
            ->sum('amount');

        $projectedEndOfMonth = $realBalance + $futureSum;

        // ─── Budget overview: top 5 expense categories with limit ───
        $categoriesWithLimit = Category::where('user_id', $userId)
            ->where('kind', 'expense')
            ->whereNotNull('monthly_limit')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Spent = net spending for expense categories (abs of negative net, 0 otherwise).
        // Using SUM(amount) instead of SUM(ABS(amount)) so refunds/income in the
        // same category properly offset spending for budget progress.
        $spentByCategory = Movement::where('user_id', $userId)
            ->whereIn('category_id', $categoriesWithLimit->pluck('id'))
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('date', '<=', $today)
            ->where('is_projected', false)
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as net')
            ->pluck('net', 'category_id');

        $budgetOverview = $categoriesWithLimit
            ->map(fn (Category $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
                'monthly_limit' => (float) $cat->monthly_limit,
                'spent' => max(0, -(float) ($spentByCategory->get($cat->id) ?? 0)),
            ])
            ->sortByDesc('spent')
            ->take(5)
            ->values()
            ->all();

        // ─── Mini reconciliation ───
        $totalAccounts = (float) Account::where('user_id', $userId)
            ->where('exclude_from_reconciliation', false)
            ->sum('balance');

        $difference = round($totalAccounts - $realBalance, 2);
        $reconciled = abs($difference) <= 0.01;

        // ─── Upcoming projected movements (next 7 days) ───
        $nextWeekEnd = Carbon::now()->addDays(7)->toDateString();

        $upcoming = Movement::where('user_id', $userId)
            ->where('date', '>', $today)
            ->where('date', '<=', $nextWeekEnd)
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('category')
            ->get()
            ->map(fn (Movement $movement) => [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'description' => $movement->description,
                'category_name' => $movement->category?->name,
                'amount' => (float) $movement->amount,
                'is_projected' => (bool) $movement->is_projected,
            ]);

        // ─── Chart: daily running balance for the selected month ───
        $openingBalance = (float) Movement::openingBalance(
            $selectedMonth->copy()->startOfMonth(),
            $userId
        );

        $monthMovements = Movement::forMonth($selectedMonth)
            ->where('user_id', $userId)
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Build daily accumulation map
        $dailyAmounts = [];
        foreach ($monthMovements as $movement) {
            $dateStr = $movement->date->toDateString();
            $dailyAmounts[$dateStr] = ($dailyAmounts[$dateStr] ?? 0) + (float) $movement->amount;
        }

        $totalDays = (int) $selectedMonth->copy()->endOfMonth()->format('d');
        $running = $openingBalance;
        $dailyBalances = [];

        for ($day = 1; $day <= $totalDays; $day++) {
            $dateStr = $selectedMonth->copy()->startOfMonth()->addDays($day - 1)->toDateString();
            if (isset($dailyAmounts[$dateStr])) {
                $running += $dailyAmounts[$dateStr];
            }
            $dailyBalances[] = [
                'date' => $dateStr,
                'balance' => round($running, 2),
            ];
        }

        return Inertia::render('Dashboard', [
            'cards' => [
                'realBalance' => $realBalance,
                'monthIncome' => $monthIncome,
                'monthExpense' => $monthExpense,
                'projectedEndOfMonth' => $projectedEndOfMonth,
            ],
            'budgetOverview' => $budgetOverview,
            'reconciliation' => [
                'totalAccounts' => $totalAccounts,
                'realBalance' => $realBalance,
                'difference' => $difference,
                'reconciled' => $reconciled,
            ],
            'upcomingProjections' => $upcoming->values()->all(),
            'chartData' => $dailyBalances,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }
}
