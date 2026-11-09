<?php

namespace Database\Factories;

use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lote_id' => Lote::factory(),
            'ubicacion_id' => Ubicacion::factory(),
            'cantidad' => 100,
            'cantidad_reservada' => 0,
        ];
    }
}
