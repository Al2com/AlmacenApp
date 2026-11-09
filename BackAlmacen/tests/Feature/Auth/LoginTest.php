<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteraccionSpa;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteraccionSpa, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comoSpa();
    }

    public function test_un_usuario_activo_entra_con_sus_credenciales(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local']);

        $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@almacen.local');

        // La sesión abierta sirve para la siguiente petición.
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'ana@almacen.local');
    }

    public function test_una_password_incorrecta_se_rechaza(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local']);

        $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'incorrecta'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_el_mensaje_es_el_mismo_para_usuario_inexistente_y_para_password_incorrecta(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local']);

        $conPasswordMala = $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'incorrecta']);
        $conUsuarioInexistente = $this->postJson('/api/login', ['email' => 'nadie@almacen.local', 'password' => 'incorrecta']);

        $this->assertSame($conPasswordMala->json('message'), $conUsuarioInexistente->json('message'));
    }

    public function test_un_usuario_desactivado_no_entra(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local', 'activo' => false]);

        $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'password'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_el_login_se_limita_a_cinco_intentos_por_minuto(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local']);

        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'incorrecta'])
                ->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'incorrecta'])
            ->assertStatus(429);
    }
}
