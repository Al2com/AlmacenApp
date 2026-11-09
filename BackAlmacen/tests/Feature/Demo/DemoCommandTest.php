<?php

namespace Tests\Feature\Demo;

use App\Models\Articulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El comando de demostración se niega a tocar nada en producción.
 *
 * Rojo hasta que exista el comando almacen:demo.
 */
class DemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_niega_a_ejecutarse_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $codigo = $this->artisan('almacen:demo')->run();

        $this->app['env'] = 'testing';

        $this->assertSame(1, $codigo);
        $this->assertSame(0, Articulo::count());
    }

    public function test_funciona_en_entorno_local(): void
    {
        $this->app['env'] = 'local';

        $codigo = $this->artisan('almacen:demo')->run();

        $this->assertSame(0, $codigo);
        $this->assertGreaterThanOrEqual(25, Articulo::count());
    }
}
