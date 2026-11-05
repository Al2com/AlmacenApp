<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea el primer administrador de forma interactiva. No hay usuarios ni
 * contraseñas por defecto en el repositorio ni en los seeders: la contraseña
 * se pide por consola y nunca se escribe en los registros.
 */
class CrearAdmin extends Command
{
    protected $signature = 'almacen:crear-admin';

    protected $description = 'Crea el primer usuario administrador de forma interactiva';

    public function handle(): int
    {
        $datos = [
            'nombre' => $this->ask('Nombre'),
            'email' => $this->ask('Correo'),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Repite la contraseña'),
        ];

        $validator = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->uncompromised()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'rol' => Rol::Admin,
            'password' => Hash::make((string) $datos['password']),
            'activo' => true,
            'debe_cambiar_password' => false,
        ]);

        $this->info("Administrador «{$user->nombre}» creado.");

        return self::SUCCESS;
    }
}
