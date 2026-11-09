// Menú y rutas por rol (docs/frontend.md). La seguridad real está en el backend;
// esto solo decide qué se ofrece en el menú y a qué URL llega cada rol.

export const ROLES = {
  admin: 'admin',
  gestor: 'gestor_inventario',
  produccion: 'operador_produccion',
  almacen: 'operador_almacen',
  recepcion: 'operador_recepcion',
}

export const NOMBRE_ROL = {
  admin: 'administrador',
  gestor_inventario: 'gestor de inventario',
  operador_produccion: 'operador de producción',
  operador_almacen: 'operador de almacén',
  operador_recepcion: 'operador de recepción',
}

// Cada entrada: ruta, etiqueta y roles que la ven.
export const MENU = [
  { ruta: '/', etiqueta: 'panel', roles: ['admin', 'gestor_inventario'] },
  {
    ruta: '/plano',
    etiqueta: 'plano 3d',
    roles: ['admin', 'gestor_inventario', 'operador_produccion'],
  },
  {
    ruta: '/ordenes',
    etiqueta: 'órdenes',
    roles: ['admin', 'operador_produccion'],
  },
  {
    ruta: '/recepciones',
    etiqueta: 'recepciones',
    roles: ['admin', 'operador_recepcion'],
  },
  {
    ruta: '/stock',
    etiqueta: 'stock',
    roles: [
      'admin',
      'gestor_inventario',
      'operador_produccion',
      'operador_almacen',
      'operador_recepcion',
    ],
  },
  { ruta: '/usuarios', etiqueta: 'usuarios', roles: ['admin'] },
  { ruta: '/pda', etiqueta: 'pda', roles: ['operador_almacen'] },
]

const INICIO = {
  admin: '/',
  gestor_inventario: '/',
  operador_produccion: '/ordenes',
  operador_almacen: '/pda',
  operador_recepcion: '/recepciones',
}

export function inicioDe(rol) {
  return INICIO[rol] || '/'
}

export function menuDe(rol) {
  return MENU.filter((entrada) => entrada.roles.includes(rol))
}
