<?php

namespace Database\Factories;

use App\Models\Articulo;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenLinea>
 */
class OrdenLineaFactory extends Factory
{
    protected $model = OrdenLinea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orden_id' => OrdenProduccion::factory(),
            'articulo_id' => Articulo::factory(),
            'cantidad_solicitada' => 10,
            'cantidad_preparada' => 0,
        ];
    }
}
