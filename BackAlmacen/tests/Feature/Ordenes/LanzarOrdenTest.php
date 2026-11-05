<?php

namespace Tests\Feature\Ordenes;

use App\Enums\EstadoOrden;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\LanzarOrden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas al lanzar una orden: reserva por FEFO, sin sobre-reservar, saltando
 * caducados, con preparación parcial si falta stock y tareas ordenadas por ruta.
 *
 * Rojo hasta el paso 3 (depende de LanzarOrden).
 */
class LanzarOrdenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05');
    }

    private function stock(Ubicacion $ubicacion, Lote $lote, float $cantidad, float $reservada = 0): void
    {
        Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => $cantidad,
            'cantidad_reservada' => $reservada,
        ]);
    }

    public function test_reserva_los_lotes_por_fefo(): void
    {
        $articulo = Articulo::factory()->create();
        $pronto = Lote::factory()->for($articulo)->create(['caducidad' => '2026-11-20']);
        $tarde = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $this->stock(Ubicacion::factory()->create(), $pronto, 10);
        $this->stock(Ubicacion::factory()->create(), $tarde, 10);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-1', [
            ['articulo' => $articulo, 'cantidad' => 8],
        ]);

        // Se reserva del lote que caduca antes y no se toca el otro.
        $this->assertDatabaseHas('stock', ['lote_id' => $pronto->id, 'cantidad_reservada' => 8]);
        $this->assertDatabaseHas('stock', ['lote_id' => $tarde->id, 'cantidad_reservada' => 0]);
        $this->assertDatabaseHas('tareas_picking', ['lote_id' => $pronto->id, 'cantidad' => 8]);
    }

    public function test_no_reserva_mas_de_lo_disponible(): void
    {
        $articulo = Articulo::factory()->create();
        $pronto = Lote::factory()->for($articulo)->create(['caducidad' => '2026-11-20']);
        $tarde = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $this->stock(Ubicacion::factory()->create(), $pronto, 10);
        $this->stock(Ubicacion::factory()->create(), $tarde, 10);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-2', [
            ['articulo' => $articulo, 'cantidad' => 15],
        ]);

        $this->assertDatabaseHas('stock', ['lote_id' => $pronto->id, 'cantidad_reservada' => 10]);
        $this->assertDatabaseHas('stock', ['lote_id' => $tarde->id, 'cantidad_reservada' => 5]);
    }

    public function test_no_reserva_lotes_caducados(): void
    {
        $articulo = Articulo::factory()->create();
        $caducado = Lote::factory()->for($articulo)->create(['caducidad' => '2026-10-20']);
        $valido = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $this->stock(Ubicacion::factory()->create(), $caducado, 10);
        $this->stock(Ubicacion::factory()->create(), $valido, 10);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-3', [
            ['articulo' => $articulo, 'cantidad' => 6],
        ]);

        // El caducado no se reserva: la tarea sale del lote válido.
        $this->assertDatabaseHas('stock', ['lote_id' => $caducado->id, 'cantidad_reservada' => 0]);
        $this->assertDatabaseHas('stock', ['lote_id' => $valido->id, 'cantidad_reservada' => 6]);
    }

    public function test_preparacion_parcial_cuando_falta_stock(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $this->stock(Ubicacion::factory()->create(), $lote, 10);

        $orden = (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-4', [
            ['articulo' => $articulo, 'cantidad' => 15],
        ]);

        // Se reserva lo que hay (10) y la orden no queda preparada: falta material.
        $this->assertSame(10.0, (float) TareaPicking::query()->sum('cantidad'));
        $this->assertNotSame(EstadoOrden::Preparada, $orden->refresh()->estado);
    }

    public function test_las_tareas_van_ordenadas_por_ruta(): void
    {
        $articulo = Articulo::factory()->create();
        // El lote que caduca antes está en un pasillo posterior; la ruta manda en la secuencia.
        $pronto = Lote::factory()->for($articulo)->create(['caducidad' => '2026-11-20']);
        $tarde = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        $lejos = Ubicacion::factory()->create(['pasillo' => 3, 'estanteria' => 5, 'nivel' => 2]);
        $cerca = Ubicacion::factory()->create(['pasillo' => 1, 'estanteria' => 1, 'nivel' => 1]);
        $this->stock($lejos, $pronto, 10);
        $this->stock($cerca, $tarde, 10);

        (new LanzarOrden)->lanzar(User::factory()->create(), 'OP-5', [
            ['articulo' => $articulo, 'cantidad' => 15],
        ]);

        $secuenciaCerca = TareaPicking::query()->where('ubicacion_id', $cerca->id)->value('secuencia');
        $secuenciaLejos = TareaPicking::query()->where('ubicacion_id', $lejos->id)->value('secuencia');

        $this->assertLessThan($secuenciaLejos, $secuenciaCerca);
    }
}
