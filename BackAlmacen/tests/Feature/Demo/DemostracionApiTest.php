<?php

namespace Tests\Feature\Demo;

use App\Enums\EstadoTarea;
use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\LanzarOrden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemostracionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => Rol::Admin]);
    }

    private function lanzarPedido(string $codigo): void
    {
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'cantidad' => 10,
            'cantidad_reservada' => 0,
        ]);
        (new LanzarOrden)->lanzar($produccion, $codigo, [['articulo' => $articulo, 'cantidad' => 5]]);
    }

    public function test_el_admin_obtiene_la_ruta_del_ultimo_pedido(): void
    {
        $this->lanzarPedido('OP-UNO');
        $this->travelTo('2026-11-05 09:05:00');
        $this->lanzarPedido('OP-DOS');

        $this->actingAs($this->admin())
            ->getJson('/api/demostracion/ruta')
            ->assertOk()
            ->assertJsonPath('orden', 'OP-DOS')
            ->assertJsonPath('tareas.0.orden', 'OP-DOS');
    }

    public function test_sin_pedidos_la_ruta_viene_vacia(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/demostracion/ruta')
            ->assertOk()
            ->assertJsonPath('orden', null)
            ->assertJsonPath('tareas', []);
    }

    public function test_la_ruta_no_devuelve_tareas_ya_confirmadas(): void
    {
        $this->lanzarPedido('OP-MIXTA');
        $tarea = TareaPicking::firstOrFail();
        $tarea->forceFill(['estado' => EstadoTarea::Confirmada])->save();

        $this->actingAs($this->admin())
            ->getJson('/api/demostracion/ruta')
            ->assertOk()
            ->assertJsonPath('tareas', []);
    }

    public function test_confirmar_una_tarea_de_la_demo_genera_un_movimiento_real(): void
    {
        $this->lanzarPedido('OP-CONSUMO');
        $tarea = TareaPicking::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/api/demostracion/confirmar/{$tarea->id}")
            ->assertOk();

        // Movimiento de consumo real, a nombre del usuario que lanza la demo.
        $this->assertDatabaseHas('movimientos', [
            'tipo' => 'consumo',
            'cantidad' => 5,
            'user_id' => $admin->id,
            'tarea_picking_id' => $tarea->id,
        ]);
        // Stock descontado y reserva liberada.
        $this->assertSame(5.0, (float) Stock::sum('cantidad'));
        $this->assertSame(0.0, (float) Stock::sum('cantidad_reservada'));
        $this->assertSame(EstadoTarea::Confirmada, $tarea->refresh()->estado);
    }

    public function test_no_se_confirma_dos_veces_la_misma_tarea(): void
    {
        $this->lanzarPedido('OP-DOBLE');
        $tarea = TareaPicking::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/demostracion/confirmar/{$tarea->id}")->assertOk();
        $this->actingAs($admin)->postJson("/api/demostracion/confirmar/{$tarea->id}")->assertStatus(422);
    }

    public function test_un_rol_sin_permiso_no_confirma_la_demo(): void
    {
        $this->lanzarPedido('OP-SINPERMISO');
        $tarea = TareaPicking::firstOrFail();
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);

        $this->actingAs($produccion)
            ->postJson("/api/demostracion/confirmar/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_no_se_confirma_la_demo_en_produccion(): void
    {
        $this->lanzarPedido('OP-PROD');
        $tarea = TareaPicking::firstOrFail();

        $this->app['env'] = 'production';
        $respuesta = $this->actingAs($this->admin())
            ->postJson("/api/demostracion/confirmar/{$tarea->id}");
        $this->app['env'] = 'testing';

        $respuesta->assertForbidden();
    }
}
