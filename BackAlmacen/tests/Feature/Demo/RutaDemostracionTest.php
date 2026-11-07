<?php

namespace Tests\Feature\Demo;

use App\Enums\EstadoOrden;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\LanzarOrden;
use App\Services\RutaDemostracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La demostración recrea la ruta de picking del último pedido lanzado, sin
 * consumir nada. El servicio solo lee: da esa orden y sus tareas en orden de
 * ruta.
 *
 * Rojo hasta que exista RutaDemostracion.
 */
class RutaDemostracionTest extends TestCase
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

    private function lanzar(string $codigo): OrdenProduccion
    {
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);
        $articulo = Articulo::factory()->create();
        $this->stock($articulo, 10);

        return (new LanzarOrden)->lanzar($produccion, $codigo, [['articulo' => $articulo, 'cantidad' => 5]]);
    }

    public function test_sin_ordenes_activas_no_hay_ruta(): void
    {
        $this->assertNull((new RutaDemostracion)->ultimaOrden());
    }

    public function test_toma_la_ultima_orden_lanzada(): void
    {
        $this->lanzar('OP-1');
        $this->travelTo('2026-11-05 09:05:00');
        $this->lanzar('OP-2');

        $orden = (new RutaDemostracion)->ultimaOrden();

        $this->assertNotNull($orden);
        $this->assertSame('OP-2', $orden->codigo);
    }

    public function test_ignora_las_ordenes_que_ya_estan_en_picking(): void
    {
        // La demostración es para ver el recorrido ANTES de picar: solo mira
        // las órdenes lanzadas, no las que ya se están preparando.
        $this->lanzar('OP-LANZADA')->refresh();
        $this->travelTo('2026-11-05 09:05:00');
        $enPicking = $this->lanzar('OP-EN-PICKING');
        $enPicking->forceFill(['estado' => EstadoOrden::EnPicking])->save();

        $orden = (new RutaDemostracion)->ultimaOrden();

        $this->assertNotNull($orden);
        $this->assertSame('OP-LANZADA', $orden->codigo);
    }

    public function test_las_tareas_salen_en_orden_de_ruta(): void
    {
        $this->lanzar('OP-RUTA');
        $orden = OrdenProduccion::where('codigo', 'OP-RUTA')->firstOrFail();

        $tareas = (new RutaDemostracion)->tareas($orden);

        $secuencias = $tareas->pluck('secuencia')->all();
        $ordenadas = $secuencias;
        sort($ordenadas);
        $this->assertSame($ordenadas, $secuencias);
        $this->assertNotEmpty($tareas);
    }

    public function test_no_consume_stock(): void
    {
        $this->lanzar('OP-SIN-CONSUMO');
        $antes = (float) Stock::sum('cantidad');

        $servicio = new RutaDemostracion;
        $orden = $servicio->ultimaOrden();
        $servicio->tareas($orden);

        $this->assertSame($antes, (float) Stock::sum('cantidad'));
    }
}
