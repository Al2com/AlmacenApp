<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteraccionSpa;
use Tests\TestCase;

class SesionTest extends TestCase
{
    use InteraccionSpa, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comoSpa();
    }

    public function test_logout_cierra_la_sesion(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local']);
        $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'password'])->assertOk();

        $this->postJson('/api/logout')->assertOk();

        $this->comoNuevaPeticion();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_desactivar_a_un_usuario_con_sesion_abierta_invalida_su_siguiente_peticion(): void
    {
        $user = User::factory()->create(['email' => 'ana@almacen.local']);
        $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'password'])->assertOk();
        $this->comoNuevaPeticion();
        $this->getJson('/api/me')->assertOk();

        $user->forceFill(['activo' => false])->save();

        $this->comoNuevaPeticion();
        $this->getJson('/api/me')->assertStatus(403);
    }
}
