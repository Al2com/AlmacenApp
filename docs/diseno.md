# Diseño de AlmacenApp

Borrador 1 (5 de noviembre de 2026). Proyecto de formación: una aplicación para monitorizar un almacén pequeño, con gestión por lotes, FEFO, órdenes de producción, picking y una vista 3D. El modelo de datos está en `docs/er.md`.

## Roles y permisos

| Rol | Puede |
|---|---|
| Admin | Todo: usuarios, artículos, ubicaciones y su configuración (capacidad y máximo de lotes), fecha simulada y cualquier operación. Sus acciones también quedan registradas. |
| Gestor de inventario | Solo cantidades: conteos, ajustes con motivo obligatorio, bajas por caducidad, corrección de datos de un lote e incidencias de picking. |
| Operador de producción | Crear, lanzar y cancelar sus órdenes, ver su estado, consultar stock y confirmar que ha recibido el material preparado. |
| Operador de almacén | Ver sus tareas de picking en orden de ruta, confirmarlas o marcar incidencia, hacer traslados internos y contar. |
| Operador de recepción | Registrar entradas, crear lotes con su caducidad y ubicar el material. |

Los permisos se comprueban siempre en el backend (policies de Laravel). Ocultar un botón en React es solo comodidad. Cada movimiento guarda el usuario que lo hizo.

## Reglas de dominio

1. Los movimientos son un registro inmutable: nunca se editan ni se borran. El saldo de la tabla `stock` se actualiza en la misma transacción que inserta el movimiento.
2. El saldo se guarda por lote y ubicación. Disponible = `cantidad` - `cantidad_reservada`. La base de datos rechaza cantidades negativas (restricción CHECK) y el consumo bloquea (`lockForUpdate`) las filas implicadas para evitar que dos operaciones simultáneas lean el mismo saldo.
3. Criterio de salida FEFO: primero el lote que caduca antes; si empatan, el que entró antes; los lotes sin caducidad van al final, por fecha de entrada.
4. Un lote caducado no se consume. Se da de baja con un movimiento de tipo `baja`, para que el saldo siga cuadrando con el histórico. El estado de caducado se calcula comparando con la fecha de hoy, no se guarda.
5. La fecha de entrada pertenece al lote, no a la fila de stock: un traslado no rejuvenece el material.
6. Al lanzar una orden se reservan los lotes por FEFO y se generan tareas de picking ordenadas por ruta (pasillo, estantería, nivel). Si falta stock, se prepara lo que hay, la línea queda marcada con su faltante y la orden no pasa a preparada hasta resolverse.
7. Cada ubicación admite un máximo de lotes (por defecto uno) y una capacidad. No se puede bajar ninguno de los dos por debajo de lo que la ubicación ya contiene.
8. Artículos y ubicaciones con historial se desactivan, no se borran. No se cambia la unidad de medida ni la condición de tener caducidad de un artículo que ya tiene movimientos.
9. La fecha de hoy es configurable en el sistema, para poder simular el paso del tiempo y ver cómo caducan los lotes.

## Estados y tipos

- Orden de producción: `lanzada`, `en_picking`, `preparada`, `entregada`, `cancelada`.
- Tarea de picking: `pendiente`, `confirmada`, `incidencia`.
- Movimiento: `recepcion`, `consumo`, `traslado`, `ajuste`, `baja`.

Para empezar, confirmar el picking descuenta el material (`consumo`). Más adelante se puede hacer más fiel a la realidad: el picking genera un `traslado` a una ubicación de producción y el consumo ocurre después.

## Flujos

- **Recepción.** El operador de recepción registra la entrada, se crea el lote con su caducidad, el sistema propone una ubicación libre con capacidad y se genera el movimiento de recepción.
- **Orden de producción.** Producción lanza la orden con sus líneas, el sistema reserva por FEFO y genera las tareas, el operador de almacén las recoge en orden de ruta desde la PDA y confirma, la orden queda como preparada y producción confirma que la ha recibido.
- **Conteo.** El gestor lanza un conteo de una zona, el operador cuenta sin ver la cifra del sistema, el sistema calcula la diferencia y el gestor decide si la aprueba. Solo entonces se genera el movimiento de ajuste.

## Plan por fases

1. Recepciones, stock y FEFO.
2. Órdenes de producción, reservas y picking.
3. Pantalla de PDA para el operador de almacén.
4. Vista 3D con Three.js.

## Límites conocidos

- La capacidad se mide en unidades, así que mezcla artículos de tamaños distintos. Una mejora posible es medirla en huecos de palé o en volumen.
- La PDA es una pantalla web pensada para móvil; sin escáner, la confirmación se simula tecleando o pulsando el código de ubicación, el de lote y la cantidad.
