<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas garantizadas por la propia base de datos (restricciones CHECK),
 * sin depender de ningún servicio.
 */
class StockNoNegativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_guardar_stock_con_cantidad_negativa(): void
    {
        $lote = Lote::factory()->create();
        $ubicacion = Ubicacion::factory()->create();

        $this->expectException(QueryException::class);

        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => -1,
            'cantidad_reservada' => 0,
        ]);
    }

    public function test_no_permite_reservar_mas_cantidad_de_la_disponible(): void
    {
        $lote = Lote::factory()->create();
        $ubicacion = Ubicacion::factory()->create();

        $this->expectException(QueryException::class);

        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 5,
            'cantidad_reservada' => 6,
        ]);
    }
}
