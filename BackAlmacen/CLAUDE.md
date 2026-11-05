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

Pendiente de rellenar al generar el proyecto: arrancar el servidor, migrar y sembrar, lanzar los tests y formatear el código.
