<?php

namespace Database\Factories;

use App\Models\Articulo;
use App\Models\Lote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lote>
 */
class LoteFactory extends Factory
{
    protected $model = Lote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'articulo_id' => Articulo::factory(),
            'codigo_lote' => strtoupper(fake()->unique()->bothify('L-#####')),
            'fecha_recepcion' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'caducidad' => now()->addDays(fake()->numberBetween(10, 180))->toDateString(),
        ];
    }

    public function sinCaducidad(): static
    {
        return $this->state(fn (array $attributes) => [
            'caducidad' => null,
        ]);
    }
}
