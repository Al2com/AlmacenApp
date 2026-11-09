<?php

namespace Tests\Feature;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\RegistroMovimiento;
use App\Support\FechaSimulada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La caducidad se decide contra la fecha simulada, no contra el reloj real:
 * adelantar «hoy» convierte un lote válido en caducado.
 */
class CaducidadConFechaSimuladaTest extends TestCase
{
    use RefreshDatabase;

    public function test_adelantar_la_fecha_simulada_convierte_un_lote_en_caducado(): void
    {
        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $lote = Lote::factory()->create(['caducidad' => '2026-11-10']);
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 10,
            'cantidad_reservada' => 0,
        ]);

        $servicio = new RegistroMovimiento;

        // Antes de caducar: el consumo se permite.
        FechaSimulada::fijar(Carbon::parse('2026-11-05'));
        $servicio->registrar(TipoMovimiento::Consumo, $lote, 2, $user, origen: $ubicacion);

        // Adelantamos «hoy» más allá de la caducidad: ahora el consumo se rechaza.
        FechaSimulada::fijar(Carbon::parse('2026-11-15'));

        try {
            $servicio->registrar(TipoMovimiento::Consumo, $lote, 2, $user, origen: $ubicacion);
            $this->fail('Se esperaba una ReglaDeDominioException al consumir un lote ya caducado.');
        } catch (ReglaDeDominioException) {
            // Comportamiento esperado.
        }

        $this->assertDatabaseCount('movimientos', 1);
        $this->assertDatabaseHas('stock', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 8,
        ]);
    }
}
