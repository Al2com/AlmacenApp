# Modelo entidad-relación

Borrador 1 (5 de noviembre de 2026). Cubre las fases iniciales: recepciones, stock, órdenes de producción y picking. GitHub renderiza el diagrama de abajo directamente.

```mermaid
erDiagram
  ARTICULOS ||--o{ LOTES : tiene
  LOTES ||--o{ STOCK : "se guarda en"
  UBICACIONES ||--o{ STOCK : contiene
  LOTES ||--o{ MOVIMIENTOS : registra
  UBICACIONES |o--o{ MOVIMIENTOS : origen
  UBICACIONES |o--o{ MOVIMIENTOS : destino
  USERS ||--o{ MOVIMIENTOS : realiza
  USERS ||--o{ ORDENES_PRODUCCION : lanza
  ORDENES_PRODUCCION ||--|{ ORDEN_LINEAS : tiene
  ARTICULOS ||--o{ ORDEN_LINEAS : pide
  ORDEN_LINEAS ||--o{ TAREAS_PICKING : genera
  LOTES ||--o{ TAREAS_PICKING : reserva
  UBICACIONES ||--o{ TAREAS_PICKING : "recoge de"
  USERS |o--o{ TAREAS_PICKING : "asignada a"
  TAREAS_PICKING |o--o{ MOVIMIENTOS : origina

  USERS {
    int id PK
    string nombre
    string email UK
    string password
    string rol
    boolean activo
    boolean debe_cambiar_password
    datetime ultimo_acceso
  }
  ARTICULOS {
    int id PK
    string codigo UK
    string nombre
    string unidad
    boolean tiene_caducidad
    boolean activo
  }
  UBICACIONES {
    int id PK
    string codigo UK
    string tipo
    int pasillo
    int estanteria
    int nivel
    int capacidad
    int max_lotes
    boolean activa
  }
  LOTES {
    int id PK
    int articulo_id FK
    string codigo_lote
    date fecha_recepcion
    date caducidad
  }
  STOCK {
    int lote_id PK, FK
    int ubicacion_id PK, FK
    decimal cantidad
    decimal cantidad_reservada
  }
  MOVIMIENTOS {
    int id PK
    string tipo
    int lote_id FK
    int ubicacion_origen_id FK
    int ubicacion_destino_id FK
    decimal cantidad
    int user_id FK
    int tarea_picking_id FK
    string motivo
    timestamp created_at
  }
  ORDENES_PRODUCCION {
    int id PK
    string codigo UK
    string estado
    int user_id FK
    timestamp created_at
  }
  ORDEN_LINEAS {
    int id PK
    int orden_id FK
    int articulo_id FK
    decimal cantidad_solicitada
    decimal cantidad_preparada
  }
  TAREAS_PICKING {
    int id PK
    int orden_linea_id FK
    int lote_id FK
    int ubicacion_id FK
    decimal cantidad
    int secuencia
    string estado
    int asignada_a FK
    timestamp confirmada_at
  }
```

## Notas sobre el modelo

- `stock` no tiene id propio: su clave es la pareja (`lote_id`, `ubicacion_id`), que impide duplicados. Es el saldo actual y se actualiza en la misma transacción que inserta el movimiento.
- En `movimientos`, `ubicacion_origen_id` y `ubicacion_destino_id` son opcionales a propósito. Una recepción no tiene origen y una baja no tiene destino. La cantidad siempre es positiva.
- `movimientos` es un registro inmutable: nunca se edita ni se borra. `tarea_picking_id` permite saber qué movimiento salió de qué tarea.
- La fecha de entrada (`fecha_recepcion`) y la caducidad pertenecen al lote, no al stock. Así un traslado no rejuvenece el material.
- `users` lleva además `debe_cambiar_password` (primer acceso y restablecimientos) y `ultimo_acceso`. Un usuario con `activo` a falso no puede entrar. Detalle en `docs/seguridad.md`.
- El rol es una columna de `users`, sin tabla aparte. Son cinco roles fijos: admin, gestor de inventario, operador de producción, operador de almacén y operador de recepción.
- `ubicaciones.max_lotes` (por defecto 1) y `ubicaciones.capacidad` regulan cuántos lotes y cuánta cantidad admite cada ubicación.
- `tareas_picking` se crea al lanzar una orden y reserva lotes por FEFO. `ordenes_produccion.estado` y `tareas_picking.estado` están descritos en `docs/diseno.md`.

## Fuera de este borrador

Conteos cíclicos y sus líneas, incidencias como entidad propia, configuración del sistema (fecha simulada) y stock mínimo con avisos de reposición.
