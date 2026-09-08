<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoalRequest;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    /**
     * Display a listing of goals with their progress and contributions.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $goals = Goal::where('user_id', $userId)
            ->withSum('contributions', 'amount')
            ->withCount('contributions')
            ->with(['contributions' => function ($query) {
                $query->orderByDesc('date')->orderByDesc('id');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $mapContribution = fn (GoalContribution $contribution): array => [
            'id' => $contribution->id,
            'date' => $contribution->date->toDateString(),
            'amount' => (float) $contribution->amount,
            'notes' => $contribution->notes,
        ];

        $realBalance = (float) Movement::realBalance($userId);
        $apartado = Goal::apartadoAmount($userId);

        return Inertia::render('Metas/Index', [
            'goals' => $goals->map(function (Goal $goal) use ($mapContribution): array {
                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'target_amount' => (float) $goal->target_amount,
                    'target_date' => $goal->target_date?->toDateString(),
                    'progress_amount' => (float) $goal->progress_amount,
                    'percent' => $goal->percent,
                    'remaining_amount' => (float) $goal->remaining_amount,
                    'days_to_target' => $goal->target_date
                        ? now()->startOfDay()->diffInDays($goal->target_date->copy()->startOfDay(), false)
                        : null,
                    'can_delete' => $goal->contributions_count === 0,
                    'is_complete' => $goal->is_complete,
                    'contributions' => $goal->contributions->map($mapContribution)->values(),
                ];
            })->values(),
            'summary' => [
                'apartado' => $apartado,
                'available_real' => round($realBalance - $apartado, 2),
            ],
        ]);
    }

    /**
     * Store a newly created goal.
     */
    public function store(GoalRequest $request): RedirectResponse
    {
        $request->user()->goals()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Meta creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified goal and resync completion when the target changes.
     */
    public function update(GoalRequest $request, Goal $goal): RedirectResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($goal, $validated): void {
            $goal->update($validated);

            if (array_key_exists('target_amount', $validated)) {
                $goal->syncCompletion();
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Meta actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified goal only if it has no contributions.
     */
    public function destroy(Request $request, Goal $goal): RedirectResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($goal->contributions()->exists()) {
            abort(409, 'No se puede eliminar una meta con aportes registrados.');
        }

        $goal->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Meta eliminada correctamente.',
        ]);

        return back();
    }
}
