<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalContributionRequest;
use App\Models\Goal;
use App\Models\GoalContribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GoalContributionController extends Controller
{
    /**
     * Register a contribution against a goal and resync its completion.
     */
    public function store(StoreGoalContributionRequest $request, Goal $goal): RedirectResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($request, $goal): void {
            $goal->contributions()->create($request->validated());
            $goal->syncCompletion();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Aporte registrado correctamente.',
        ]);

        return back();
    }

    /**
     * Remove a contribution and resync completion (may reopen the goal).
     * The contribution is resolved scoped to the goal in the path.
     */
    public function destroy(Request $request, Goal $goal, GoalContribution $contribution): RedirectResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($contribution->goal_id !== $goal->id) {
            abort(404);
        }

        DB::transaction(function () use ($goal, $contribution): void {
            $contribution->delete();
            $goal->syncCompletion();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Aporte eliminado correctamente.',
        ]);

        return back();
    }
}
