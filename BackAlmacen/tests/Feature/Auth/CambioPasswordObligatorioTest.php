<?php

namespace Tests\Feature\Auth;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteraccionSpa;
use Tests\TestCase;

class CambioPasswordObligatorioTest extends TestCase
{
    use InteraccionSpa, RefreshDatabase;

    public function test_una_cuenta_que_debe_cambiar_password_no_accede_al_resto_de_la_api(): void
    {
        $user = User::factory()->debeCambiarPassword()->create(['rol' => Rol::Admin]);

        $this->actingAs($user)->getJson('/api/usuarios')->assertStatus(409);
    }

    public function test_el_perfil_es_accesible_aunque_deba_cambiar_la_password(): void
    {
        $user = User::factory()->debeCambiarPassword()->create();

        $this->actingAs($user)->getJson('/api/me')->assertOk();
    }

    public function test_tras_cambiar_la_password_accede_con_normalidad(): void
    {
        $this->sinComprobarFiltraciones();
        $user = User::factory()->debeCambiarPassword()->create(['rol' => Rol::Admin]);

        $this->actingAs($user)->putJson('/api/password', [
            'password_actual' => 'password',
            'password' => 'guardalmacen-2026-abc',
            'password_confirmation' => 'guardalmacen-2026-abc',
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->debe_cambiar_password);

        $this->actingAs($user)->getJson('/api/usuarios')->assertOk();
    }

    public function test_cambiar_la_password_exige_la_actual_correcta(): void
    {
        $this->sinComprobarFiltraciones();
        $user = User::factory()->debeCambiarPassword()->create();

        $this->actingAs($user)->putJson('/api/password', [
            'password_actual' => 'no-es-la-actual',
            'password' => 'guardalmacen-2026-abc',
            'password_confirmation' => 'guardalmacen-2026-abc',
        ])->assertStatus(422);
    }
}
