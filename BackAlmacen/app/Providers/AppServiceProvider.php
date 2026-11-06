<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fuerza bruta: 5 intentos de login por minuto y combinación de correo e IP.
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // El panel general es de admin y gestor de inventario (docs/frontend.md).
        Gate::define('ver-panel', fn (User $user): bool => in_array($user->rol, [Rol::Admin, Rol::GestorInventario], true));

        // Las recepciones las registran admin y operador de recepción (docs/diseno.md).
        Gate::define('registrar-recepcion', fn (User $user): bool => in_array($user->rol, [Rol::Admin, Rol::OperadorRecepcion], true));

        // El catálogo de artículos es de consulta para cualquier rol autenticado.
        Gate::define('ver-catalogo', fn (User $user): bool => true);

        // El plano del almacén (2D y 3D) lo ven admin, gestor y producción (docs/frontend.md).
        Gate::define('ver-plano', fn (User $user): bool => in_array($user->rol, [Rol::Admin, Rol::GestorInventario, Rol::OperadorProduccion], true));

        // El stock es de consulta para cualquier rol autenticado (docs/frontend.md).
        Gate::define('ver-stock', fn (User $user): bool => true);
    }
}
