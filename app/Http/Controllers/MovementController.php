<?php

namespace App\Http\Controllers;

use App\Http\Requests\MovementRequest;
use App\Models\Category;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    /**
     * Display a listing of movements for the selected month.
     */
    public function index(Request $request): Response
    {
        $month = $request->query('month');
        $selectedMonth = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m', $month)
            : Carbon::now();

        $openingBalance = Movement::openingBalance(
            $selectedMonth->copy()->startOfMonth(),
            $request->user()->id
        );

        $movements = Movement::forMonth($selectedMonth)
            ->where('user_id', $request->user()->id)
            ->orderBy('date')
            ->orderBy('id')
            ->with('category')
            ->get();

        $runningBalance = (float) $openingBalance;
        $today = Carbon::now()->toDateString();

        $transformed = $movements->map(function (Movement $movement) use (&$runningBalance, $today) {
            $runningBalance += (float) $movement->amount;

            return [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'description' => $movement->description,
                'category_id' => $movement->category_id,
                'category_name' => $movement->category?->name,
                'amount' => (float) $movement->amount,
                'running_balance' => $runningBalance,
                'is_projected' => $movement->date->toDateString() > $today,
                'notes' => $movement->notes,
            ];
        });

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'kind', 'color']);

        return Inertia::render('Movimientos/Index', [
            'movements' => $transformed,
            'categories' => $categories,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'openingBalance' => (float) $openingBalance,
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    /**
     * Store a newly created movement.
     */
    public function store(MovementRequest $request): RedirectResponse
    {
        $request->user()->movements()->create([
            ...$request->validated(),
            'source' => 'manual',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Movimiento registrado correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified movement.
     */
    public function update(MovementRequest $request, Movement $movement): RedirectResponse
    {
        if ($movement->user_id !== $request->user()->id) {
            abort(403);
        }

        $movement->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Movimiento actualizado correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified movement.
     */
    public function destroy(Request $request, Movement $movement): RedirectResponse
    {
        if ($movement->user_id !== $request->user()->id) {
            abort(403);
        }

        $movement->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Movimiento eliminado correctamente.',
        ]);

        return back();
    }
}
