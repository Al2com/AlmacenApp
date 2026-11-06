<?php

namespace Tests\Feature\Demo;

use App\Enums\Rol;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\LanzarOrden;
use App\Services\OperarioVirtual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El operario virtual pica la cola de tareas pendientes (la orden más antigua
 * primero, en orden de ruta) confirmando con la API real, como usuario de
 * demostración propio, nunca como el admin que lo puso en marcha.
 *
 * Rojo hasta que exista OperarioVirtual.
 */
class OperarioVirtualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');
    }

    private function stock(Articulo $articulo, float $cantidad): void
    {
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => 0,
        ]);
    }

    public function test_el_operario_de_demostracion_se_crea_una_sola_vez(): void
    {
        $servicio = new OperarioVirtual;

        $primero = $servicio->operario();
        $segundo = $servicio->operario();

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(Rol::OperadorAlmacen, $primero->rol);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_la_siguiente_tarea_es_la_de_la_orden_mas_antigua(): void
    {
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);
        $articuloA = Articulo::factory()->create();
        $this->stock($articuloA, 10);
        (new LanzarOrden)->lanzar($produccion, 'OP-A', [['articulo' => $articuloA, 'cantidad' => 5]]);

        $this->travelTo('2026-11-05 09:05:00');
        $articuloB = Articulo::factory()->create();
        $this->stock($articuloB, 10);
        (new LanzarOrden)->lanzar($produccion, 'OP-B', [['articulo' => $articuloB, 'cantidad' => 5]]);

        $siguiente = (new OperarioVirtual)->siguienteTarea();

        $this->assertSame('OP-A', $siguiente->ordenLinea->orden->codigo);
    }

    public function test_confirmar_la_siguiente_usa_el_operario_de_demostracion(): void
    {
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);
        $articulo = Articulo::factory()->create();
        $this->stock($articulo, 10);
        (new LanzarOrden)->lanzar($produccion, 'OP-C', [['articulo' => $articulo, 'cantidad' => 5]]);

        $servicio = new OperarioVirtual;
        $operario = $servicio->operario();
        $confirmada = $servicio->confirmarSiguiente();

        $this->assertDatabaseHas('movimientos', [
            'tipo' => 'consumo',
            'cantidad' => 5,
            'user_id' => $operario->id,
        ]);
        $this->assertNotSame($produccion->id, $operario->id);
        $this->assertSame('OP-C', $confirmada->ordenLinea->orden->codigo);
    }

    public function test_tras_confirmar_la_unica_tarea_no_queda_siguiente(): void
    {
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);
        $articulo = Articulo::factory()->create();
        $this->stock($articulo, 10);
        (new LanzarOrden)->lanzar($produccion, 'OP-D', [['articulo' => $articulo, 'cantidad' => 5]]);

        $servicio = new OperarioVirtual;
        $servicio->confirmarSiguiente();

        $this->assertNull($servicio->siguienteTarea());
    }

    public function test_sin_tareas_pendientes_confirmar_siguiente_no_hace_nada(): void
    {
        $confirmada = (new OperarioVirtual)->confirmarSiguiente();

        $this->assertNull($confirmada);
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_no_crea_el_operario_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $excepcion = null;
        try {
            (new OperarioVirtual)->operario();
        } catch (ReglaDeDominioException $e) {
            $excepcion = $e;
        }

        $this->app['env'] = 'testing';

        $this->assertNotNull($excepcion);
        $this->assertDatabaseCount('users', 0);
    }
}
