<?php

namespace Tests\Feature\Auth;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteraccionSpa;
use Tests\TestCase;

/**
 * Matriz de roles contra los endpoints que existen hoy. Crece en el mismo
 * commit cuando se añade un endpoint (docs/seguridad.md).
 */
class MatrizRolesTest extends TestCase
{
    use InteraccionSpa, RefreshDatabase;

    /**
     * @return array<string, array{Rol, bool}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [Rol::Admin, true],
            'gestor de inventario' => [Rol::GestorInventario, false],
            'operador de producción' => [Rol::OperadorProduccion, false],
            'operador de almacén' => [Rol::OperadorAlmacen, false],
            'operador de recepción' => [Rol::OperadorRecepcion, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_cualquier_rol_autenticado_ve_su_perfil(Rol $rol, bool $gestionaUsuarios): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/me')->assertOk();
    }

    #[DataProvider('roles')]
    public function test_listar_usuarios_solo_lo_permite_el_admin(Rol $rol, bool $gestionaUsuarios): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/usuarios')->assertStatus($gestionaUsuarios ? 200 : 403);
    }

    #[DataProvider('roles')]
    public function test_crear_usuarios_solo_lo_permite_el_admin(Rol $rol, bool $gestionaUsuarios): void
    {
        $this->sinComprobarFiltraciones();
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->postJson('/api/usuarios', [
            'nombre' => 'Cuenta Nueva',
            'email' => $rol->value.'-nueva@almacen.local',
            'rol' => Rol::OperadorAlmacen->value,
            'password' => 'guardalmacen-2026-abc',
            'password_confirmation' => 'guardalmacen-2026-abc',
        ])->assertStatus($gestionaUsuarios ? 201 : 403);
    }
}
