<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Solo el admin gestiona cuentas (docs/seguridad.md: «Solo el admin crea
 * cuentas»).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol === Rol::Admin;
    }

    public function create(User $user): bool
    {
        return $user->rol === Rol::Admin;
    }

    public function update(User $user, User $objetivo): bool
    {
        return $user->rol === Rol::Admin;
    }
}
