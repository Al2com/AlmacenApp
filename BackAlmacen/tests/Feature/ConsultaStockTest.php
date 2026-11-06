<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultaStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05');
    }

    private function stock(Articulo $articulo, string $caducidad): void
    {
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => $caducidad]);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'cantidad' => 10,
            'cantidad_reservada' => 0,
        ]);
    }

    public function test_lista_el_stock_con_su_estado_de_frescura(): void
    {
        $articulo = Articulo::factory()->create();
        $this->stock($articulo, '2026-10-20'); // caducado
        $this->stock($articulo, '2026-11-09'); // caduca pronto
        $this->stock($articulo, '2027-05-01'); // ok

        $filas = $this->actingAs(User::factory()->create(['rol' => Rol::OperadorAlmacen]))
            ->getJson('/api/stock')
            ->assertOk()
            ->json();

        $estados = array_column($filas, 'estado');
        $this->assertContains('caducado', $estados);
        $this->assertContains('caduca_pronto', $estados);
        $this->assertContains('ok', $estados);
    }

    public function test_filtra_por_ubicacion(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-05-01']);
        $aqui = Ubicacion::factory()->create();
        $alla = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $aqui->id, 'cantidad' => 5, 'cantidad_reservada' => 0]);
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $alla->id, 'cantidad' => 8, 'cantidad_reservada' => 0]);

        $filas = $this->actingAs(User::factory()->create(['rol' => Rol::OperadorAlmacen]))
            ->getJson("/api/stock?ubicacion_id={$aqui->id}")
            ->assertOk()
            ->json();

        $this->assertCount(1, $filas);
        $this->assertSame($aqui->codigo, $filas[0]['ubicacion']);
    }

    public function test_detalle_de_una_posicion(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-05-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 9, 'cantidad_reservada' => 2]);

        $this->actingAs(User::factory()->create(['rol' => Rol::OperadorAlmacen]))
            ->getJson("/api/stock/posicion/{$ubicacion->id}")
            ->assertOk()
            ->assertJsonPath('codigo', $ubicacion->codigo)
            ->assertJsonPath('contenido.0.disponible', 7)
            ->assertJsonPath('contenido.0.reservada', 2);
    }

    public function test_filtra_por_estado(): void
    {
        $articulo = Articulo::factory()->create();
        $this->stock($articulo, '2026-10-20'); // caducado
        $this->stock($articulo, '2027-05-01'); // ok

        $filas = $this->actingAs(User::factory()->create(['rol' => Rol::Admin]))
            ->getJson('/api/stock?estado=caducado')
            ->assertOk()
            ->json();

        $this->assertCount(1, $filas);
        $this->assertSame('caducado', $filas[0]['estado']);
    }
}
