<?php

namespace Database\Factories;

use App\Enums\EstadoOrden;
use App\Models\OrdenProduccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenProduccion>
 */
class OrdenProduccionFactory extends Factory
{
    protected $model = OrdenProduccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('OP-#####')),
            'estado' => EstadoOrden::Lanzada,
            'user_id' => User::factory(),
        ];
    }
}
