<?php

namespace Tests\Feature;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\RegistroMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una ubicación no admite más lotes que su máximo ni más cantidad que su
 * capacidad.
 *
 * Rojo hasta el paso 4 (depende de RegistroMovimiento).
 */
class LimitesUbicacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_ubicacion_no_admite_mas_cantidad_que_su_capacidad(): void
    {
        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 10, 'max_lotes' => 5]);
        $lote = Lote::factory()->create();

        try {
            (new RegistroMovimiento)->registrar(
                TipoMovimiento::Recepcion,
                $lote,
                11,
                $user,
                destino: $ubicacion,
            );
            $this->fail('Se esperaba una ReglaDeDominioException por exceso de capacidad.');
        } catch (ReglaDeDominioException) {
            // Comportamiento esperado.
        }

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_una_ubicacion_no_admite_mas_lotes_que_su_maximo(): void
    {
        $user = User::factory()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 1]);
        $servicio = new RegistroMovimiento;

        $primero = Lote::factory()->create();
        $servicio->registrar(TipoMovimiento::Recepcion, $primero, 5, $user, destino: $ubicacion);

        // Un segundo lote distinto en la misma ubicación supera max_lotes = 1.
        $segundo = Lote::factory()->create();

        try {
            $servicio->registrar(TipoMovimiento::Recepcion, $segundo, 5, $user, destino: $ubicacion);
            $this->fail('Se esperaba una ReglaDeDominioException por exceso de lotes.');
        } catch (ReglaDeDominioException) {
            // Comportamiento esperado.
        }

        // Solo el primer lote quedó ubicado.
        $this->assertSame(1, Movimiento::query()->count());
    }
}
