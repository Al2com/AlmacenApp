<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Hash de una contraseña inexistente. Se comprueba contra él cuando el
     * correo no existe, para que la respuesta tarde lo mismo que con un correo
     * real y no se pueda deducir qué cuentas existen.
     */
    private const HASH_FICTICIO = '$2y$10$xXwFL1bTX/XCbXDbeQRg3etFL609lCydaJ9Ah.D3/OZ7ftysoH9AW';

    public function login(LoginRequest $request): UserResource
    {
        $email = (string) $request->string('email');
        $password = (string) $request->string('password');

        $user = User::where('email', $email)->first();

        $passwordValido = $user instanceof User
            ? Hash::check($password, $user->password)
            : Hash::check($password, self::HASH_FICTICIO);

        if (! $user instanceof User || ! $user->activo || ! $passwordValido) {
            Log::warning('Intento de login fallido', ['email' => $email, 'ip' => $request->ip()]);

            // Mismo mensaje tanto si el usuario no existe como si falla la contraseña.
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['ultimo_acceso' => now()])->save();

        return new UserResource($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function cambiarPassword(CambiarPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->string('password')),
            'debe_cambiar_password' => false,
        ])->save();

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
