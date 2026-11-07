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
- Posición de estantería (panel y plano): `libre`, `ocupada`, `caduca_pronto`, `caducada`, `reservada`, `en_picking`, `recogida`. Una caducidad manda siempre; dentro de las reservadas, la primera tarea pendiente de cada orden activa (`lanzada` o `en_picking`) se marca `en_picking` y el resto `reservada`; al confirmarse, pasa a `recogida` durante una ventana corta (`segundos_recogida`, ajuste) y luego cae a su estado normal. Detalle en `docs/frontend.md`.

Para empezar, confirmar el picking descuenta el material (`consumo`). Más adelante se puede hacer más fiel a la realidad: el picking genera un `traslado` a una ubicación de producción y el consumo ocurre después.

## Flujos

- **Recepción.** El operador de recepción registra la entrada, se crea el lote con su caducidad, el sistema propone una ubicación libre con capacidad y se genera el movimiento de recepción.
- **Orden de producción.** Producción lanza la orden con sus líneas, el sistema reserva por FEFO y genera las tareas, el operador de almacén las recoge en orden de ruta desde la PDA y confirma, la orden queda como preparada y producción confirma que la ha recibido.
- **Conteo.** El gestor lanza un conteo de una zona, el operador cuenta sin ver la cifra del sistema, el sistema calcula la diferencia y el gestor decide si la aprueba. Solo entonces se genera el movimiento de ajuste.

## Actividad en vivo (panel del admin)

El panel del admin muestra un feed con cada operación en el momento en que ocurre: hora, usuario, tipo (recepción, reserva, traslado, ajuste, baja), lote, ubicación y cantidad. En el plano, la posición afectada se resalta unos segundos. **Solo lo ve el admin.**

Se alimenta de tres fuentes: los `movimientos`, las reservas de stock y los eventos de auditoría (accesos fallidos y cambios de configuración de usuarios, ubicaciones y fecha simulada). **No incluye datos sensibles**: ni contraseñas, ni tokens, ni correos completos.

Primera versión por **consulta periódica**: el front pregunta cada 5 segundos a un endpoint que devuelve lo nuevo desde la última marca de tiempo. Paso posterior: tiempo real con **Laravel Reverb**, por un **canal privado autorizado solo para el admin**, lanzando los eventos **después de confirmar la transacción** (`afterCommit`) para no emitir operaciones que luego se deshacen.

## Plan por fases

1. Recepciones, stock y FEFO.
2. Órdenes de producción, reservas y picking.
3. Pantalla de PDA para el operador de almacén.
4. Actividad en vivo (feed del admin).
5. Configuración de medidas del almacén compartida por 2D y 3D.
6. Vista 3D realista (gemelo digital), apoyada en las dos anteriores.

El detalle del orden está en `docs/plan.md`.

## Decisiones de implementación (inventario)

- **Traslado**: mueve solo la **cantidad disponible** (`cantidad` − `cantidad_reservada`), nunca lo reservado, y a una ubicación **activa** con capacidad y hueco de lote. No cambia la fecha de entrada del lote.
- **Caducado**: un lote caducado **no se consume ni se traslada**; solo se da de baja.
- **Regularización (ajuste)**: corrige el saldo contado de un lote **que ya está** en esa ubicación; exige motivo, no deja la cantidad por debajo de lo reservado ni en negativo y **no pasa por la capacidad** de la ubicación (es una corrección de lo que hay, no una colocación nueva). Genera un movimiento de tipo `ajuste` (origen si baja, destino si sube).
- **Reservas**: solo cambian `cantidad_reservada`, **no son movimientos**. Se reparten por FEFO (saltando caducados) y bloquean las filas; una segunda reserva solo ve lo que quedó disponible. Se pueden liberar.
- **Cámara refrigerada**: se modela con `ubicaciones.tipo = 'refrigerado'` (las normales son `'estante'`). De momento es solo organizativa/visual; no hay regla que obligue a un artículo a ir en frío.

## Datos de demostración

`php artisan almacen:demo` llena el almacén para enseñar la aplicación en marcha: 25 artículos, el 85-90 % de las posiciones ocupadas (lotes con fechas variadas: la mayoría en buen estado, algunos que caducan en menos de 7 días y un par ya caducados) y un pedido **`OP-DEMO-GRANDE`** ya lanzado, listo para simular su ruta. Es determinista (sin Faker ni azar): siempre deja el mismo resultado. Con `--fresh` recrea la base antes de sembrar. **Se niega a ejecutarse si `APP_ENV=production`.**

El flujo real del almacén sigue su curso: se pueden crear más pedidos, lanzarlos (reservan stock y generan picking), borrarlos y hacer el picking de verdad por la PDA. Un pedido solo se puede **borrar** antes de empezar el picking (lanzada) o si está cancelada; en picking, preparada o entregada no, porque tiene movimientos inmutables.

### Modo demostración (operario virtual)

Desde el plano 3D, el admin puede poner en marcha un **operario virtual** que recrea la **ruta de picking de la orden activa** (`OP-DEMO-GRANDE` o la última que haya lanzado el usuario), recorriéndola tarea a tarea en orden de ruta. Es una **recreación visual, no un picking real**: no confirma nada ni consume stock, así que se puede repetir las veces que haga falta y las cajas recogidas no desaparecen (quedan marcadas como recogidas en el plano). Vale igual para una orden recién lanzada que para una que ya está en picking; solo quedan fuera las entregadas y canceladas.

- El endpoint `GET /api/demostracion/ruta` (servicio `RutaDemostracion`) solo lee: devuelve la última orden activa (lanzada o en picking) y sus tareas en orden de ruta. Está detrás de la policy de admin y del mismo cierre de producción (gate `usar-demostracion`).
- El front decide la ruta a pie y el ritmo (caminar por el centro de los pasillos, la bola azul del picking, pausar, acelerar, seguir con la cámara...) a partir de esas tareas. Al no confirmar nada, la animación no puede tocar el stock.
- Si no hay ninguna orden activa, la demostración lo avisa en vez de recorrer nada.

## Límites conocidos

- La capacidad se mide en unidades, así que mezcla artículos de tamaños distintos. Una mejora posible es medirla en huecos de palé o en volumen.
- La PDA es una pantalla web pensada para móvil; sin escáner, la confirmación se simula tecleando o pulsando el código de ubicación, el de lote y la cantidad.
