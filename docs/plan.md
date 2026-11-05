# Plan de construcción

Orden de trabajo para ir codificando. Cada paso debe quedar con sus tests en verde y un commit antes de pasar al siguiente. Las reglas están en `docs/diseno.md` y el modelo en `docs/er.md`; aquí solo está el orden.

## Fase 1: base, stock y FEFO

1. **Entorno.** Generar el proyecto Laravel en `BackAlmacen/` y el de React con Vite en `FrontAlmacen/`. Docker Compose con MySQL 8 (mínimo 8.0.16, para que se apliquen las restricciones CHECK), el backend y el front. Rellenar la sección de comandos de los tres `CLAUDE.md`.
2. **Migraciones** en este orden, por las claves ajenas: `users` (con la columna `rol`), `articulos`, `ubicaciones`, `lotes`, `stock` (clave primaria compuesta lote y ubicación), `movimientos`. Las de órdenes y picking se dejan para la fase 2. Todas las claves ajenas con `ON DELETE RESTRICT`.
3. **Tests primero** de las reglas críticas, antes de escribir la lógica:
   - el stock no puede quedar negativo;
   - un movimiento y el cambio de saldo se guardan juntos o no se guarda nada;
   - dos consumos simultáneos sobre el mismo saldo no dejan cantidades incoherentes;
   - la selección FEFO elige el lote que caduca antes, desempata por fecha de entrada y deja los lotes sin caducidad al final;
   - un lote caducado no se consume, solo se da de baja;
   - una ubicación no admite más lotes que su máximo ni más cantidad que su capacidad.
4. **Servicios de dominio**: uno que registra movimientos y actualiza el saldo en una transacción, y otro que propone los lotes a consumir por FEFO. Los controladores solo llaman a estos servicios; la lógica no vive en ellos.
5. **Fecha simulada.** Una sola clase que dice qué día es «hoy» para toda la lógica de caducidad, configurable por el admin. Nada de `now()` suelto en esas reglas.
6. **Seeder** con datos de ejemplo parecidos a los del prototipo: 4 pasillos de 10 bahías por 3 niveles, unos cuantos artículos con y sin caducidad, lotes en distintos estados y una docena de movimientos.
7. **Acceso y seguridad**, según `docs/seguridad.md`: Sanctum en modo SPA con sesión por cookie HttpOnly, login con límite de intentos, logout, primer acceso con cambio de contraseña obligatorio y un comando para crear el primer admin. Policies por rol con una matriz de tests (cada rol contra cada endpoint). Nada de lo que viene después se construye sin pasar por este paso.
8. **API de lectura** para el panel: cifras del día, estantería por pasillo, orden FEFO, últimos movimientos, entradas y salidas por hora.
9. **Recepciones**: crear lote, proponer ubicación y registrar el movimiento de recepción.
10. **Front**: login, layout con el menú por rol, rutas protegidas y el panel general conectado a la API real, siguiendo `docs/frontend.md`.

## Fase 2: órdenes y picking

Órdenes de producción con líneas, reserva por FEFO al lanzarlas, generación de tareas ordenadas por ruta, preparación parcial si falta stock, y confirmación de producción. Aquí entran las tablas `ordenes_produccion`, `orden_lineas` y `tareas_picking`.

## Fase 3: PDA

Pantalla móvil para el operador de almacén, con confirmación por códigos e incidencias.

## Fase 4: vista 3D

Recreación con Three.js de las posiciones, con los mismos estados y el mismo detalle que la estantería 2D.

## Decisiones abiertas

- Segundo factor de autenticación: fuera de la primera versión, pero la estructura de login no debe impedirlo.
- Si la tarea de picking referencia el stock con una clave ajena compuesta (lote y ubicación) para que no pueda apuntar a un saldo que no existe. Hoy son dos claves ajenas sueltas.
- Cuántos días antes del vencimiento se considera «caduca pronto» (provisional: 7, como ajuste).
