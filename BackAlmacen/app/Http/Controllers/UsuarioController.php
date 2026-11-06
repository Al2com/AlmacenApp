<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\ActualizarUsuarioRequest;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\RegistroDeActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Gestión de cuentas. Solo el admin (UserPolicy). Las cuentas nuevas nacen con
 * el cambio de contraseña obligatorio en el primer acceso.
 */
class UsuarioController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(User::orderBy('nombre')->get());
    }

    public function store(GuardarUsuarioRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $user = User::create([
            'nombre' => (string) $request->string('nombre'),
            'email' => (string) $request->string('email'),
            'rol' => $request->enum('rol', Rol::class),
            'password' => Hash::make((string) $request->string('password')),
            'activo' => true,
            'debe_cambiar_password' => true,
        ]);

        Log::info('Cuenta creada', ['admin' => $request->user()?->getAuthIdentifier(), 'usuario' => $user->id]);
        $this->anotar($request, "cuenta creada: {$user->nombre}");

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function update(ActualizarUsuarioRequest $request, User $usuario): UserResource
    {
        Gate::authorize('update', $usuario);

        $usuario->fill($request->validated())->save();

        Log::info('Cuenta actualizada', ['admin' => $request->user()?->getAuthIdentifier(), 'usuario' => $usuario->id]);
        $this->anotar($request, "cuenta actualizada: {$usuario->nombre}");

        return new UserResource($usuario);
    }

    private function anotar(Request $request, string $detalle): void
    {
        $admin = $request->user();
        (new RegistroDeActividad)->registrar(
            'cambio_config',
            $admin instanceof User ? $admin : null,
            null,
            $detalle,
        );
    }
}
