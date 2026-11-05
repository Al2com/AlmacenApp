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
     * @return array<string, array{Rol, bool, bool}>
     */
    public static function roles(): array
    {
        // [rol, gestiona usuarios (solo admin), ve el panel (admin y gestor)]
        return [
            'admin' => [Rol::Admin, true, true],
            'gestor de inventario' => [Rol::GestorInventario, false, true],
            'operador de producción' => [Rol::OperadorProduccion, false, false],
            'operador de almacén' => [Rol::OperadorAlmacen, false, false],
            'operador de recepción' => [Rol::OperadorRecepcion, false, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_cualquier_rol_autenticado_ve_su_perfil(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/me')->assertOk();
    }

    #[DataProvider('roles')]
    public function test_listar_usuarios_solo_lo_permite_el_admin(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/usuarios')->assertStatus($gestionaUsuarios ? 200 : 403);
    }

    #[DataProvider('roles')]
    public function test_crear_usuarios_solo_lo_permite_el_admin(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
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

    #[DataProvider('roles')]
    public function test_el_panel_solo_lo_ven_admin_y_gestor(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        foreach (['/api/panel/cifras', '/api/panel/estanteria', '/api/panel/fefo', '/api/panel/movimientos', '/api/panel/flujo'] as $endpoint) {
            $this->actingAs($user)->getJson($endpoint)->assertStatus($vePanel ? 200 : 403);
        }
    }

    #[DataProvider('roles')]
    public function test_las_recepciones_solo_las_registran_admin_y_recepcion(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $registraRecepciones = in_array($rol, [Rol::Admin, Rol::OperadorRecepcion], true);
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)
            ->getJson('/api/recepciones/ubicacion-propuesta?cantidad=10')
            ->assertStatus($registraRecepciones ? 200 : 403);
    }
}
