// Estados de una posición de estantería, en un solo sitio: la estantería 2D y
// la vista 3D (fase 4) los pintan igual. Cada estado lleva símbolo además de
// color, para no depender solo del tono.
// `clase` pinta la vista 2D (vía CSS) y `color` la 3D: el mismo estado, igual.
export const ESTADOS = {
  libre: {
    etiqueta: 'libre',
    simbolo: '',
    clase: 'pos-libre',
    color: '#c7cdd3',
  },
  ocupada: {
    etiqueta: 'ocupada',
    simbolo: '',
    clase: 'pos-ocupada',
    color: '#8fa6f2',
  },
  caduca_pronto: {
    etiqueta: 'caduca pronto',
    simbolo: '!',
    clase: 'pos-pronto',
    color: '#ff8a3d',
  },
  caducada: {
    etiqueta: 'caducada',
    simbolo: '×',
    clase: 'pos-caducada',
    color: '#7a1a1a',
  },
  reservada: {
    etiqueta: 'reservada',
    simbolo: 'R',
    clase: 'pos-reservada',
    color: '#1b2d5e',
  },
}

export function estado(clave) {
  return ESTADOS[clave] || ESTADOS.libre
}
