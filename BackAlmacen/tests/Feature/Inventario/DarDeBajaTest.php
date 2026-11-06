<?php

namespace Tests\Feature\Inventario;

use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\DarDeBaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarDeBajaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05');
    }

    private function saldo(Lote $lote, float $cantidad, float $reservada = 0): Ubicacion
    {
        $ubicacion = Ubicacion::factory()->create();
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => $reservada,
        ]);

        return $ubicacion;
    }

    public function test_da_de_baja_un_lote_caducado(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2026-10-20']);
        $ubicacion = $this->saldo($lote, 10);

        (new DarDeBaja)->baja($lote, $ubicacion, 10, User::factory()->create(), 'caducado');

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 0]);
        $this->assertDatabaseHas('movimientos', ['tipo' => 'baja', 'lote_id' => $lote->id, 'cantidad' => 10, 'motivo' => 'caducado']);
    }

    public function test_no_da_de_baja_mas_de_lo_disponible(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $ubicacion = $this->saldo($lote, 10, 7);

        try {
            (new DarDeBaja)->baja($lote, $ubicacion, 5, User::factory()->create(), 'rotura');
            $this->fail('Se esperaba una ReglaDeDominioException.');
        } catch (ReglaDeDominioException) {
            // esperado
        }

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad' => 10]);
        $this->assertDatabaseCount('movimientos', 0);
    }
}
