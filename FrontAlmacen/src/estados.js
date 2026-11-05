// Estados de una posición de estantería, en un solo sitio: la estantería 2D y
// la vista 3D (fase 4) los pintan igual. Cada estado lleva símbolo además de
// color, para no depender solo del tono.
export const ESTADOS = {
  libre: { etiqueta: 'libre', simbolo: '', clase: 'pos-libre' },
  ocupada: { etiqueta: 'ocupada', simbolo: '', clase: 'pos-ocupada' },
  caduca_pronto: {
    etiqueta: 'caduca pronto',
    simbolo: '!',
    clase: 'pos-pronto',
  },
  caducada: { etiqueta: 'caducada', simbolo: '×', clase: 'pos-caducada' },
  reservada: { etiqueta: 'reservada', simbolo: 'R', clase: 'pos-reservada' },
}

export function estado(clave) {
  return ESTADOS[clave] || ESTADOS.libre
}
