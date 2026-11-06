# Front de AlmacenApp

Borrador 1 (5 de noviembre de 2026). Describe cómo debe verse y comportarse el front. El diseño visual de referencia es el dashboard prototipado en el lienzo de diseño; si algo de aquí choca con lo que se ve allí, manda este documento y se corrige el prototipo.

## Tecnología

React + Vite, React Router para las rutas, y Three.js con react-three-fiber para la vista 3D (fase 4). Peticiones a la API con fetch o axios, y una capa fina en `src/api/` para que ningún componente construya URLs a mano. Estilos con CSS propio y variables; sin librería de componentes, para que el diseño no se parezca a todo lo demás.

## Identidad visual

Concepto: sala de control de un almacén. El elemento protagonista es la estantería en alzado (pasillo, bahía y nivel); todo lo demás es sobrio y tipográfico.

| Uso | Valor |
|---|---|
| Fondo de la aplicación (hormigón) | `#E6E9EC` |
| Tinta y panel oscuro | `#0E1B2E` |
| Texto secundario | `#46546A` |
| Azul (entradas, acciones, montantes) | `#2447D6` |
| Naranja (salidas, alertas, largueros) | `#E8590C`, y `#B9460A` cuando va como texto sobre claro |
| Líneas finas | `#C7CDD3` |

Tipografía: Archivo (Google Fonts, eje de ancho incluido). Títulos y cifras con `font-stretch: 112%` y peso 800; texto a 14-15 px con peso 400-600. No hay mayúsculas en etiquetas.

Estados de una posición de estantería (siempre con símbolo además de color, para que no dependa solo del tono):

| Estado | Fondo | Símbolo |
|---|---|---|
| Libre | transparente, borde discontinuo | ninguno |
| Ocupada | `#8FA6F2` | ninguno |
| Caduca en 7 días o menos | `#FF8A3D` | `!` |
| Caducada | rayado rojo oscuro | `×` |
| Reservada para picking | `#1B2D5E` con borde claro | `R` |

Reglas generales: zonas táctiles de al menos 44 px, foco visible con teclado, contraste de texto 4.5:1, gráficos con leyenda y valor al pasar el cursor. Los 7 días del aviso de caducidad deben ser un ajuste, no un número escrito en el código.

## Pantalla de acceso (`/login`)

Un formulario de correo y contraseña, nada más. No hay registro público: las cuentas las crea el admin. Reglas:

- Si algo falla, un único mensaje («Correo o contraseña incorrectos») sin decir cuál de los dos es. Si el servidor limita los intentos, se muestra cuándo se puede volver a probar.
- Los campos llevan `autocomplete` correcto para que el gestor de contraseñas del navegador funcione, y el botón de mostrar contraseña es un botón real con etiqueta.
- Tras entrar, cada rol aterriza en su pantalla (ver tabla de rutas). Si la cuenta tiene que cambiar la contraseña, se redirige a esa pantalla y no se puede ir a ningún otro sitio antes.
- Si la sesión caduca mientras se trabaja, se vuelve al login y, tras entrar, a la pantalla donde se estaba.

## Pantallas

Rutas y quién puede entrar. Las que no aparecen en el menú de un rol tampoco deben ser accesibles por URL, y el backend lo comprueba igualmente.

| Ruta | Pantalla | Roles |
|---|---|---|
| `/login` | Acceso | todos |
| `/` | Panel general | admin, gestor |
| `/plano` | Vista 3D del almacén | admin, gestor, producción |
| `/ordenes` | Órdenes de producción | admin, producción |
| `/recepciones` | Recepciones | admin, recepción |
| `/stock` | Stock y lotes | admin, gestor, y lectura para el resto |
| `/articulos`, `/ubicaciones`, `/usuarios` | Configuración | solo admin |
| `/conteos`, `/ajustes`, `/incidencias` | Inventario | admin, gestor |
| `/pda` | Tareas de picking en pantalla de móvil | almacén |

Al entrar, cada rol aterriza en su pantalla natural: admin y gestor en el panel, producción en órdenes, almacén en la PDA, recepción en recepciones.

## Panel general (`/`)

De arriba abajo:

1. Barra superior con el logotipo, el menú del rol y el usuario. El selector Admin/Gestor del prototipo es solo para la maqueta; en la aplicación real el rol viene del login.
2. Cuatro cifras del día: lotes que caducan en 7 días o menos, órdenes en curso, tareas de picking pendientes e incidencias abiertas.
3. Estantería en alzado: selector de pasillo con su ocupación, rejilla de 10 bahías por 3 niveles, leyenda, y debajo el detalle de la posición pulsada (artículo, lote, caducidad, disponible de total). Es el sustituto 2D de la vista 3D. Tiene un **control de densidad** (compacta/ampliada): en ampliada cada celda muestra el código de artículo y el disponible sin tener que pulsar, con scroll horizontal si no cabe en la pantalla. Las posiciones con **stock reservado** llevan una marca `R` con la cantidad.
4. «Salen primero»: los lotes que toca consumir antes, en orden FEFO. Un lote caducado aparece el primero pero marcado para baja, no para consumo.
5. Gráfico de entradas y salidas por hora y últimos movimientos.
6. Bloque propio del rol: el gestor ve conteos y bajas pendientes de aprobar; el admin ve la actividad del día por rol.

De dónde sale cada dato (para construir los endpoints): las cifras y el orden FEFO se calculan sobre `stock` y `lotes`; la estantería sale de `ubicaciones` (pasillo, estantería, nivel) cruzada con `stock`; los movimientos y el gráfico, de `movimientos` agrupados por hora y tipo.

## Stock y lotes (`/stock`)

Lectura para todos los roles; las acciones de inventario, solo para admin y gestor.

- **Filtros**: artículo, estado de caducidad, lote y ubicación. La tabla muestra artículo, lote, ubicación, disponible de total (con la marca `R` de reservado) y estado.
- **Detalle de una posición**: al pulsar «ver posición» se muestran los lotes que contiene, con disponible, reservado y total.
- **Acciones del gestor** desde el detalle: **regularizar** (cantidad contada + motivo), **trasladar** (ubicación de destino + cantidad) y **dar de baja** (cantidad + motivo). Todas piden confirmación en dos pasos: primero se revisa el cambio (cantidad actual → nueva, destino, motivo) y luego se confirma. Va contra la API real.

## Pantalla de PDA (`/pda`)

Pendiente de diseñar. Debe ser de una sola mano en móvil: una tarea cada vez, en orden de ruta, con la ubicación destacada, el lote y la cantidad. Confirmar pide el código de ubicación, el de lote y la cantidad; si no coincide, avisa; si no hay material, permite abrir una incidencia.

## Vista 3D (`/plano`)

Fase 4. Debe usar los mismos datos y los mismos estados que la estantería 2D: una posición ocupada, una que caduca pronto, una caducada, una reservada. Pulsar una caja abre el mismo detalle de posición.
