<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Lote;
use App\Services\SeleccionFefo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Criterio FEFO: caduca antes, sale antes; desempate por fecha de entrada;
 * los lotes sin caducidad al final.
 *
 * Rojo hasta el paso 4 (depende de SeleccionFefo).
 */
class SeleccionFefoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<Lote>  $lotes
     * @return list<int>
     */
    private function ids(array $lotes): array
    {
        return array_map(fn (Lote $lote) => $lote->id, $lotes);
    }

    public function test_elige_primero_el_lote_que_caduca_antes(): void
    {
        $articulo = Articulo::factory()->create();
        $tarde = Lote::factory()->for($articulo)->create([
            'caducidad' => '2026-12-31',
            'fecha_recepcion' => '2026-11-01',
        ]);
        $pronto = Lote::factory()->for($articulo)->create([
            'caducidad' => '2026-11-20',
            'fecha_recepcion' => '2026-11-05',
        ]);

        $orden = (new SeleccionFefo)->ordenar($articulo);

        $this->assertSame([$pronto->id, $tarde->id], $this->ids($orden));
    }

    public function test_desempata_por_fecha_de_entrada_cuando_caducan_el_mismo_dia(): void
    {
        $articulo = Articulo::factory()->create();
        $nuevo = Lote::factory()->for($articulo)->create([
            'caducidad' => '2026-12-01',
            'fecha_recepcion' => '2026-11-04',
        ]);
        $viejo = Lote::factory()->for($articulo)->create([
            'caducidad' => '2026-12-01',
            'fecha_recepcion' => '2026-10-01',
        ]);

        $orden = (new SeleccionFefo)->ordenar($articulo);

        $this->assertSame([$viejo->id, $nuevo->id], $this->ids($orden));
    }

    public function test_los_lotes_sin_caducidad_van_al_final(): void
    {
        $articulo = Articulo::factory()->create();
        $sinCaducidad = Lote::factory()->for($articulo)->sinCaducidad()->create([
            'fecha_recepcion' => '2026-10-01',
        ]);
        $conCaducidad = Lote::factory()->for($articulo)->create([
            'caducidad' => '2026-12-31',
            'fecha_recepcion' => '2026-11-01',
        ]);

        $orden = (new SeleccionFefo)->ordenar($articulo);

        $this->assertSame([$conCaducidad->id, $sinCaducidad->id], $this->ids($orden));
    }
}
