<?php

namespace Tests\Feature\Ordenes;

use App\Enums\EstadoOrden;
use App\Enums\Rol;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\BorrarOrden;
use App\Services\LanzarOrden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Borrar una orden la elimina de verdad (orden, líneas y tareas). Solo se puede
 * cuando no ha movido stock de verdad: estando lanzada (se liberan sus reservas
 * antes) o ya cancelada. En picking, preparada o entregada no se borra, porque
 * tiene movimientos inmutables.
 *
 * Rojo hasta que exista BorrarOrden.
 */
class BorrarOrdenTest extends TestCase
{
    use RefreshDatabase;

    private Articulo $articulo;

    private Stock $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');

        $this->articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($this->articulo)->create(['caducidad' => '2027-02-01']);
        $this->saldo = Stock::create([
            'lote_id' => $lote->id,
            'ubicacion_id' => Ubicacion::factory()->create()->id,
            'cantidad' => 10,
            'cantidad_reservada' => 0,
        ]);
    }

    private function lanzar(string $codigo = 'OP-1'): OrdenProduccion
    {
        $produccion = User::factory()->create(['rol' => Rol::OperadorProduccion]);

        return (new LanzarOrden)->lanzar($produccion, $codigo, [
            ['articulo' => $this->articulo, 'cantidad' => 6],
        ]);
    }

    // Stock tiene clave compuesta (lote + ubicación), sin `id`: se relee por su clave.
    private function saldoFresco(): Stock
    {
        return Stock::query()
            ->where('lote_id', $this->saldo->lote_id)
            ->where('ubicacion_id', $this->saldo->ubicacion_id)
            ->firstOrFail();
    }

    public function test_borrar_una_orden_lanzada_libera_sus_reservas_y_la_elimina(): void
    {
        $orden = $this->lanzar();
        $this->assertSame(6.0, (float) $this->saldoFresco()->cantidad_reservada);

        (new BorrarOrden)->borrar($orden);

        $this->assertSame(0.0, (float) $this->saldoFresco()->cantidad_reservada);
        $this->assertDatabaseMissing('ordenes_produccion', ['id' => $orden->id]);
        $this->assertDatabaseMissing('orden_lineas', ['orden_id' => $orden->id]);
        $this->assertSame(0, TareaPicking::count());
        // No se ha tocado la cantidad física, solo la reserva.
        $this->assertSame(10.0, (float) $this->saldoFresco()->cantidad);
    }

    public function test_borrar_una_orden_cancelada_la_elimina(): void
    {
        $orden = $this->lanzar();
        $orden->forceFill(['estado' => EstadoOrden::Cancelada])->save();

        (new BorrarOrden)->borrar($orden);

        $this->assertDatabaseMissing('ordenes_produccion', ['id' => $orden->id]);
    }

    public function test_no_se_puede_borrar_una_orden_en_picking(): void
    {
        $orden = $this->lanzar();
        $orden->forceFill(['estado' => EstadoOrden::EnPicking])->save();

        $this->expectException(ReglaDeDominioException::class);

        (new BorrarOrden)->borrar($orden);
    }
}
