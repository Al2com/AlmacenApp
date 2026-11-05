<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Validation\UncompromisedVerifier;

trait InteraccionSpa
{
    /**
     * Prepara la petición como si viniera del front (SPA): marca el origen como
     * dominio con estado para que Sanctum arranque la sesión por cookie.
     */
    protected function comoSpa(): void
    {
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    /**
     * Emula la siguiente petición del navegador: en tests el guard cachea el
     * usuario en memoria, así que lo olvidamos para que relea la sesión por
     * cookie (como ocurre en cada petición real).
     */
    protected function comoNuevaPeticion(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Evita la llamada de red a «Have I Been Pwned» en los tests que crean
     * contraseñas; la regla uncompromised() se sigue aplicando en producción.
     */
    protected function sinComprobarFiltraciones(): void
    {
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data)
            {
                return true;
            }
        });
    }
}
