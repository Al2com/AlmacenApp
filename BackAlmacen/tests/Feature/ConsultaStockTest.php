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
