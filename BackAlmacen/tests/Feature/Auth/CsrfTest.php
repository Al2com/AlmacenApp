<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * El cliente de tests omite la comprobación de CSRF (runningUnitTests), así que
 * aquí se verifica que la protección está cableada en la pila con estado de la
 * API y que la cookie CSRF se sirve. El rechazo real (419) ocurre en peticiones
 * de navegador.
 */
class CsrfTest extends TestCase
{
    public function test_la_api_con_estado_incluye_la_validacion_de_csrf(): void
    {
        $this->assertContains(ValidateCsrfToken::class, config('sanctum.middleware'));
    }

    public function test_la_cookie_csrf_se_sirve(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }
}
