<?php

namespace Tests\Feature\Inventario;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\RegularizarStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegularizarStockTest extends TestCase
{
    use RefreshDatabase;

    private function saldo(float $cantidad, float $reservada = 0): array
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => $reservada,
        ]);

        return [$lote, $ubicacion];
    }

    public function test_ajuste_a_la_baja_corrige_el_saldo_y_registra_el_movimiento(): void
    {
        [$lote, $ubicacion] = $this->saldo(10);

        (new RegularizarStock)->regularizar($lote, $ubicacion, 7, User::factory()->create(), 'rotura');

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 7]);
        $this->assertDatabaseHas('movimientos', [
            'tipo' => 'ajuste',
            'lote_id' => $lote->id,
            'cantidad' => 3,
            'ubicacion_origen_id' => $ubicacion->id,
            'motivo' => 'rotura',
        ]);
    }

    public function test_ajuste_al_alza_corrige_el_saldo(): void
    {
        [$lote, $ubicacion] = $this->saldo(10);

        (new RegularizarStock)->regularizar($lote, $ubicacion, 14, User::factory()->create(), 'recuento');

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad' => 14]);
        $this->assertDatabaseHas('movimientos', [
            'tipo' => 'ajuste',
            'cantidad' => 4,
            'ubicacion_destino_id' => $ubicacion->id,
        ]);
    }

    public function test_no_deja_la_cantidad_por_debajo_de_lo_reservado(): void
    {
        [$lote, $ubicacion] = $this->saldo(10, 6);

        try {
            (new RegularizarStock)->regularizar($lote, $ubicacion, 4, User::factory()->create(), 'recuento');
            $this->fail('Se esperaba una ReglaDeDominioException.');
        } catch (ReglaDeDominioException) {
            // esperado
        }

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad' => 10]);
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_rechaza_si_no_hay_stock_de_ese_lote_en_esa_ubicacion(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();

        $this->expectException(ReglaDeDominioException::class);

        (new RegularizarStock)->regularizar($lote, $ubicacion, 5, User::factory()->create(), 'recuento');
    }

    public function test_el_ajuste_es_un_movimiento_de_tipo_ajuste(): void
    {
        [$lote, $ubicacion] = $this->saldo(10);

        $movimiento = (new RegularizarStock)->regularizar($lote, $ubicacion, 8, User::factory()->create(), 'recuento');

        $this->assertSame(TipoMovimiento::Ajuste, $movimiento->tipo);
    }
}
