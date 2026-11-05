<?php

namespace Database\Factories;

use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ubicacion>
 */
class UbicacionFactory extends Factory
{
    protected $model = Ubicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('UB-####')),
            'tipo' => 'estante',
            'pasillo' => fake()->numberBetween(1, 4),
            'estanteria' => fake()->numberBetween(1, 10),
            'nivel' => fake()->numberBetween(1, 3),
            'capacidad' => 100,
            'max_lotes' => 1,
            'activa' => true,
        ];
    }
}
