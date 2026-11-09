<?php

namespace Tests\Feature\Inventario;

use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Services\ReservaDeStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La reserva suma a cantidad_reservada por FEFO (no es un movimiento); una
 * segunda reserva solo ve lo que quedó disponible.
 *
 * Rojo hasta que exista ReservaDeStock.
 */
class ReservaDeStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05');
    }

    private function stock(Articulo $articulo, string $caducidad, float $cantidad): Lote
    {
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => $caducidad]);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => 0,
        ]);

        return $lote;
    }

    public function test_reserva_por_fefo_repartiendo_entre_lotes(): void
    {
        $articulo = Articulo::factory()->create();
        $pronto = $this->stock($articulo, '2026-11-20', 10);
        $tarde = $this->stock($articulo, '2027-02-01', 10);

        $resultado = (new ReservaDeStock)->reservar($articulo, 15);

        $this->assertSame(15.0, $resultado['reservado']);
        $this->assertSame(0.0, $resultado['faltante']);
        $this->assertDatabaseHas('stock', ['lote_id' => $pronto->id, 'cantidad_reservada' => 10]);
        $this->assertDatabaseHas('stock', ['lote_id' => $tarde->id, 'cantidad_reservada' => 5]);
    }

    public function test_una_segunda_reserva_solo_ve_lo_disponible(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = $this->stock($articulo, '2027-02-01', 10);
        $servicio = new ReservaDeStock;

        $primera = $servicio->reservar($articulo, 7);
        $segunda = $servicio->reservar($articulo, 7);

        $this->assertSame(7.0, $primera['reservado']);
        $this->assertSame(3.0, $segunda['reservado']);
        $this->assertSame(4.0, $segunda['faltante']);
        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad_reservada' => 10]);
    }

    public function test_no_reserva_lotes_caducados(): void
    {
        $articulo = Articulo::factory()->create();
        $caducado = $this->stock($articulo, '2026-10-20', 10);
        $valido = $this->stock($articulo, '2027-02-01', 10);

        $resultado = (new ReservaDeStock)->reservar($articulo, 6);

        $this->assertSame(6.0, $resultado['reservado']);
        $this->assertDatabaseHas('stock', ['lote_id' => $caducado->id, 'cantidad_reservada' => 0]);
        $this->assertDatabaseHas('stock', ['lote_id' => $valido->id, 'cantidad_reservada' => 6]);
    }

    public function test_liberar_reduce_la_reserva(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = $this->stock($articulo, '2027-02-01', 10);
        $servicio = new ReservaDeStock;
        $servicio->reservar($articulo, 8);

        $liberado = $servicio->liberar($articulo, 5);

        $this->assertSame(5.0, $liberado);
        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad_reservada' => 3]);
    }
}
