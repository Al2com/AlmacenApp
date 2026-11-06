// Medidas del almacén, en un único sitio y compartidas por la vista 2D y la 3D.
// Cambiar estos valores ajusta ambas vistas sin tocar los componentes.
export const ALMACEN = {
  bahias: 10, // bahías por estantería
  niveles: 3, // niveles por estantería
  anchoBahia: 1.15, // ancho de cada bahía (3D, eje X)
  alturaNivel: 1.15, // altura de cada nivel (3D, eje Y)
  fondoEstanteria: 1.2, // fondo de la estantería (3D, eje Z)
  anchoPasillo: 3.4, // hueco entre estanterías (pasillo), a escala realista
  alturaMaxAlcanzable: 2.0, // metros desde el suelo; por encima hace falta escalera
}

// Listas para las rejillas (2D y 3D).
export const BAHIAS = Array.from({ length: ALMACEN.bahias }, (_, i) => i + 1)
// De arriba abajo: [3, 2, 1].
export const NIVELES = Array.from(
  { length: ALMACEN.niveles },
  (_, i) => ALMACEN.niveles - i
)

// Derivados para la 3D.
export const CENTRO_BAHIA = (ALMACEN.bahias + 1) / 2
export const PASO_PASILLO = ALMACEN.fondoEstanteria + ALMACEN.anchoPasillo
export const PROF_RACK = ALMACEN.fondoEstanteria / 2
export const ANCHO_RACK = ALMACEN.bahias * ALMACEN.anchoBahia + 0.2
export const POSTE_X = ANCHO_RACK / 2

// El suelo queda algo por debajo del nivel 1 (donde se apoyan las cajas), para
// que se vea una base bajo la estantería.
export const ALTURA_SUELO = -0.75

// Altura (eje Y, relativa a la caja del nivel 1) de la línea de alcance sin
// escalera, a partir de la medida real en metros desde el suelo.
export const Y_ALTURA_ALCANZABLE = ALMACEN.alturaMaxAlcanzable + ALTURA_SUELO

// Metros reales desde el suelo a las cajas de un nivel (1, 2, 3…).
export function alturaSobreSuelo(nivel) {
  return (nivel - 1) * ALMACEN.alturaNivel - ALTURA_SUELO
}

// Si ese nivel queda por encima de lo alcanzable sin escalera.
export function necesitaEscalera(nivel) {
  return alturaSobreSuelo(nivel) > ALMACEN.alturaMaxAlcanzable
}
