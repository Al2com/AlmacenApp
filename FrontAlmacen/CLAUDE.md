# FrontAlmacen

React + Vite, con Three.js para la vista 3D. Contexto general en `../CLAUDE.md`; seguridad en `../docs/seguridad.md`; diseño y pantallas en `../docs/frontend.md`; orden de trabajo en `../docs/plan.md`.

## Estructura prevista

- `src/api/`: todas las llamadas a la API, una función por endpoint. Los componentes no construyen URLs.
- `src/pages/`: una carpeta por pantalla (panel, órdenes, recepciones, PDA...).
- `src/components/`: piezas reutilizables, como la rejilla de estantería o el gráfico de horas.
- `src/auth/`: sesión, token y rutas protegidas por rol.
- `src/three/`: la escena 3D, separada del resto para que no entre en el resto del código.

## Reglas

- La sesión va en una cookie HttpOnly que gestiona el navegador. No se guardan tokens, contraseñas ni datos personales en `localStorage` ni en `sessionStorage`.
- Nada de `dangerouslySetInnerHTML`. Todo lo que viene de la API se pinta como texto.
- El rol decide qué menú y qué rutas se ven, pero la seguridad real está en el backend. Ocultar un botón no protege nada.
- Los colores, la tipografía y los estados de una posición salen de las variables CSS definidas según `docs/frontend.md`. No se escriben colores sueltos en los componentes.
- Todo lo que sea de color lleva también un símbolo o texto: nadie debe depender solo del tono para entender un estado.
- Zonas táctiles de al menos 44 px, foco visible y etiquetas de accesibilidad en los botones que solo llevan icono.
- Los estados de caducidad (libre, ocupada, caduca pronto, caducada, reservada) se calculan en un solo sitio y se usan igual en la estantería 2D y en la vista 3D.

## Comandos

Desde `FrontAlmacen/`.

- Instalar dependencias: `npm install`
- Arrancar en desarrollo: `npm run dev` (http://127.0.0.1:5173)
- Compilar para producción: `npm run build` · previsualizar: `npm run preview`
- Linter (ESLint): `npm run lint`
- Formato (Prettier): `npm run format` · solo comprobar: `npm run format:check`
