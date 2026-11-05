<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogoArticulosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Rol}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [Rol::Admin],
            'gestor' => [Rol::GestorInventario],
            'produccion' => [Rol::OperadorProduccion],
            'almacen' => [Rol::OperadorAlmacen],
            'recepcion' => [Rol::OperadorRecepcion],
        ];
    }

    #[DataProvider('roles')]
    public function test_cualquier_rol_consulta_el_catalogo(Rol $rol): void
    {
        Articulo::factory()->create(['activo' => true]);

        $this->actingAs(User::factory()->create(['rol' => $rol]))
            ->getJson('/api/articulos')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_el_catalogo_omite_los_articulos_inactivos(): void
    {
        Articulo::factory()->create(['activo' => true]);
        Articulo::factory()->create(['activo' => false]);

        $this->actingAs(User::factory()->create(['rol' => Rol::OperadorProduccion]))
            ->getJson('/api/articulos')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
