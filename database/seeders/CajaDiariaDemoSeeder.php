<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CajaDiariaDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@cajadiaria.app',
        ]);

        // ─── Categories ───────────────────────────────────────────

        $mercado = $user->categories()->create([
            'name' => 'Mercado',
            'kind' => 'expense',
            'color' => '#FF6384',
            'sort_order' => 1,
        ]);

        $transporte = $user->categories()->create([
            'name' => 'Transporte',
            'kind' => 'expense',
            'color' => '#36A2EB',
            'sort_order' => 2,
        ]);

        $servicios = $user->categories()->create([
            'name' => 'Servicios',
            'kind' => 'expense',
            'color' => '#9966FF',
            'sort_order' => 3,
        ]);

        $falabella = $user->categories()->create([
            'name' => 'Falabella',
            'kind' => 'expense',
            'monthly_limit' => 2000,
            'color' => '#FFCE56',
            'sort_order' => 4,
        ]);

        $sueldo = $user->categories()->create([
            'name' => 'Sueldo',
            'kind' => 'income',
            'color' => '#4BC0C0',
            'sort_order' => 5,
        ]);

        // ─── Accounts ─────────────────────────────────────────────

        $user->accounts()->create([
            'name' => 'BCP Cuenta Sueldo',
            'kind' => 'bank',
            'balance' => 5000,
            'sort_order' => 1,
        ]);

        $user->accounts()->create([
            'name' => 'Billetera',
            'kind' => 'wallet',
            'balance' => 350,
            'sort_order' => 2,
        ]);

        $user->accounts()->create([
            'name' => 'Efectivo',
            'kind' => 'cash',
            'balance' => 200,
            'sort_order' => 3,
        ]);

        $user->accounts()->create([
            'name' => 'Liquidación',
            'kind' => 'other',
            'balance' => 0,
            'exclude_from_reconciliation' => true,
            'sort_order' => 4,
        ]);

        // ─── Movements ────────────────────────────────────────────

        $expenseCategories = [$mercado->id, $transporte->id, $servicios->id, $falabella->id];
        $incomeCategories = [$sueldo->id];
        $today = now();
        $daysInMonth = $today->daysInMonth;
        $descriptions = [
            'Compra en supermercado',
            'Pasaje de bus',
            'Pago de servicio',
            'Recarga de celular',
            'Comida rápida',
            'Gasolina',
            'Compra en farmacia',
            'Pago de recibo',
        ];

        for ($i = 0; $i < 30; $i++) {
            $isExpense = fake()->boolean(70);
            $catId = $isExpense
                ? fake()->randomElement($expenseCategories)
                : fake()->randomElement($incomeCategories);
            $amount = $isExpense
                ? fake()->randomFloat(2, 10, 500) * -1
                : fake()->randomFloat(2, 100, 3000);

            $user->movements()->create([
                'date' => $today->copy()->startOfMonth()->addDays(fake()->numberBetween(0, $daysInMonth - 1))->format('Y-m-d'),
                'description' => fake()->randomElement($descriptions),
                'category_id' => $catId,
                'amount' => $amount,
                'source' => 'manual',
                'notes' => null,
            ]);
        }

        // ─── Recurring Transactions ──────────────────────────────

        $sueldoRecurring = $user->recurringTransactions()->create([
            'name' => 'Sueldo mensual',
            'category_id' => $sueldo->id,
            'amount' => 3500,
            'day_of_month' => 1,
            'start_month' => now()->startOfMonth()->format('Y-m-d'),
            'active' => true,
        ]);

        $user->recurringTransactions()->create([
            'name' => 'Alquiler',
            'category_id' => $servicios->id,
            'amount' => -1200,
            'day_of_month' => 5,
            'start_month' => now()->startOfMonth()->format('Y-m-d'),
            'active' => true,
        ]);

        // ─── Movements with non-manual sources ───────────────────

        $user->movements()->create([
            'date' => $today->copy()->startOfMonth()->format('Y-m-d'),
            'description' => 'Sueldo recurrente',
            'category_id' => $sueldo->id,
            'amount' => 3500,
            'source' => 'recurring',
            'recurring_id' => $sueldoRecurring->id,
            'notes' => null,
        ]);

        $user->movements()->create([
            'date' => $today->copy()->startOfMonth()->addDays(2)->format('Y-m-d'),
            'description' => 'Importación inicial de saldos',
            'category_id' => null,
            'amount' => 5000,
            'source' => 'import',
            'notes' => 'Saldo inicial importado',
        ]);
    }
}
