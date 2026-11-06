<?php

namespace Tests\Feature\Inventario;

use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\TrasladarStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrasladarStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05');
    }

    private function origenConStock(Lote $lote, float $cantidad, float $reservada = 0): Ubicacion
    {
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => $reservada,
        ]);

        return $ubicacion;
    }

    public function test_traslada_cantidad_entre_ubicaciones(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01', 'fecha_recepcion' => '2026-10-01']);
        $origen = $this->origenConStock($lote, 10);
        $destino = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);

        (new TrasladarStock)->trasladar($lote, $origen, $destino, 4, User::factory()->create());

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'ubicacion_id' => $origen->id, 'cantidad' => 6]);
        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'ubicacion_id' => $destino->id, 'cantidad' => 4]);
        $this->assertDatabaseHas('movimientos', ['tipo' => 'traslado', 'cantidad' => 4]);
    }

    public function test_solo_traslada_la_cantidad_disponible(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $origen = $this->origenConStock($lote, 10, 7);
        $destino = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);

        $this->expectException(ReglaDeDominioException::class);

        (new TrasladarStock)->trasladar($lote, $origen, $destino, 5, User::factory()->create());
    }

    public function test_no_traslada_a_un_destino_inactivo(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $origen = $this->origenConStock($lote, 10);
        $destino = Ubicacion::factory()->create(['activa' => false]);

        $this->expectException(ReglaDeDominioException::class);

        (new TrasladarStock)->trasladar($lote, $origen, $destino, 4, User::factory()->create());
    }

    public function test_no_traslada_un_lote_caducado(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2026-10-20']);
        $origen = $this->origenConStock($lote, 10);
        $destino = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);

        try {
            (new TrasladarStock)->trasladar($lote, $origen, $destino, 4, User::factory()->create());
            $this->fail('Se esperaba una ReglaDeDominioException.');
        } catch (ReglaDeDominioException) {
            // esperado
        }

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_el_traslado_no_cambia_la_fecha_de_entrada_del_lote(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01', 'fecha_recepcion' => '2026-10-01']);
        $origen = $this->origenConStock($lote, 10);
        $destino = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);

        (new TrasladarStock)->trasladar($lote, $origen, $destino, 4, User::factory()->create());

        $this->assertSame('2026-10-01', $lote->refresh()->fecha_recepcion->toDateString());
    }
}
