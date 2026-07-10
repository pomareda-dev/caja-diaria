<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProjectionController extends Controller
{
    /**
     * Display the future projection timeline.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $userId = $user->id;
        $today = Carbon::now()->toDateString();

        // Get all future movements (date > today, regardless of is_projected flag)
        $futureMovements = Movement::where('user_id', $userId)
            ->where('date', '>', $today)
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('category')
            ->get();

        $realBalance = (float) Movement::realBalance($userId);

        // Compute running balance starting from realBalance
        $runningBalance = $realBalance;
        $items = $futureMovements->map(function (Movement $movement) use (&$runningBalance) {
            $runningBalance += (float) $movement->amount;

            return [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'description' => $movement->description,
                'category_id' => $movement->category_id,
                'category_name' => $movement->category?->name,
                'category_color' => $movement->category?->color,
                'amount' => (float) $movement->amount,
                'source' => $movement->source,
                'is_projected' => (bool) $movement->is_projected,
                'running_balance' => $runningBalance,
            ];
        });

        return Inertia::render('Proyeccion/Index', [
            'items' => $items->values()->all(),
            'openingBalance' => $realBalance,
            'horizonMonths' => $user->settings['projection_horizon'] ?? 12,
        ]);
    }
}
