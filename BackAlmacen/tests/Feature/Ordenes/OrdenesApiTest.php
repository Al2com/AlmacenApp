<?php

namespace Tests\Feature\Ordenes;

use App\Enums\EstadoOrden;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdenesApiTest extends TestCase
{
    use RefreshDatabase;

    private function produccion(): User
    {
        return User::factory()->create(['rol' => Rol::OperadorProduccion]);
    }

    private function articuloConStock(float $cantidad): Articulo
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => 0,
        ]);

        return $articulo;
    }

    public function test_produccion_lanza_una_orden_y_se_generan_tareas(): void
    {
        $articulo = $this->articuloConStock(50);

        $this->actingAs($this->produccion())
            ->postJson('/api/ordenes', [
                'nombre' => 'Línea 2',
                'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 20]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Línea 2')
            ->assertJsonPath('data.estado', 'lanzada');

        $this->assertDatabaseHas('tareas_picking', ['cantidad' => 20]);
        $this->assertDatabaseHas('stock', ['cantidad_reservada' => 20]);
    }

    public function test_el_codigo_de_la_orden_se_genera_solo(): void
    {
        $articulo = $this->articuloConStock(50);

        $codigo = $this->actingAs($this->produccion())
            ->postJson('/api/ordenes', [
                'nombre' => 'Primera',
                'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 5]],
            ])
            ->assertCreated()
            ->json('data.codigo');

        $this->assertMatchesRegularExpression('/^OP-\d{4}$/', $codigo);
    }

    public function test_los_codigos_generados_no_se_repiten(): void
    {
        $articulo = $this->articuloConStock(50);
        $produccion = $this->produccion();

        $crear = fn (string $nombre) => $this->actingAs($produccion)
            ->postJson('/api/ordenes', [
                'nombre' => $nombre,
                'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 5]],
            ])
            ->json('data.codigo');

        $this->assertNotSame($crear('Una'), $crear('Otra'));
    }

    public function test_el_nombre_de_la_orden_es_obligatorio(): void
    {
        $articulo = $this->articuloConStock(50);

        $this->actingAs($this->produccion())
            ->postJson('/api/ordenes', [
                'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 5]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_cada_produccion_solo_ve_sus_ordenes(): void
    {
        $mias = $this->produccion();
        $otras = $this->produccion();
        OrdenProduccion::factory()->create(['user_id' => $mias->id]);
        OrdenProduccion::factory()->create(['user_id' => $otras->id]);

        $this->actingAs($mias)
            ->getJson('/api/ordenes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_un_operador_no_puede_ver_la_orden_de_otro(): void
    {
        $ajena = OrdenProduccion::factory()->create(['user_id' => $this->produccion()->id]);

        $this->actingAs($this->produccion())
            ->getJson("/api/ordenes/{$ajena->id}")
            ->assertForbidden();
    }

    public function test_un_operador_no_puede_cancelar_la_orden_de_otro(): void
    {
        $ajena = OrdenProduccion::factory()->create([
            'user_id' => $this->produccion()->id,
            'estado' => EstadoOrden::Lanzada,
        ]);

        $this->actingAs($this->produccion())
            ->postJson("/api/ordenes/{$ajena->id}/cancelar")
            ->assertForbidden();

        $this->assertSame(EstadoOrden::Lanzada, $ajena->refresh()->estado);
    }

    public function test_cancelar_libera_las_reservas(): void
    {
        $produccion = $this->produccion();
        $articulo = $this->articuloConStock(50);

        $orden = $this->actingAs($produccion)->postJson('/api/ordenes', [
            'nombre' => 'Para cancelar',
            'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 20]],
        ])->json('data.id');

        $this->actingAs($produccion)->postJson("/api/ordenes/{$orden}/cancelar")->assertOk();

        $this->assertDatabaseHas('ordenes_produccion', ['id' => $orden, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('stock', ['cantidad_reservada' => 0]);
        $this->assertDatabaseCount('tareas_picking', 0);
    }

    public function test_solo_se_entrega_una_orden_preparada(): void
    {
        $produccion = $this->produccion();
        $orden = OrdenProduccion::factory()->create([
            'user_id' => $produccion->id,
            'estado' => EstadoOrden::Lanzada,
        ]);

        // Lanzada todavía no se puede entregar.
        $this->actingAs($produccion)->postJson("/api/ordenes/{$orden->id}/entregar")->assertStatus(422);

        $orden->forceFill(['estado' => EstadoOrden::Preparada])->save();
        $this->actingAs($produccion)->postJson("/api/ordenes/{$orden->id}/entregar")->assertOk();
        $this->assertSame(EstadoOrden::Entregada, $orden->refresh()->estado);
    }

    public function test_borrar_una_orden_lanzada_la_elimina(): void
    {
        $produccion = $this->produccion();
        $articulo = $this->articuloConStock(50);

        $orden = $this->actingAs($produccion)->postJson('/api/ordenes', [
            'nombre' => 'Para borrar',
            'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 20]],
        ])->json('data.id');

        $this->actingAs($produccion)->deleteJson("/api/ordenes/{$orden}")->assertOk();

        $this->assertDatabaseMissing('ordenes_produccion', ['id' => $orden]);
        $this->assertDatabaseHas('stock', ['cantidad_reservada' => 0]);
    }

    public function test_un_operador_no_puede_borrar_la_orden_de_otro(): void
    {
        $ajena = OrdenProduccion::factory()->create([
            'user_id' => $this->produccion()->id,
            'estado' => EstadoOrden::Lanzada,
        ]);

        $this->actingAs($this->produccion())
            ->deleteJson("/api/ordenes/{$ajena->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('ordenes_produccion', ['id' => $ajena->id]);
    }

    public function test_no_se_borra_una_orden_en_picking(): void
    {
        $produccion = $this->produccion();
        $orden = OrdenProduccion::factory()->create([
            'user_id' => $produccion->id,
            'estado' => EstadoOrden::EnPicking,
        ]);

        $this->actingAs($produccion)->deleteJson("/api/ordenes/{$orden->id}")->assertStatus(422);
        $this->assertDatabaseHas('ordenes_produccion', ['id' => $orden->id]);
    }
}
