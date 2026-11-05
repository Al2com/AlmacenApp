# Seguridad de AlmacenApp

Borrador 1 (5 de noviembre de 2026). Es un proyecto de formación, pero se construye como si fuera a estar en producción: lo que se aprende aquí es justo esto. Este documento fija lo mínimo que no se negocia.

## Acceso

- **Sesión por cookie, no por token.** Laravel Sanctum en modo SPA: el navegador guarda una cookie de sesión `HttpOnly`, `SameSite=Lax` y `Secure` en producción. Así un fallo de XSS en el front no puede robar la sesión, cosa que sí pasa si el token vive en `localStorage`. El front pide primero `/sanctum/csrf-cookie` y manda el token CSRF en cada petición que modifica datos.
- **Sin registro público.** Solo el admin crea cuentas. El primer admin se crea con un comando de Artisan que pide la contraseña por consola; no hay usuarios ni contraseñas por defecto en el repositorio, ni en los seeders.
- **Contraseñas.** Hash con el algoritmo por defecto de Laravel, mínimo de 12 caracteres y comprobación contra contraseñas filtradas conocidas (`Password::min(12)->uncompromised()`). Las cuentas nuevas y los restablecimientos fuerzan el cambio en el primer acceso (`debe_cambiar_password`).
- **Fuerza bruta.** El login admite 5 intentos por minuto por combinación de correo e IP. La respuesta es siempre la misma, tanto si el usuario no existe como si la contraseña es incorrecta, y tarda lo mismo.
- **Sesión.** Se regenera al entrar, se destruye al salir y caduca por inactividad (por defecto 120 minutos; ajustable). Un usuario con `activo` a falso no puede entrar, y si se desactiva a alguien con sesión abierta, su siguiente petición falla.
- **Segundo factor.** Fuera de la primera versión. La estructura de login no debe impedir añadirlo más adelante.

## Permisos

- **Todo bajo autenticación.** Cada ruta de la API exige sesión salvo el login y la cookie CSRF. Una ruta sin policy se considera prohibida.
- **Roles como enum.** Los cinco roles son un enum de PHP, no cadenas sueltas. Los permisos se expresan en policies, una por recurso, y los comprueba el backend en cada petición; lo que se oculta en React es solo comodidad.
- **Cada uno ve lo suyo.** Un operador de almacén solo ve y confirma sus tareas, y producción solo gestiona sus órdenes. Se filtra en la consulta, no en el front.
- **Matriz de tests.** Hay un test que recorre cada rol contra cada endpoint y comprueba que se permite lo que toca y se rechaza lo demás con 403. Si se añade un endpoint, la matriz crece en el mismo commit.

## API

- Toda entrada se valida con Form Requests; las respuestas salen siempre por API Resources, así que nunca se exponen columnas por accidente.
- `$fillable` explícito en cada modelo. Consultas siempre parametrizadas con Eloquent o el constructor de consultas; nada de SQL concatenado con datos del usuario.
- CORS limitado al origen del front. Límite general de peticiones en la API, además del propio del login.
- `APP_DEBUG` a falso fuera de local, para que ningún error enseñe trazas ni rutas internas. HTTPS obligatorio en producción.
- Los movimientos guardan quién los hizo. Además se registran los intentos de login fallidos y los cambios de configuración (usuarios, ubicaciones, fecha simulada). Nunca se escriben contraseñas ni tokens en los registros.

## Datos y entorno

- El `.env` no se sube nunca; el repositorio solo lleva `.env.example` sin valores reales.
- La aplicación se conecta a MySQL con un usuario propio con los permisos justos, no con `root`. El puerto de MySQL no se expone fuera de la máquina.
- Las copias de seguridad de la base de datos no se guardan dentro del repositorio.

## Front

- No se guarda nada sensible en `localStorage` ni en `sessionStorage`.
- No se usa `dangerouslySetInnerHTML`: lo que llega de la API se pinta como texto.
- Las rutas protegidas del front son comodidad de uso. La protección real está en el backend.

## Dependencias

Los archivos de bloqueo (`composer.lock` y `package-lock.json`) van al repositorio. Antes de cerrar cada fase se ejecutan `composer audit` y `npm audit`, y se actualiza lo que tenga vulnerabilidades conocidas.

## Pruebas de seguridad que deben existir

- Credenciales incorrectas, y que el mensaje sea el mismo para usuario inexistente y contraseña errónea.
- El límite de intentos de login.
- Un usuario desactivado no entra y una sesión abierta deja de valer al desactivarlo.
- Una petición que modifica datos sin token CSRF se rechaza.
- La matriz de roles contra endpoints.
- Un operador no puede leer ni modificar tareas u órdenes ajenas cambiando el identificador en la URL.
