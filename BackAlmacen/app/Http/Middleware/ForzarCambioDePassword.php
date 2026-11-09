<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras una cuenta tenga pendiente el cambio de contraseña del primer
 * acceso, no puede usar el resto de la API. Se responde 409 para que el front
 * sepa que debe llevar al usuario a la pantalla de cambio de contraseña.
 */
class ForzarCambioDePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->debe_cambiar_password) {
            return response()->json([
                'message' => 'Debe cambiar la contraseña antes de continuar.',
                'code' => 'cambio_password_requerido',
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
