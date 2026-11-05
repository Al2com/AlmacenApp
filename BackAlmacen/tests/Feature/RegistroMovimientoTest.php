<?php

namespace Tests\Feature;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\RegistroMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas del registro de movimientos y su efecto sobre el saldo.
 *
 * Rojo hasta el paso 4 (depende de RegistroMovimiento).
 */
class RegistroMovimientoTest extends TestCase
{
    use RefreshDatabase;

    private function stockEn(Ubicacion $ubicacion, Lote $lote, float $cantidad): Stock
    {
        return Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => 0,
        ]);
    }

    public function test_un_consumo_descuenta_el_saldo_en_la_misma_operacion(): void
    {
        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->create();
        $this->stockEn($ubicacion, $lote, 10);

        (new RegistroMovimiento)->registrar(
            TipoMovimiento::Consumo,
            $lote,
            4,
            $user,
            origen: $ubicacion,
        );

        $this->assertDatabaseHas('stock', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 6,
        ]);
        $this->assertDatabaseHas('movimientos', [
            'lote_id' => $lote->id,
            'tipo' => 'consumo',
            'cantidad' => 4,
            'user_id' => $user->id,
        ]);
    }

    public function test_el_movimiento_y_el_saldo_se_guardan_juntos_o_no_se_guarda_nada(): void
    {
        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->create();
        $this->stockEn($ubicacion, $lote, 3);

        // Un consumo mayor que el saldo no puede dejar el stock en negativo.
        try {
            (new RegistroMovimiento)->registrar(
                TipoMovimiento::Consumo,
                $lote,
                5,
                $user,
                origen: $ubicacion,
            );
            $this->fail('Se esperaba una ReglaDeDominioException.');
        } catch (ReglaDeDominioException) {
            // Comportamiento esperado.
        }

        // Ni el movimiento ni el cambio de saldo deben haberse guardado.
        $this->assertDatabaseCount('movimientos', 0);
        $this->assertDatabaseHas('stock', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 3,
        ]);
    }

    public function test_consumos_concurrentes_no_dejan_el_stock_en_negativo(): void
    {
        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->create();
        $this->stockEn($ubicacion, $lote, 10);

        $servicio = new RegistroMovimiento;

        // Dos consumos de 7 sobre un saldo de 10: solo uno puede cumplirse.
        $servicio->registrar(TipoMovimiento::Consumo, $lote, 7, $user, origen: $ubicacion);

        try {
            $servicio->registrar(TipoMovimiento::Consumo, $lote, 7, $user, origen: $ubicacion);
            $this->fail('Se esperaba una ReglaDeDominioException en el segundo consumo.');
        } catch (ReglaDeDominioException) {
            // Comportamiento esperado.
        }

        // El saldo queda coherente (10 - 7) y solo hay un consumo registrado.
        $this->assertDatabaseHas('stock', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 3,
        ]);
        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_no_permite_consumir_un_lote_caducado(): void
    {
        $this->travelTo('2026-11-05');

        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->create(['caducidad' => '2026-11-01']);
        $this->stockEn($ubicacion, $lote, 10);

        try {
            (new RegistroMovimiento)->registrar(
                TipoMovimiento::Consumo,
                $lote,
                2,
                $user,
                origen: $ubicacion,
            );
            $this->fail('Se esperaba una ReglaDeDominioException al consumir un lote caducado.');
        } catch (ReglaDeDominioException) {
            // Comportamiento esperado.
        }

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_un_lote_caducado_se_puede_dar_de_baja(): void
    {
        $this->travelTo('2026-11-05');

        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->create(['caducidad' => '2026-11-01']);
        $this->stockEn($ubicacion, $lote, 10);

        (new RegistroMovimiento)->registrar(
            TipoMovimiento::Baja,
            $lote,
            10,
            $user,
            origen: $ubicacion,
            motivo: 'caducado',
        );

        $this->assertDatabaseHas('stock', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 0,
        ]);
        $this->assertDatabaseHas('movimientos', [
            'lote_id' => $lote->id,
            'tipo' => 'baja',
        ]);
    }
}
