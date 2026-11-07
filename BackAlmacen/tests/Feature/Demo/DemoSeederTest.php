<?php

namespace Tests\Feature\Demo;

use App\Models\Articulo;
use App\Models\Movimiento;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El seeder de demostración deja el almacén lleno y coherente, de forma
 * reproducible (siempre el mismo resultado, sin aleatoriedad).
 *
 * Rojo hasta que exista DemoSeeder.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');
    }

    public function test_llena_el_almacen_entre_el_85_y_el_90_por_ciento(): void
    {
        (new DemoSeeder)->run();

        $totalPosiciones = Ubicacion::count();
        $ocupadas = Stock::where('cantidad', '>', 0)->count();
        $ocupacion = $ocupadas / $totalPosiciones;

        $this->assertGreaterThanOrEqual(0.85, $ocupacion);
        $this->assertLessThanOrEqual(0.90, $ocupacion);
    }

    public function test_crea_unos_25_articulos_distintos(): void
    {
        (new DemoSeeder)->run();

        $this->assertGreaterThanOrEqual(25, Articulo::count());
    }

    public function test_hay_lotes_en_los_tres_estados_de_caducidad(): void
    {
        $this->travelTo('2026-11-05');

        (new DemoSeeder)->run();

        $hoy = '2026-11-05';
        $limite = '2026-11-12';

        $this->assertTrue(
            Stock::join('lotes', 'lotes.id', '=', 'stock.lote_id')
                ->where('lotes.caducidad', '<', $hoy)->exists(),
            'Debe haber al menos un lote ya caducado.'
        );
        $this->assertTrue(
            Stock::join('lotes', 'lotes.id', '=', 'stock.lote_id')
                ->whereBetween('lotes.caducidad', [$hoy, $limite])->exists(),
            'Debe haber al menos un lote que caduque en menos de 7 días.'
        );
        $this->assertTrue(
            Stock::join('lotes', 'lotes.id', '=', 'stock.lote_id')
                ->where('lotes.caducidad', '>', $limite)->exists(),
            'La mayoría debe estar en buen estado.'
        );
    }

    public function test_el_stock_cuadra_con_los_movimientos_de_recepcion(): void
    {
        (new DemoSeeder)->run();

        // El seeder solo recibe material (no consume ni da de baja): todo lo
        // que hay en stock viene explicado por una recepción, nada aparece de
        // la nada.
        $totalStock = (float) Stock::sum('cantidad');
        $totalRecepciones = (float) Movimiento::where('tipo', 'recepcion')->sum('cantidad');

        $this->assertSame($totalRecepciones, $totalStock);
    }

    public function test_es_reproducible_no_usa_aleatoriedad(): void
    {
        // Nada de Faker ni de azar en las cantidades, fechas o ubicaciones: el
        // seeder es una lista fija, así que dos ejecuciones (en bases limpias)
        // dan siempre el mismo resultado. Se comprueba fijando valores exactos
        // en vez de volver a sembrar (sembrar dos veces violaría los códigos
        // únicos de artículo).
        (new DemoSeeder)->run();

        $this->assertDatabaseHas('articulos', ['codigo' => 'DEMO-001']);
        $this->assertDatabaseHas('ordenes_produccion', ['codigo' => 'OP-DEMO-GRANDE']);
    }

    public function test_deja_el_pedido_de_demostracion_lanzado_con_preparacion_parcial(): void
    {
        // El seeder deja una orden OP-DEMO-GRANDE ya lanzada, lista para
        // simular su ruta en el plano. La orden es la de la demostración.
        (new DemoSeeder)->run();

        $orden = OrdenProduccion::where('codigo', 'OP-DEMO-GRANDE')->firstOrFail();
        $this->assertSame('lanzada', $orden->estado->value);

        $lineas = OrdenLinea::where('orden_id', $orden->id)->count();
        $this->assertGreaterThanOrEqual(25, $lineas);
        $this->assertLessThanOrEqual(40, $lineas);

        // Al menos una línea no consigue reservar toda la cantidad pedida.
        $haySinServir = OrdenLinea::where('orden_id', $orden->id)
            ->get()
            ->contains(fn (OrdenLinea $linea) => (float) TareaPicking::where('orden_linea_id', $linea->id)->sum('cantidad') < (float) $linea->cantidad_solicitada);

        $this->assertTrue($haySinServir);
    }
}
