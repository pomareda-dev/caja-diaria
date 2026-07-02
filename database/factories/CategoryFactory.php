<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $kinds = ['expense', 'income', 'transfer'];
        $names = [
            'expense' => ['Mercado', 'Transporte', 'Servicios', 'Salud', 'Educación', 'Entretenimiento', 'Comida', 'Ropa', 'Suscripciones', 'Luz', 'Agua', 'Internet'],
            'income' => ['Sueldo', 'Freelance', 'Inversiones', 'Ventas', 'Reembolso', 'Intereses'],
            'transfer' => ['Transferencia', 'Ahorro', 'Inversión'],
        ];

        $kind = fake()->randomElement($kinds);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement($names[$kind]).' '.fake()->numberBetween(1000, 9999),
            'kind' => $kind,
            'monthly_limit' => $kind === 'expense' ? fake()->optional(0.3)->randomFloat(2, 100, 5000) : null,
            'color' => fake()->optional(0.5)->hexColor(),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
