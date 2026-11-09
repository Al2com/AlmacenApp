<?php

namespace Tests\Feature\Panel;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\ConfirmarTarea;
use App\Services\LanzarOrden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estados de una posición más allá de libre/ocupada/caducada: reservada para
 * una orden, en picking (la siguiente tarea de su orden) y recogida (justo
 * tras confirmar), con el detalle de para qué orden está reservada cada cosa.
 */
class EstadoPosicionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');
    }

    private function gestor(): User
    {
        return User::factory()->create(['rol' => Rol::GestorInventario]);
    }

    private function estanteria(): array
    {
        return $this->actingAs($this->gestor())->getJson('/api/panel/estanteria')->assertOk()->json();
    }

    private function estadoDe(array $posiciones, string $codigo): string
    {
        return collect($posiciones)->firstWhere('codigo', $codigo)['estado'];
    }

    public function test_la_primera_tarea_pendiente_de_una_orden_esta_en_picking_el_resto_reservada(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $primera = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 1, 'nivel' => 1]);
        $segunda = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 2, 'nivel' => 1]);
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $primera->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $segunda->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-9', [
            ['articulo' => $articulo, 'cantidad' => 20],
        ]);

        $posiciones = $this->estanteria();

        $this->assertSame('en_picking', $this->estadoDe($posiciones, $primera->codigo));
        $this->assertSame('reservada', $this->estadoDe($posiciones, $segunda->codigo));
    }

    public function test_el_contenido_dice_para_que_orden_esta_reservado(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-10', [
            ['articulo' => $articulo, 'cantidad' => 6],
        ]);

        $posiciones = $this->estanteria();
        $contenido = collect($posiciones)->firstWhere('codigo', $ubicacion->codigo)['contenido'][0];

        $this->assertSame('OP-10', $contenido['reservas'][0]['orden']);
        $this->assertSame(6.0, (float) $contenido['reservas'][0]['cantidad']);
    }

    public function test_una_posicion_queda_recogida_justo_al_confirmar(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);
        $almacen = User::factory()->create(['rol' => Rol::OperadorAlmacen]);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-11', [
            ['articulo' => $articulo, 'cantidad' => 10],
        ]);
        $tarea = TareaPicking::query()->first();
        (new ConfirmarTarea)->confirmar($tarea, $almacen);

        $posiciones = $this->estanteria();

        $this->assertSame('recogida', $this->estadoDe($posiciones, $ubicacion->codigo));
    }

    public function test_la_recogida_desaparece_pasada_la_ventana(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);
        $almacen = User::factory()->create(['rol' => Rol::OperadorAlmacen]);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-12', [
            ['articulo' => $articulo, 'cantidad' => 10],
        ]);
        $tarea = TareaPicking::query()->first();
        (new ConfirmarTarea)->confirmar($tarea, $almacen);

        $this->travelTo('2026-11-05 09:05:00');

        $posiciones = $this->estanteria();

        // Sin stock (se consumió todo) y pasada la ventana: vuelve a libre.
        $this->assertSame('libre', $this->estadoDe($posiciones, $ubicacion->codigo));
    }

    public function test_una_reserva_manual_sin_orden_sigue_marcando_reservada(): void
    {
        // Las reservas de inventario (sin orden) ya funcionaban así; no se rompen.
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 10, 'cantidad_reservada' => 4]);

        $posiciones = $this->estanteria();

        $this->assertSame('reservada', $this->estadoDe($posiciones, $ubicacion->codigo));
    }
}
