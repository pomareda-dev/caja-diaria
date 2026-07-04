<?php

namespace App\Http\Controllers;

use App\Http\Requests\MovementRequest;
use App\Models\Category;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('category')
            ->get();

        $today = Carbon::now()->toDateString();

        // Partition: real = is_projected=false AND date<=today; projected = is_projected=true OR date>today
        $realMovements = collect();
        $projectedMovements = collect();

        foreach ($movements as $movement) {
            $isProjected = (bool) $movement->is_projected || $movement->date->toDateString() > $today;
            $row = [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'description' => $movement->description,
                'category_id' => $movement->category_id,
                'category_name' => $movement->category?->name,
                'amount' => (float) $movement->amount,
                'is_projected' => $isProjected,
                'notes' => $movement->notes,
            ];

            if ($isProjected) {
                $projectedMovements->push($row);
            } else {
                $realMovements->push($row);
            }
        }

        // Compute running_balance only over realMovements
        $runningBalance = (float) $openingBalance;
        $realMovements = $realMovements->map(function (array $row) use (&$runningBalance) {
            $runningBalance += (float) $row['amount'];
            $row['running_balance'] = $runningBalance;

            return $row;
        });

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'kind', 'color']);

        return Inertia::render('Movimientos/Index', [
            'realMovements' => $realMovements->values()->all(),
            'projectedMovements' => $projectedMovements->values()->all(),
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
        $validated = $request->validated();
        $isProjected = (bool) ($validated['is_projected'] ?? false);
        $date = $validated['date'];
        $sortOrder = Movement::nextSortOrder($request->user()->id, $date, $isProjected);

        $request->user()->movements()->create([
            ...$validated,
            'source' => 'manual',
            'sort_order' => $sortOrder,
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

        $validated = $request->validated();
        $isChangingToReal = $movement->is_projected && ! ($validated['is_projected'] ?? false);
        $isDateChanged = isset($validated['date']) && $validated['date'] !== $movement->date->toDateString();

        if ($isChangingToReal || $isDateChanged) {
            $targetDate = $validated['date'] ?? $movement->date->toDateString();
            $targetProjected = (bool) ($validated['is_projected'] ?? $movement->is_projected);
            $validated['sort_order'] = Movement::nextSortOrder(
                $request->user()->id,
                $targetDate,
                $targetProjected
            );
        }

        $movement->update($validated);

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

    /**
     * Reorder movements within the same (user, date, is_projected) group.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:movements,id'],
        ]);

        $ids = $validated['ids'];
        $movements = Movement::whereIn('id', $ids)
            ->where('user_id', $request->user()->id)
            ->get();

        // Cross-user or missing ids check
        if ($movements->count() !== count($ids)) {
            abort(403);
        }

        // Validate all share the same date and is_projected
        $firstDate = $movements->first()->date->toDateString();
        $firstProjected = (bool) $movements->first()->is_projected;

        $allSameGroup = $movements->every(function (Movement $movement) use ($firstDate, $firstProjected) {
            return $movement->date->toDateString() === $firstDate
                && (bool) $movement->is_projected === $firstProjected;
        });

        if (! $allSameGroup) {
            abort(422);
        }

        // Reassign sort_order atomically (skip single-row — idempotent)
        if (count($ids) > 1) {
            DB::transaction(function () use ($ids): void {
                foreach ($ids as $index => $id) {
                    Movement::where('id', $id)->update(['sort_order' => $index + 1]);
                }
            });
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Orden actualizado correctamente.',
        ]);

        return back();
    }
}
