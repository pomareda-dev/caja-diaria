<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts with reconciliation data.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $accounts = Account::where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Reconciliation computation
        $totalAccounts = (float) $accounts
            ->where('exclude_from_reconciliation', false)
            ->sum('balance');

        $realBalance = (float) Movement::realBalance($userId);

        $difference = round($totalAccounts - $realBalance, 2);
        $reconciled = abs($difference) <= 0.01;

        return Inertia::render('Cuentas/Index', [
            'accounts' => $accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'kind' => $account->kind,
                'balance' => (float) $account->balance,
                'exclude_from_reconciliation' => $account->exclude_from_reconciliation,
                'sort_order' => $account->sort_order,
            ]),
            'reconciliation' => [
                'totalAccounts' => $totalAccounts,
                'realBalance' => $realBalance,
                'difference' => $difference,
                'reconciled' => $reconciled,
            ],
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(AccountRequest $request): RedirectResponse
    {
        $request->user()->accounts()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cuenta creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified account.
     */
    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        if ($account->user_id !== $request->user()->id) {
            abort(403);
        }

        $account->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cuenta actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Reorder accounts by reassigning sort_order sequentially.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:accounts,id'],
        ]);

        $ids = $validated['ids'];
        $accounts = Account::whereIn('id', $ids)
            ->where('user_id', $request->user()->id)
            ->get();

        if ($accounts->count() !== count($ids)) {
            abort(403);
        }

        if (count($ids) > 1) {
            DB::transaction(function () use ($ids): void {
                foreach ($ids as $index => $id) {
                    Account::where('id', $id)->update(['sort_order' => $index + 1]);
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
     * Remove the specified account.
     */
    public function destroy(Request $request, Account $account): RedirectResponse
    {
        if ($account->user_id !== $request->user()->id) {
            abort(403);
        }

        $account->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cuenta eliminada correctamente.',
        ]);

        return back();
    }
}
