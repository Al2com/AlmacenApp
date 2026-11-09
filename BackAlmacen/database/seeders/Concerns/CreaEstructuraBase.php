<?php

namespace Database\Seeders\Concerns;

use App\Enums\Rol;
use App\Models\Ubicacion;
use App\Models\User;

/**
 * Usuarios (uno por rol) y la estantería del almacén (4 pasillos de 10 por 3
 * niveles, más una cámara refrigerada). Lo comparten el seeder normal y el de
 * demostración para no repetir la estructura del almacén en los dos sitios.
 */
trait CreaEstructuraBase
{
    /**
     * Un usuario por rol, con contraseña conocida para el entorno local.
     *
     * @return array<string, User>
     */
    private function crearUsuarios(): array
    {
        $usuarios = [];

        foreach (Rol::cases() as $rol) {
            $usuarios[$rol->value] = User::factory()->create([
                'nombre' => ucfirst(str_replace('_', ' ', $rol->value)),
                'email' => $rol->value.'@almacen.local',
                'rol' => $rol,
            ]);
        }

        return $usuarios;
    }

    /**
     * La estantería del prototipo: 4 pasillos de 10 estanterías por 3 niveles,
     * más una cámara refrigerada con una estantería pequeña (3 por 2) para los
     * productos que necesitan frío.
     *
     * @return array{normales: list<Ubicacion>, frias: list<Ubicacion>}
     */
    private function crearUbicaciones(): array
    {
        $normales = [];
        foreach (range(1, 4) as $pasillo) {
            foreach (range(1, 10) as $estanteria) {
                foreach (range(1, 3) as $nivel) {
                    $normales[] = Ubicacion::create([
                        'codigo' => sprintf('P%d-E%02d-N%d', $pasillo, $estanteria, $nivel),
                        'tipo' => 'estante',
                        'pasillo' => $pasillo,
                        'estanteria' => $estanteria,
                        'nivel' => $nivel,
                        'capacidad' => 100,
                        'max_lotes' => 1,
                        'activa' => true,
                    ]);
                }
            }
        }

        $frias = [];
        foreach (range(1, 3) as $estanteria) {
            foreach (range(1, 2) as $nivel) {
                $frias[] = Ubicacion::create([
                    'codigo' => sprintf('FRIO-E%02d-N%d', $estanteria, $nivel),
                    'tipo' => 'refrigerado',
                    'pasillo' => 5,
                    'estanteria' => $estanteria,
                    'nivel' => $nivel,
                    'capacidad' => 100,
                    'max_lotes' => 1,
                    'activa' => true,
                ]);
            }
        }

        return ['normales' => $normales, 'frias' => $frias];
    }
}
