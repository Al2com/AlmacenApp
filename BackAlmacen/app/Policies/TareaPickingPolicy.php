<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\TareaPicking;
use App\Models\User;

/**
 * Las tareas de picking son del operador de almacén (y del admin). Forman una
 * cola compartida de la nave: se recogen en orden de ruta.
 */
class TareaPickingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, [Rol::Admin, Rol::OperadorAlmacen], true);
    }

    public function confirmar(User $user, TareaPicking $tarea): bool
    {
        return in_array($user->rol, [Rol::Admin, Rol::OperadorAlmacen], true);
    }

    public function incidencia(User $user, TareaPicking $tarea): bool
    {
        return in_array($user->rol, [Rol::Admin, Rol::OperadorAlmacen], true);
    }
}
