<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with spent for the selected month.
     */
    public function index(Request $request): Response
    {
        $month = $request->query('month');
        $selectedMonth = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m', $month)
            : Carbon::now();

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Batch compute balance (income - expenses) per category for the selected month
        $balanceByCategory = Movement::where('user_id', $request->user()->id)
            ->whereIn('category_id', $categories->pluck('id'))
            ->whereBetween('date', [
                $selectedMonth->copy()->startOfMonth()->toDateString(),
                $selectedMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->where('date', '<=', Carbon::now()->toDateString())
            ->where('is_projected', false)
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as balance')
            ->pluck('balance', 'category_id');

        $categoriesWithBudget = $categories->map(function (Category $cat) use ($balanceByCategory) {
            $balance = (float) ($balanceByCategory->get($cat->id) ?? 0);

            // Derive spent from balance so the progress bar matches the balance column:
            // - expense categories: net spending = abs(balance) when balance < 0, else 0
            // - income categories: net earning = balance when balance > 0, else 0
            // - transfer / others: abs(balance) as a neutral fallback
            $spent = match ($cat->kind) {
                'expense' => max(0, -$balance),
                'income' => max(0, $balance),
                default => abs($balance),
            };

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'kind' => $cat->kind,
                'color' => $cat->color,
                'monthly_limit' => $cat->monthly_limit ? (float) $cat->monthly_limit : null,
                'spent' => $spent,
                'balance' => $balance,
                'sort_order' => $cat->sort_order,
            ];
        });

        return Inertia::render('Categorias/Index', [
            'categories' => $categoriesWithBudget,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $request->user()->categories()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified category.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        if ($category->user_id !== $request->user()->id) {
            abort(403);
        }

        $category->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Reorder categories by reassigning sort_order sequentially.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $ids = $validated['ids'];
        $categories = Category::whereIn('id', $ids)
            ->where('user_id', $request->user()->id)
            ->get();

        if ($categories->count() !== count($ids)) {
            abort(403);
        }

        if (count($ids) > 1) {
            DB::transaction(function () use ($ids): void {
                foreach ($ids as $index => $id) {
                    Category::where('id', $id)->update(['sort_order' => $index + 1]);
                }
            });
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Orden actualizado correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->user_id !== $request->user()->id) {
            abort(403);
        }

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría eliminada correctamente.',
        ]);

        return back();
    }
}
