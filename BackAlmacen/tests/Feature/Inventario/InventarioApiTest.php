<?php

namespace Tests\Feature\Inventario;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioApiTest extends TestCase
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

    private function saldo(Lote $lote, Ubicacion $ubicacion, float $cantidad, float $reservada = 0): void
    {
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => $reservada,
        ]);
    }

    public function test_el_gestor_regulariza_el_stock(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        $this->saldo($lote, $ubicacion, 10);

        $this->actingAs($this->gestor())
            ->postJson('/api/inventario/ajustes', [
                'lote_id' => $lote->id,
                'ubicacion_id' => $ubicacion->id,
                'cantidad' => 7,
                'motivo' => 'rotura',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'ajuste');

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad' => 7]);
    }

    public function test_el_ajuste_exige_motivo(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        $this->saldo($lote, $ubicacion, 10);

        $this->actingAs($this->gestor())
            ->postJson('/api/inventario/ajustes', [
                'lote_id' => $lote->id,
                'ubicacion_id' => $ubicacion->id,
                'cantidad' => 7,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_el_gestor_traslada_stock(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $origen = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $destino = Ubicacion::factory()->create(['capacidad' => 100, 'max_lotes' => 5]);
        $this->saldo($lote, $origen, 10);

        $this->actingAs($this->gestor())
            ->postJson('/api/inventario/traslados', [
                'lote_id' => $lote->id,
                'origen_id' => $origen->id,
                'destino_id' => $destino->id,
                'cantidad' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'traslado');

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'ubicacion_id' => $destino->id, 'cantidad' => 4]);
    }

    public function test_el_gestor_da_de_baja_un_caducado(): void
    {
        $lote = Lote::factory()->create(['caducidad' => '2026-10-20']);
        $ubicacion = Ubicacion::factory()->create();
        $this->saldo($lote, $ubicacion, 10);

        $this->actingAs($this->gestor())
            ->postJson('/api/inventario/bajas', [
                'lote_id' => $lote->id,
                'ubicacion_id' => $ubicacion->id,
                'cantidad' => 10,
                'motivo' => 'caducado',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'baja');

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad' => 0]);
    }

    public function test_el_gestor_reserva_y_libera(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $this->saldo($lote, Ubicacion::factory()->create(), 10);
        $gestor = $this->gestor();

        $this->actingAs($gestor)
            ->postJson('/api/inventario/reservas', ['articulo_id' => $articulo->id, 'cantidad' => 8])
            ->assertOk()
            ->assertJsonPath('reservado', 8)
            ->assertJsonPath('faltante', 0);

        $this->actingAs($gestor)
            ->postJson('/api/inventario/reservas/liberar', ['articulo_id' => $articulo->id, 'cantidad' => 3])
            ->assertOk()
            ->assertJsonPath('liberado', 3);

        $this->assertDatabaseHas('stock', ['lote_id' => $lote->id, 'cantidad_reservada' => 5]);
    }
}
