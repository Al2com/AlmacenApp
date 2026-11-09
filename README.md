# AlmacenApp

Aplicación para monitorizar el almacén de una empresa de **suministros veterinarios**:
gestión por lotes, salida **FEFO**, órdenes de producción, **picking** con PDA,
cámara refrigerada y una **vista 3D** del almacén.

Proyecto de formación, construido como si fuera a producción (seguridad, tests,
análisis estático).

## Tecnología

- **Backend** (`BackAlmacen/`): Laravel 12 + MySQL 8, API con autenticación por
  sesión (Laravel Sanctum en modo SPA). Pint + Larastan + PHPUnit.
- **Frontend** (`FrontAlmacen/`): React 19 + Vite + React Router, y Three.js
  (react-three-fiber) para la vista 3D. ESLint + Prettier.
- **Docker** para el entorno local (MySQL, backend y frontend).
- Documentación de modelo, diseño, seguridad y plan en `docs/`.

## Requisitos

- Docker y Docker Compose (para MySQL; o un MySQL 8 propio).
- PHP 8.2 y Composer, Node 20+ y npm (para el modo local).

## Puesta en marcha (local)

Desde la raíz del proyecto:

```bash
# 1. Base de datos MySQL (en 127.0.0.1:3306)
cp .env.example .env            # rellena MYSQL_ROOT_PASSWORD y MYSQL_PASSWORD
docker compose up -d db

# 2. Backend
cd BackAlmacen
composer install
cp .env.example .env            # rellena DB_PASSWORD (igual que MYSQL_PASSWORD)
php artisan key:generate
php artisan migrate --seed      # crea el esquema y datos de ejemplo
php artisan serve               # http://127.0.0.1:8000

# 3. Frontend (en otra terminal)
cd FrontAlmacen
npm install
cp .env.example .env            # VITE_API_URL=http://localhost:8000
npm run dev                     # http://localhost:5173
```

Abre **http://localhost:5173** (usa `localhost`, no `127.0.0.1`: el CORS del
backend permite ese origen).

## Usuarios de prueba (seeder)

Solo en el entorno local. Todos con la contraseña **`password`**:

| Correo | Rol | Aterriza en |
|---|---|---|
| `admin@almacen.local` | administrador | Panel |
| `gestor_inventario@almacen.local` | gestor de inventario | Panel |
| `operador_produccion@almacen.local` | operador de producción | Órdenes |
| `operador_almacen@almacen.local` | operador de almacén | PDA (picking) |
| `operador_recepcion@almacen.local` | operador de recepción | Recepciones |

En producción no hay usuarios ni contraseñas por defecto: el primer admin se crea
con `php artisan almacen:crear-admin` (pide la contraseña por consola).

## El flujo de punta a punta

1. **Recepción** (`/recepciones`): registra una entrada (artículo, lote,
   caducidad y cantidad); el sistema propone una ubicación libre con capacidad.
2. **Producción** (`/ordenes`): lanza una orden; el sistema **reserva los lotes
   por FEFO** y genera las **tareas de picking** ordenadas por ruta.
3. **Almacén** (`/pda`): recoge las tareas una a una, confirma con los códigos de
   ubicación y lote (o abre incidencia si no hay material); al confirmar se
   **consume** el stock.
4. **Panel** (`/`) y **plano 3D** (`/plano`): reflejan el stock, el estado de los
   lotes (válido, caduca pronto, caducado, reservado) y la cámara refrigerada.

## Reglas de dominio clave

- El stock nunca es negativo (restricción CHECK en BD); el consumo bloquea las
  filas con `lockForUpdate`.
- Los movimientos son inmutables; el saldo se actualiza en la misma transacción.
- FEFO: caduca antes, sale antes; desempate por fecha de entrada; sin caducidad
  al final. Un lote caducado no se consume: se da de baja.
- La fecha de «hoy» es simulable, para ver caducar los lotes sin esperar.

## Comandos útiles

Backend (`BackAlmacen/`), con MySQL en marcha:

```bash
php artisan test                 # tests (sobre la base almacen_test)
./vendor/bin/pint                # formato · --test para solo comprobar
./vendor/bin/phpstan analyse     # análisis estático (Larastan)
php artisan migrate:fresh --seed # recrear la base con datos de ejemplo
```

Frontend (`FrontAlmacen/`):

```bash
npm run dev          # desarrollo
npm run build        # producción
npm run lint         # ESLint
npm run format       # Prettier
```

## Seguridad

Sesión por cookie `HttpOnly` (Sanctum SPA), login con límite de intentos, cambio
de contraseña obligatorio en el primer acceso, y permisos por rol en policies que
el backend comprueba en cada petición. Detalle en `docs/seguridad.md`.

## Estado

Fases 1 a 4 del plan completas (base/stock/FEFO/seguridad/panel, órdenes y
picking, PDA con códigos e incidencias, y vista 3D). Ver `docs/plan.md`.
