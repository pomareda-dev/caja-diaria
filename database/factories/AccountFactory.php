<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $kinds = ['bank', 'wallet', 'cash', 'credit', 'other'];
        $names = [
            'bank' => ['BCP', 'Interbank', 'BBVA', 'Scotiabank', 'Banco de la Nación'],
            'wallet' => ['Yape', 'Plin', 'Billetera Digital', 'Tunki'],
            'cash' => ['Efectivo', 'Caja chica'],
            'credit' => ['Visa', 'Mastercard', 'American Express', 'Diners Club'],
            'other' => ['Ahorros', 'Fondo de emergencia', 'CTS'],
        ];

        $kind = fake()->randomElement($kinds);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement($names[$kind]).' '.fake()->numberBetween(1000, 9999),
            'kind' => $kind,
            'balance' => fake()->randomFloat(2, -1000, 50000),
            'exclude_from_reconciliation' => $kind === 'credit' ? fake()->boolean(20) : fake()->boolean(5),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
