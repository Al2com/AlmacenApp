<?php

namespace Tests\Feature\Ordenes;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\ConfirmarTarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas al confirmar una tarea de picking: consume el material reservado,
 * actualiza lo preparado y, al completar la orden, la pasa a preparada.
 *
 * Rojo hasta el paso 3 (depende de ConfirmarTarea).
 */
class ConfirmarTareaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tarea: TareaPicking, lote: Lote, ubicacion: Ubicacion, linea: OrdenLinea, orden: OrdenProduccion}
     */
    private function escenario(float $solicitada, float $cantidadTarea): array
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 10,
            'cantidad_reservada' => $cantidadTarea,
        ]);

        $orden = OrdenProduccion::factory()->create(['estado' => EstadoOrden::Lanzada]);
        $linea = OrdenLinea::factory()->create([
            'orden_id' => $orden->id,
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => $solicitada,
        ]);
        $tarea = TareaPicking::factory()->create([
            'orden_linea_id' => $linea->id,
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidadTarea,
            'secuencia' => 1,
        ]);

        return compact('tarea', 'lote', 'ubicacion', 'linea', 'orden');
    }

    public function test_confirmar_una_tarea_consume_el_material_reservado(): void
    {
        $operador = User::factory()->create();
        ['tarea' => $tarea, 'lote' => $lote, 'ubicacion' => $ubicacion] = $this->escenario(8, 8);

        (new ConfirmarTarea)->confirmar($tarea, $operador);

        // Sale el material: baja la cantidad y se libera la reserva.
        $this->assertDatabaseHas('stock', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 2,
            'cantidad_reservada' => 0,
        ]);
        // Queda el movimiento de consumo ligado a la tarea.
        $this->assertDatabaseHas('movimientos', [
            'tipo' => 'consumo',
            'lote_id' => $lote->id,
            'cantidad' => 8,
            'tarea_picking_id' => $tarea->id,
            'user_id' => $operador->id,
        ]);
    }

    public function test_confirmar_marca_la_tarea_y_lo_preparado_de_la_linea(): void
    {
        $operador = User::factory()->create();
        ['tarea' => $tarea, 'linea' => $linea] = $this->escenario(8, 8);

        (new ConfirmarTarea)->confirmar($tarea, $operador);

        $tarea->refresh();
        $this->assertSame(EstadoTarea::Confirmada, $tarea->estado);
        $this->assertNotNull($tarea->confirmada_at);
        $this->assertSame(8.0, (float) $linea->refresh()->cantidad_preparada);
    }

    public function test_al_confirmar_todas_las_tareas_la_orden_queda_preparada(): void
    {
        $operador = User::factory()->create();
        ['tarea' => $tarea, 'orden' => $orden] = $this->escenario(8, 8);

        (new ConfirmarTarea)->confirmar($tarea, $operador);

        $this->assertSame(EstadoOrden::Preparada, $orden->refresh()->estado);
    }

    public function test_mientras_queden_tareas_la_orden_esta_en_picking(): void
    {
        $operador = User::factory()->create();
        $datos = $this->escenario(16, 8);

        // Una segunda tarea pendiente de la misma línea deja la orden a medias.
        TareaPicking::factory()->create([
            'orden_linea_id' => $datos['linea']->id,
            'lote_id' => $datos['lote']->id,
            'ubicacion_id' => $datos['ubicacion']->id,
            'cantidad' => 8,
            'secuencia' => 2,
        ]);

        (new ConfirmarTarea)->confirmar($datos['tarea'], $operador);

        $this->assertSame(EstadoOrden::EnPicking, $datos['orden']->refresh()->estado);
    }
}
