<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoffDebtRequest;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    /**
     * Display a listing of debts.
     */
    public function index(Request $request): Response
    {
        $debts = Debt::where('user_id', $request->user()->id)
            ->withCount(['movements as real_movements_count' => function ($q) {
                $q->where('is_projected', false);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Deudas/Index', [
            'debts' => $debts->map(fn (Debt $debt) => [
                'id' => $debt->id,
                'name' => $debt->name,
                'principal_amount' => (float) $debt->principal_amount,
                'disbursement_date' => $debt->disbursement_date->format('Y-m-d'),
                'installment_amount' => (float) $debt->installment_amount,
                'installments_count' => $debt->installments_count,
                'payment_dates' => $debt->payment_dates,
                'closed_at' => $debt->closed_at?->toDateTimeString(),
                'rate_factor' => $debt->rate_factor,
                'total_to_pay' => (float) $debt->total_to_pay,
                'paid_installments' => $debt->paid_installments,
                'remaining' => (float) $debt->remaining,
                'can_delete' => $debt->real_movements_count === 0,
                'is_active' => $debt->is_active,
            ]),
        ]);
    }

    /**
     * Store a newly created debt with its movements in a transaction.
     */
    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {
            // Create the debt
            $debt = $user->debts()->create([
                'name' => $validated['name'],
                'principal_amount' => $validated['principal_amount'],
                'disbursement_date' => $validated['disbursement_date'],
                'installment_amount' => $validated['installment_amount'],
                'installments_count' => $validated['installments_count'],
                'payment_dates' => $validated['payment_dates'],
            ]);

            // Find or create "Préstamo" category
            $loanCategory = Category::where('user_id', $user->id)
                ->where('name', 'Préstamo')
                ->first();

            // Create disbursement movement (+principal)
            $disbursementDate = Carbon::parse($validated['disbursement_date']);
            $isProjected = $disbursementDate->isFuture();

            $user->movements()->create([
                'date' => $validated['disbursement_date'],
                'description' => "Desembolso {$debt->name}",
                'category_id' => $loanCategory?->id,
                'amount' => $validated['principal_amount'],
                'source' => 'manual',
                'debt_id' => $debt->id,
                'is_projected' => $isProjected,
                'sort_order' => Movement::nextSortOrder($user->id, $validated['disbursement_date'], $isProjected),
            ]);

            // Create installment movements (-installment)
            foreach ($validated['payment_dates'] as $index => $paymentDate) {
                $paymentDateCarbon = Carbon::parse($paymentDate);
                $isProjected = $paymentDateCarbon->isFuture();
                $installmentNumber = $index + 1;

                $user->movements()->create([
                    'date' => $paymentDate,
                    'description' => "Cuota {$debt->name} ({$installmentNumber}/{$validated['installments_count']})",
                    'category_id' => $loanCategory?->id,
                    'amount' => -$validated['installment_amount'],
                    'source' => 'manual',
                    'debt_id' => $debt->id,
                    'is_projected' => $isProjected,
                    'sort_order' => Movement::nextSortOrder($user->id, $paymentDate, $isProjected),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified debt and regenerate projected movements.
     */
    public function update(UpdateDebtRequest $request, Debt $debt): RedirectResponse
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $debt, $user) {
            // Update debt fields
            $debt->update($validated);

            // Delete only projected movements linked to this debt
            $debt->movements()->where('is_projected', true)->delete();

            // Find or create "Préstamo" category
            $loanCategory = Category::where('user_id', $user->id)
                ->where('name', 'Préstamo')
                ->first();

            // Regenerate projected movements for disbursement if future
            $disbursementDate = Carbon::parse($debt->disbursement_date);
            if ($disbursementDate->isFuture()) {
                $user->movements()->create([
                    'date' => $disbursementDate->toDateString(),
                    'description' => "Desembolso {$debt->name}",
                    'category_id' => $loanCategory?->id,
                    'amount' => $debt->principal_amount,
                    'source' => 'manual',
                    'debt_id' => $debt->id,
                    'is_projected' => true,
                    'sort_order' => Movement::nextSortOrder($user->id, $disbursementDate->toDateString(), true),
                ]);
            }

            // Regenerate projected installment movements
            foreach ($debt->payment_dates as $index => $paymentDate) {
                $paymentDateCarbon = Carbon::parse($paymentDate);
                if ($paymentDateCarbon->isFuture()) {
                    $installmentNumber = $index + 1;

                    $user->movements()->create([
                        'date' => $paymentDate,
                        'description' => "Cuota {$debt->name} ({$installmentNumber}/{$debt->installments_count})",
                        'category_id' => $loanCategory?->id,
                        'amount' => -$debt->installment_amount,
                        'source' => 'manual',
                        'debt_id' => $debt->id,
                        'is_projected' => true,
                        'sort_order' => Movement::nextSortOrder($user->id, $paymentDate, true),
                    ]);
                }
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Pay off the debt early with a single movement.
     */
    public function payoff(PayoffDebtRequest $request, Debt $debt): RedirectResponse
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! $debt->is_active) {
            abort(422, 'La deuda ya está cerrada.');
        }

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $debt, $user) {
            // Find or create "Préstamo" category
            $loanCategory = Category::where('user_id', $user->id)
                ->where('name', 'Préstamo')
                ->first();

            // Create payoff movement
            $user->movements()->create([
                'date' => now()->toDateString(),
                'description' => "Liquidación anticipada {$debt->name}",
                'category_id' => $loanCategory?->id,
                'amount' => -$validated['amount'],
                'source' => 'manual',
                'debt_id' => $debt->id,
                'is_projected' => false,
                'sort_order' => Movement::nextSortOrder($user->id, now()->toDateString(), false),
            ]);

            // Delete remaining projected movements
            $debt->movements()->where('is_projected', true)->delete();

            // Close the debt
            $debt->update(['closed_at' => now()]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda liquidada correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified debt only if no real payments exist.
     */
    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        // Check if there are any real (non-projected) movements
        $hasRealPayments = $debt->movements()
            ->where('is_projected', false)
            ->exists();

        if ($hasRealPayments) {
            abort(409, 'No se puede eliminar una deuda con pagos registrados.');
        }

        // Delete projected movements and the debt
        DB::transaction(function () use ($debt) {
            $debt->movements()->where('is_projected', true)->delete();
            $debt->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda eliminada correctamente.',
        ]);

        return back();
    }
}
