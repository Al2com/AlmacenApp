<?php

namespace Database\Factories;

use App\Models\Articulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Articulo>
 */
class ArticuloFactory extends Factory
{
    protected $model = Articulo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('ART-####')),
            'nombre' => fake()->words(2, true),
            'unidad' => 'ud',
            'tiene_caducidad' => true,
            'activo' => true,
        ];
    }

    public function sinCaducidad(): static
    {
        return $this->state(fn (array $attributes) => [
            'tiene_caducidad' => false,
        ]);
    }
}
