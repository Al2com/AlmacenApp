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

    public function test_la_ruta_no_consume_stock(): void
    {
        $this->lanzarPedido('OP-VISUAL');
        $antes = (float) Stock::sum('cantidad');

        $this->actingAs($this->admin())->getJson('/api/demostracion/ruta')->assertOk();

        $this->assertSame($antes, (float) Stock::sum('cantidad'));
    }
}
