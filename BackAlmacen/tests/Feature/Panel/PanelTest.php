<?php

namespace Tests\Feature\Panel;

use App\Enums\EstadoOrden;
use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\RegistroMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05');
    }

    private function gestor(): User
    {
        return User::factory()->create(['rol' => Rol::GestorInventario]);
    }

    private function stockEn(Ubicacion $ubicacion, Lote $lote, float $cantidad, float $reservada = 0): void
    {
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => $reservada,
        ]);
    }

    public function test_las_cifras_cuentan_los_lotes_que_caducan_pronto(): void
    {
        $articulo = Articulo::factory()->create();
        $ubicacion = Ubicacion::factory()->create();

        $this->stockEn($ubicacion, Lote::factory()->for($articulo)->create(['caducidad' => '2026-11-09']), 10);
        $this->stockEn(Ubicacion::factory()->create(), Lote::factory()->for($articulo)->create(['caducidad' => '2026-12-31']), 5);

        $this->actingAs($this->gestor())
            ->getJson('/api/panel/cifras')
            ->assertOk()
            ->assertJsonPath('lotes_caducan_pronto', 1)
            ->assertJsonPath('ordenes_en_curso', 0);
    }

    public function test_las_cifras_cuentan_ordenes_en_curso_y_tareas_pendientes(): void
    {
        $linea = OrdenLinea::factory()->create([
            'orden_id' => OrdenProduccion::factory()->create(['estado' => EstadoOrden::Lanzada])->id,
        ]);
        TareaPicking::factory()->create(['orden_linea_id' => $linea->id]);

        $this->actingAs($this->gestor())
            ->getJson('/api/panel/cifras')
            ->assertOk()
            ->assertJsonPath('ordenes_en_curso', 1)
            ->assertJsonPath('tareas_pendientes', 1);
    }

    public function test_la_estanteria_marca_el_estado_de_cada_posicion(): void
    {
        $articulo = Articulo::factory()->create();

        $libre = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 1, 'nivel' => 1]);
        $ocupada = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 1, 'nivel' => 2]);
        $pronto = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 1, 'nivel' => 3]);
        $caducada = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 2, 'nivel' => 1]);
        $reservada = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 2, 'nivel' => 2]);

        $this->stockEn($ocupada, Lote::factory()->for($articulo)->create(['caducidad' => '2027-05-01']), 10);
        $this->stockEn($pronto, Lote::factory()->for($articulo)->create(['caducidad' => '2026-11-08']), 10);
        $this->stockEn($caducada, Lote::factory()->for($articulo)->create(['caducidad' => '2026-10-30']), 10);
        $this->stockEn($reservada, Lote::factory()->for($articulo)->create(['caducidad' => '2027-05-01']), 10, 4);

        $respuesta = $this->actingAs($this->gestor())->getJson('/api/panel/estanteria')->assertOk();

        $estados = collect($respuesta->json())->keyBy('codigo')->map(fn ($p) => $p['estado']);

        $this->assertSame('libre', $estados[$libre->codigo]);
        $this->assertSame('ocupada', $estados[$ocupada->codigo]);
        $this->assertSame('caduca_pronto', $estados[$pronto->codigo]);
        $this->assertSame('caducada', $estados[$caducada->codigo]);
        $this->assertSame('reservada', $estados[$reservada->codigo]);
    }

    public function test_el_orden_fefo_pone_el_caducado_primero_y_marcado_para_baja(): void
    {
        $articulo = Articulo::factory()->create();

        $this->stockEn(Ubicacion::factory()->create(), Lote::factory()->for($articulo)->create(['codigo_lote' => 'VALIDO', 'caducidad' => '2027-01-01']), 10);
        $this->stockEn(Ubicacion::factory()->create(), Lote::factory()->for($articulo)->create(['codigo_lote' => 'CADUCADO', 'caducidad' => '2026-10-20']), 10);

        $fefo = $this->actingAs($this->gestor())->getJson('/api/panel/fefo')->assertOk()->json();

        $this->assertSame('CADUCADO', $fefo[0]['lote']);
        $this->assertTrue($fefo[0]['para_baja']);
        $this->assertSame('VALIDO', $fefo[1]['lote']);
        $this->assertFalse($fefo[1]['para_baja']);
    }

    public function test_el_flujo_horario_separa_entradas_y_salidas(): void
    {
        $articulo = Articulo::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-01-01']);
        $user = $this->gestor();
        $servicio = new RegistroMovimiento;

        $servicio->registrar(TipoMovimiento::Recepcion, $lote, 30, $user, destino: $ubicacion);
        $servicio->registrar(TipoMovimiento::Consumo, $lote, 12, $user, origen: $ubicacion);

        $flujo = $this->actingAs($user)->getJson('/api/panel/flujo')->assertOk()->json();

        $entradas = (float) collect($flujo)->sum('entradas');
        $salidas = (float) collect($flujo)->sum('salidas');

        $this->assertSame(30.0, $entradas);
        $this->assertSame(12.0, $salidas);
    }

    public function test_los_ultimos_movimientos_llegan_los_mas_recientes_primero(): void
    {
        $articulo = Articulo::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-01-01']);
        $user = $this->gestor();
        $servicio = new RegistroMovimiento;

        $servicio->registrar(TipoMovimiento::Recepcion, $lote, 30, $user, destino: $ubicacion);
        $this->travelTo('2026-11-05 10:30:00');
        $servicio->registrar(TipoMovimiento::Consumo, $lote, 5, $user, origen: $ubicacion);

        $movimientos = $this->actingAs($user)->getJson('/api/panel/movimientos')->assertOk()->json('data');

        $this->assertSame('consumo', $movimientos[0]['tipo']);
        $this->assertSame('recepcion', $movimientos[1]['tipo']);
    }
}
