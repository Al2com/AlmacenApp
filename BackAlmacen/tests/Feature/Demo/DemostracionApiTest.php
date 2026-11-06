<?php

namespace Tests\Feature\Demo;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
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

    private function lanzarPedido(): void
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
        (new LanzarOrden)->lanzar($produccion, 'OP-DEMO', [['articulo' => $articulo, 'cantidad' => 5]]);
    }

    public function test_el_admin_ve_el_estado_de_la_demostracion(): void
    {
        $this->lanzarPedido();

        $this->actingAs($this->admin())
            ->getJson('/api/demostracion/estado')
            ->assertOk()
            ->assertJsonPath('tarea.orden', 'OP-DEMO')
            ->assertJsonPath('pendientes', 1);
    }

    public function test_el_admin_confirma_la_siguiente_tarea(): void
    {
        $this->lanzarPedido();

        $this->actingAs($this->admin())
            ->postJson('/api/demostracion/confirmar')
            ->assertOk()
            ->assertJsonPath('confirmada.orden', 'OP-DEMO')
            ->assertJsonPath('siguiente', null)
            ->assertJsonPath('pendientes', 0);

        $this->assertDatabaseHas('movimientos', ['tipo' => 'consumo', 'cantidad' => 5]);
    }

    public function test_sin_tareas_el_estado_no_tiene_nada_que_confirmar(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/demostracion/estado')
            ->assertOk()
            ->assertJsonPath('tarea', null);
    }
}
