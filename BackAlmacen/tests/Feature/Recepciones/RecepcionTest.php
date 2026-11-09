<?php

namespace Tests\Feature\Recepciones;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecepcionTest extends TestCase
{
    use RefreshDatabase;

    private function recepcionista(): User
    {
        return User::factory()->create(['rol' => Rol::OperadorRecepcion]);
    }

    public function test_registra_la_entrada_creando_el_lote_y_el_movimiento(): void
    {
        $articulo = Articulo::factory()->create(['tiene_caducidad' => true]);
        $ubicacion = Ubicacion::factory()->create(['codigo' => 'P1-E01-N1', 'capacidad' => 100, 'max_lotes' => 1]);

        $this->actingAs($this->recepcionista())
            ->postJson('/api/recepciones', [
                'articulo_id' => $articulo->id,
                'codigo_lote' => 'L-0001',
                'fecha_recepcion' => '2026-11-05',
                'caducidad' => '2027-01-01',
                'cantidad' => 40,
                'ubicacion_id' => $ubicacion->id,
            ])
            ->assertCreated()
            ->assertJsonPath('lote.codigo_lote', 'L-0001')
            ->assertJsonPath('ubicacion', 'P1-E01-N1');

        $this->assertDatabaseHas('lotes', ['articulo_id' => $articulo->id, 'codigo_lote' => 'L-0001']);
        $this->assertDatabaseHas('stock', ['ubicacion_id' => $ubicacion->id, 'cantidad' => 40]);
        $this->assertDatabaseHas('movimientos', ['tipo' => 'recepcion', 'cantidad' => 40, 'ubicacion_destino_id' => $ubicacion->id]);
    }

    public function test_propone_una_ubicacion_libre_con_capacidad(): void
    {
        Ubicacion::factory()->create(['codigo' => 'P1-E01-N1', 'pasillo' => 1, 'estanteria' => 1, 'nivel' => 1, 'capacidad' => 100]);

        $this->actingAs($this->recepcionista())
            ->getJson('/api/recepciones/ubicacion-propuesta?cantidad=10')
            ->assertOk()
            ->assertJsonPath('ubicacion', 'P1-E01-N1');
    }

    public function test_si_no_se_indica_ubicacion_el_sistema_propone_una(): void
    {
        $articulo = Articulo::factory()->sinCaducidad()->create();
        Ubicacion::factory()->create(['codigo' => 'P1-E01-N1', 'pasillo' => 1, 'estanteria' => 1, 'nivel' => 1, 'capacidad' => 100]);

        $this->actingAs($this->recepcionista())
            ->postJson('/api/recepciones', [
                'articulo_id' => $articulo->id,
                'codigo_lote' => 'L-0002',
                'fecha_recepcion' => '2026-11-05',
                'cantidad' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('ubicacion', 'P1-E01-N1');
    }

    public function test_rechaza_la_entrada_si_la_ubicacion_no_tiene_capacidad(): void
    {
        $articulo = Articulo::factory()->sinCaducidad()->create();
        $ubicacion = Ubicacion::factory()->create(['capacidad' => 10, 'max_lotes' => 5]);

        $this->actingAs($this->recepcionista())
            ->postJson('/api/recepciones', [
                'articulo_id' => $articulo->id,
                'codigo_lote' => 'L-0003',
                'fecha_recepcion' => '2026-11-05',
                'cantidad' => 11,
                'ubicacion_id' => $ubicacion->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_exige_caducidad_cuando_el_articulo_la_tiene(): void
    {
        $articulo = Articulo::factory()->create(['tiene_caducidad' => true]);
        Ubicacion::factory()->create(['capacidad' => 100]);

        $this->actingAs($this->recepcionista())
            ->postJson('/api/recepciones', [
                'articulo_id' => $articulo->id,
                'codigo_lote' => 'L-0004',
                'fecha_recepcion' => '2026-11-05',
                'cantidad' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caducidad');
    }

    public function test_no_admite_caducidad_cuando_el_articulo_no_la_tiene(): void
    {
        $articulo = Articulo::factory()->sinCaducidad()->create();
        Ubicacion::factory()->create(['capacidad' => 100]);

        $this->actingAs($this->recepcionista())
            ->postJson('/api/recepciones', [
                'articulo_id' => $articulo->id,
                'codigo_lote' => 'L-0005',
                'fecha_recepcion' => '2026-11-05',
                'caducidad' => '2027-01-01',
                'cantidad' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caducidad');
    }
}
