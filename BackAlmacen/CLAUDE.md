# BackAlmacen

API en Laravel con MySQL. Contexto general del proyecto en `../CLAUDE.md`; seguridad en `../docs/seguridad.md`; reglas de dominio en `../docs/diseno.md`; modelo de datos en `../docs/er.md`; orden de trabajo en `../docs/plan.md`.

## Dónde va cada cosa

- La lógica de stock y de FEFO vive en servicios dentro de `app/Services/`, no en controladores ni en modelos.
- Los permisos van en policies de `app/Policies/`, una por recurso, y cada ruta de la API pasa por ellas.
- Los controladores son finos: validan con Form Requests, llaman a un servicio y devuelven un resource.
- Las respuestas de la API se devuelven con API Resources, para no exponer columnas sin querer.

## Reglas que no se rompen

- Un movimiento no se actualiza ni se borra. Si hay un error, se corrige con otro movimiento de ajuste.
- El movimiento y el cambio de saldo en `stock` van en la misma transacción, bloqueando las filas con `lockForUpdate`.
- Para la fecha de hoy se usa la clase de fecha simulada del proyecto, nunca `now()` ni `Carbon::today()` en lógica de caducidad.
- Las migraciones definen las restricciones CHECK; no se confía solo en validar en PHP.

## Seguridad

Todas las rutas de la API van bajo autenticación salvo el login y la cookie CSRF. Una ruta sin policy es una ruta prohibida. Nada de SQL concatenado, `$fillable` explícito en cada modelo, y `.env` fuera del repositorio. El resto, en `../docs/seguridad.md`.

## Tests

Pruebas con PHPUnit (o Pest, si se elige) sobre una base MySQL de test, no SQLite: las restricciones CHECK y los bloqueos tienen que comportarse como en producción. Las reglas críticas se escriben con su test antes que el código.

## Comandos

Desde `BackAlmacen/`. Requiere MySQL en marcha (`docker compose up -d db` desde la raíz).

- Instalar dependencias: `composer install`
- Primera vez: `cp .env.example .env`, rellenar `DB_PASSWORD` y `php artisan key:generate`
- Arrancar el servidor: `php artisan serve` (http://127.0.0.1:8000)
- Migrar: `php artisan migrate` · recrear desde cero: `php artisan migrate:fresh`
- Crear el primer administrador (pide la contraseña por consola): `php artisan almacen:crear-admin`
- Tests (sobre MySQL, base `almacen_test`): `php artisan test`
- Formato de código (Pint): `./vendor/bin/pint` · solo comprobar: `./vendor/bin/pint --test`
- Análisis estático (Larastan): `./vendor/bin/phpstan analyse`
