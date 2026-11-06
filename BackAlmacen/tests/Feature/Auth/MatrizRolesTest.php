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

    #[DataProvider('roles')]
    public function test_las_ordenes_solo_las_gestionan_admin_y_produccion(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $gestionaOrdenes = in_array($rol, [Rol::Admin, Rol::OperadorProduccion], true);
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/ordenes')->assertStatus($gestionaOrdenes ? 200 : 403);
    }

    #[DataProvider('roles')]
    public function test_las_tareas_solo_las_ven_admin_y_almacen(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $veTareas = in_array($rol, [Rol::Admin, Rol::OperadorAlmacen], true);
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/tareas')->assertStatus($veTareas ? 200 : 403);
    }

    #[DataProvider('roles')]
    public function test_el_plano_lo_ven_admin_gestor_y_produccion(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $vePlano = in_array($rol, [Rol::Admin, Rol::GestorInventario, Rol::OperadorProduccion], true);
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/plano/estanteria')->assertStatus($vePlano ? 200 : 403);
    }

    #[DataProvider('roles')]
    public function test_el_stock_lo_consulta_cualquier_rol(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/stock')->assertOk();
    }

    #[DataProvider('roles')]
    public function test_el_feed_de_actividad_solo_lo_ve_el_admin(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/panel/actividad')->assertStatus($rol === Rol::Admin ? 200 : 403);
    }

    #[DataProvider('roles')]
    public function test_el_inventario_lo_gestionan_admin_y_gestor(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $gestionaInventario = in_array($rol, [Rol::Admin, Rol::GestorInventario], true);
        $user = User::factory()->create(['rol' => $rol]);

        // Cuerpo vacío: si tiene permiso pasa el gate y falla la validación (422);
        // si no, el gate lo corta antes con 403.
        $respuesta = $this->actingAs($user)->postJson('/api/inventario/ajustes', []);

        $respuesta->assertStatus($gestionaInventario ? 422 : 403);
    }

    #[DataProvider('roles')]
    public function test_la_demostracion_solo_la_usa_el_admin(Rol $rol, bool $gestionaUsuarios, bool $vePanel): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->getJson('/api/demostracion/estado')->assertStatus($rol === Rol::Admin ? 200 : 403);
    }
}
