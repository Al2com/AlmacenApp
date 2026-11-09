<?php

namespace Tests\Feature;

use App\Enums\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteraccionSpa;
use Tests\TestCase;

class CrearAdminCommandTest extends TestCase
{
    use InteraccionSpa, RefreshDatabase;

    public function test_crea_el_primer_administrador(): void
    {
        $this->sinComprobarFiltraciones();

        $this->artisan('almacen:crear-admin')
            ->expectsQuestion('Nombre', 'Admin Uno')
            ->expectsQuestion('Correo', 'admin@almacen.local')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'guardalmacen-2026-abc')
            ->expectsQuestion('Repite la contraseña', 'guardalmacen-2026-abc')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@almacen.local',
            'rol' => Rol::Admin->value,
            'activo' => true,
            'debe_cambiar_password' => false,
        ]);
    }

    public function test_rechaza_una_password_demasiado_corta(): void
    {
        $this->artisan('almacen:crear-admin')
            ->expectsQuestion('Nombre', 'Admin Uno')
            ->expectsQuestion('Correo', 'admin@almacen.local')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'corta')
            ->expectsQuestion('Repite la contraseña', 'corta')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
