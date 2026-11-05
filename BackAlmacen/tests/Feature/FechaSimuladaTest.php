<?php

namespace Tests\Feature;

use App\Support\FechaSimulada;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La fecha de «hoy» es configurable para simular el paso del tiempo; por
 * defecto es el día real y puede volver a él.
 */
class FechaSimuladaTest extends TestCase
{
    public function test_por_defecto_hoy_es_el_dia_real(): void
    {
        $this->travelTo('2026-11-05');

        $this->assertSame('2026-11-05', FechaSimulada::hoy()->toDateString());
    }

    public function test_se_puede_fijar_un_dia_concreto(): void
    {
        $this->travelTo('2026-11-05');

        FechaSimulada::fijar(Carbon::parse('2026-12-25'));

        $this->assertSame('2026-12-25', FechaSimulada::hoy()->toDateString());
        $this->assertTrue(FechaSimulada::estaSimulada());
    }

    public function test_la_fecha_fijada_persiste_entre_consultas(): void
    {
        FechaSimulada::fijar(Carbon::parse('2027-01-10'));

        $this->assertSame('2027-01-10', FechaSimulada::hoy()->toDateString());
        $this->assertSame('2027-01-10', FechaSimulada::hoy()->toDateString());
    }

    public function test_al_limpiar_vuelve_al_reloj_real(): void
    {
        $this->travelTo('2026-11-05');
        FechaSimulada::fijar(Carbon::parse('2026-12-25'));

        FechaSimulada::limpiar();

        $this->assertFalse(FechaSimulada::estaSimulada());
        $this->assertSame('2026-11-05', FechaSimulada::hoy()->toDateString());
    }
}
