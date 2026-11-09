<?php

namespace Tests\Feature\Ordenes;

use App\Enums\EstadoTarea;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TareasApiTest extends TestCase
{
    use RefreshDatabase;

    private function almacen(): User
    {
        return User::factory()->create(['rol' => Rol::OperadorAlmacen]);
    }

    /**
     * Crea una tarea pendiente con su stock reservado.
     */
    private function tarea(int $secuencia, float $cantidad = 5): TareaPicking
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 10,
            'cantidad_reservada' => $cantidad,
        ]);
        $linea = OrdenLinea::factory()->create([
            'orden_id' => OrdenProduccion::factory()->create()->id,
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => $cantidad,
        ]);

        return TareaPicking::factory()->create([
            'orden_linea_id' => $linea->id,
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'secuencia' => $secuencia,
        ]);
    }

    public function test_las_tareas_pendientes_se_listan_en_orden_de_ruta(): void
    {
        $this->tarea(secuencia: 3);
        $this->tarea(secuencia: 1);
        $this->tarea(secuencia: 2);

        $secuencias = $this->actingAs($this->almacen())
            ->getJson('/api/tareas')
            ->assertOk()
            ->json('data.*.secuencia');

        $this->assertSame([1, 2, 3], $secuencias);
    }

    /**
     * @return array<string, mixed>
     */
    private function codigos(TareaPicking $tarea): array
    {
        $tarea->loadMissing('ubicacion', 'lote');

        return [
            'codigo_ubicacion' => $tarea->ubicacion->codigo,
            'codigo_lote' => $tarea->lote->codigo_lote,
            'cantidad' => (float) $tarea->cantidad,
        ];
    }

    public function test_el_operador_de_almacen_confirma_una_tarea_con_los_codigos_correctos(): void
    {
        $tarea = $this->tarea(secuencia: 1, cantidad: 5);

        $this->actingAs($this->almacen())
            ->postJson("/api/tareas/{$tarea->id}/confirmar", $this->codigos($tarea))
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada');

        $this->assertDatabaseHas('movimientos', ['tipo' => 'consumo', 'tarea_picking_id' => $tarea->id]);
        $this->assertSame(EstadoTarea::Confirmada, $tarea->refresh()->estado);
    }

    public function test_no_confirma_si_los_codigos_no_coinciden(): void
    {
        $tarea = $this->tarea(secuencia: 1, cantidad: 5);

        $this->actingAs($this->almacen())
            ->postJson("/api/tareas/{$tarea->id}/confirmar", [
                'codigo_ubicacion' => 'OTRA',
                'codigo_lote' => $tarea->lote->codigo_lote,
                'cantidad' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo_ubicacion');

        $this->assertDatabaseCount('movimientos', 0);
        $this->assertSame(EstadoTarea::Pendiente, $tarea->refresh()->estado);
    }

    public function test_abrir_incidencia_libera_la_reserva_sin_consumir(): void
    {
        $tarea = $this->tarea(secuencia: 1, cantidad: 5);

        $this->actingAs($this->almacen())
            ->postJson("/api/tareas/{$tarea->id}/incidencia")
            ->assertOk()
            ->assertJsonPath('data.estado', 'incidencia');

        $this->assertDatabaseHas('stock', ['lote_id' => $tarea->lote_id, 'cantidad_reservada' => 0]);
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_produccion_no_accede_a_las_tareas(): void
    {
        $tarea = $this->tarea(secuencia: 1);
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);

        $this->actingAs($produccion)->getJson('/api/tareas')->assertForbidden();
        $this->actingAs($produccion)->postJson("/api/tareas/{$tarea->id}/confirmar", $this->codigos($tarea))->assertForbidden();
        $this->actingAs($produccion)->postJson("/api/tareas/{$tarea->id}/incidencia")->assertForbidden();
    }
}
