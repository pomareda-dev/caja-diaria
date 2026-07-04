<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Batch compute actual spent per category for the selected month
        $spentByCategory = Movement::where('user_id', $request->user()->id)
            ->whereIn('category_id', $categories->pluck('id'))
            ->whereBetween('date', [
                $selectedMonth->copy()->startOfMonth()->toDateString(),
                $selectedMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->where('date', '<=', Carbon::now()->toDateString())
            ->where('amount', '<', 0)
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as spent')
            ->pluck('spent', 'category_id');

        $categoriesWithBudget = $categories->map(function (Category $cat) use ($spentByCategory) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'kind' => $cat->kind,
                'color' => $cat->color,
                'monthly_limit' => $cat->monthly_limit ? (float) $cat->monthly_limit : null,
                'spent' => (float) ($spentByCategory->get($cat->id) ?? 0),
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
