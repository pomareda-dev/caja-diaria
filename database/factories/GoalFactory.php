<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Laptop nueva',
                'Viaje a Bariloche',
                'Fondo de emergencia',
                'Curso de inglés',
                'Bicicleta',
                'Consola de videojuegos',
            ]),
            'target_amount' => fake()->randomFloat(2, 500, 10000),
            'target_date' => fake()->optional(0.7)->dateTimeBetween('now', '+2 years')?->format('Y-m-d'),
            'completed_at' => null,
        ];
    }

    /**
     * Mark the goal as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
