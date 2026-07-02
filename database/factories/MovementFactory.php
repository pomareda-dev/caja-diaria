<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    protected $model = Movement::class;

    public function definition(): array
    {
        $descriptions = [
            'Compra en supermercado',
            'Pasaje de bus',
            'Pago de servicio',
            'Recarga de celular',
            'Comida rápida',
            'Gasolina',
            'Compra en farmacia',
            'Pago de suscripción',
            'Transferencia',
            'Retiro de efectivo',
            'Pago de recibo',
            'Compra en línea',
        ];

        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'description' => fake()->randomElement($descriptions),
            'category_id' => fn (array $attributes) => Category::factory()->create(['user_id' => $attributes['user_id']]),
            'amount' => fake()->randomFloat(2, 10, 500) * (fake()->boolean(70) ? -1 : 1),
            'source' => 'manual',
            'recurring_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
