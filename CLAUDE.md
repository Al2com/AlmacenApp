# AlmacenApp

Proyecto de formación: aplicación para monitorizar un almacén pequeño, con gestión por lotes, FEFO, órdenes de producción, picking y vista 3D.

## Estructura

- `BackAlmacen/`: Laravel + MySQL (API).
- `FrontAlmacen/`: React + Vite + Three.js.
- `docs/`: `er.md` (modelo de datos), `diseno.md` (roles, reglas, estados, flujos), `frontend.md` (identidad visual y pantallas), `seguridad.md` (acceso y protección) y `plan.md` (orden de construcción y decisiones abiertas). Léelos antes de tocar el modelo, la lógica o el front, y sigue el orden de `plan.md`.
- Docker para el entorno local. Un solo repositorio git en la raíz.

## Roles

Cinco roles fijos, guardados como columna `rol` en `users`: admin, gestor de inventario, operador de producción, operador de almacén y operador de recepción. Los permisos se comprueban en el backend con policies, nunca solo ocultando botones en el front. Detalle en `docs/diseno.md`.

## Reglas de dominio clave

- Los movimientos son inmutables: nunca se editan ni se borran. El saldo de `stock` (por lote y ubicación) se actualiza en la misma transacción que inserta el movimiento.
- El stock nunca puede ser negativo (restricción CHECK en la base de datos). El consumo bloquea las filas implicadas con `lockForUpdate`.
- Salida FEFO: caduca antes, sale antes; desempate por fecha de entrada; los lotes sin caducidad al final.
- Un lote caducado no se consume: se da de baja con un movimiento de tipo `baja`.
- Artículos y ubicaciones con historial se desactivan, no se borran.
- La fecha de hoy es configurable, para simular el paso del tiempo. No uses el reloj del sistema directamente en la lógica de caducidad.

## Convenciones

- Tablas y columnas en español, como en `docs/er.md` (excepto `users`, que es el nombre por defecto de Laravel).
- Escribe primero los tests de las reglas críticas (stock no negativo, FEFO, reservas, caducados) y después la lógica.

## Seguridad

La aplicación exige login y todo lo que no sea el propio login queda detrás de él. Las reglas de acceso, contraseñas, sesión, permisos y protección de la API están en `docs/seguridad.md` y no se relajan para ir más rápido. Si una tarea choca con ellas, avísame antes de hacerla.

## Arquitectura y estilo de código

El código debe parecer escrito por una persona con criterio, no generado en bloque.

- Capas claras: la petición entra por un controlador fino, se valida en un Form Request, la lógica de dominio vive en un servicio, los permisos en una policy y la respuesta sale en un Resource. La lógica de negocio no depende de HTTP y se puede probar sola.
- Enums de PHP para rol, estado de orden, estado de tarea y tipo de movimiento. Nada de cadenas sueltas repetidas.
- Nombres de dominio en español, igual que las tablas (`Lote`, `Ubicacion`, `Movimiento`), y las palabras técnicas del framework en inglés (`Controller`, `Policy`, `Request`). Que cada nombre diga lo que es; nada de `Manager`, `Helper` ni `Data`.
- Funciones y componentes cortos, con una sola responsabilidad. Si hace falta un comentario para entender qué hace una línea, se renombra; los comentarios son para explicar por qué se hace algo, no qué hace.
- Sin abstracciones antes de necesitarlas: nada de repositorios genéricos, interfaces con una sola implementación ni patrones por si acaso. Se extrae cuando algo se repite de verdad.
- Sin código muerto, sin `TODO` sueltos y sin bloques comentados. Lo pendiente va a `docs/plan.md`.
- Tests con nombres que describen el comportamiento, por ejemplo `no_permite_dejar_el_stock_en_negativo`.
- Formato automático con Laravel Pint en el back y ESLint con Prettier en el front, y análisis estático con Larastan. Los tres deben pasar limpios antes de cada commit.
- Commits pequeños, uno por cambio con sentido, con mensajes en español que digan qué cambia y por qué.

## Comandos

Pendiente de rellenar cuando se genere el proyecto Laravel y el de React.
