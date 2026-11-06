import {
  ALMACEN,
  ANCHO_RACK,
  CENTRO_BAHIA,
  PASO_PASILLO,
  PROF_RACK,
  necesitaEscalera,
} from '../../config/almacen'

const SX = ALMACEN.anchoBahia
const SY = ALMACEN.alturaNivel

// Velocidad del paso del operario, en unidades del mundo por segundo. La usan
// tanto el reloj del ciclo (para saber cuánto dura cada tramo) como la
// animación (para moverse a ese mismo ritmo), de modo que no se desacoplan.
export const VEL_BASE = 2.6

// Carril de paso lateral, a la izquierda de todos los racks: por aquí se cambia
// de un pasillo a otro sin atravesar ninguna estantería.
export const X_CARRIL = -(ANCHO_RACK / 2 + 0.9)

// Dónde se planta el operario (en el pasillo, delante del rack) para una tarea.
const FRENTE = PROF_RACK + 0.7

/**
 * Posición de pie del operario para una tarea y la altura de la caja. Para la
 * cámara refrigerada, delante de ella al fondo.
 */
export function destinoDeTarea(tarea, posiciones, pasillos, zCamara) {
  if (!tarea) return null
  const posicion = posiciones.find((p) => p.codigo === tarea.ubicacion)
  const yCaja = (tarea.nivel - 1) * SY

  if (posicion?.refrigerado) {
    return { x: 0, z: zCamara - 1, yCaja, escalera: false }
  }

  const indice = pasillos.indexOf(tarea.pasillo)
  return {
    x: (tarea.estanteria - CENTRO_BAHIA) * SX,
    z: indice * PASO_PASILLO + FRENTE,
    yCaja,
    escalera: necesitaEscalera(tarea.nivel),
  }
}

/**
 * Puntos por los que pasa el operario para ir de `origen` a `destino` sin
 * cruzar las estanterías. En el mismo pasillo se desliza por delante de las
 * bahías; para cambiar de pasillo sale al carril lateral, lo recorre hasta la
 * altura del pasillo de destino y entra por él.
 */
export function rutaEntre(origen, destino) {
  if (Math.abs(origen.z - destino.z) < 0.05) {
    return [{ x: destino.x, z: destino.z }]
  }

  const puntos = []
  if (Math.abs(origen.x - X_CARRIL) > 0.05) {
    puntos.push({ x: X_CARRIL, z: origen.z })
  }
  puntos.push({ x: X_CARRIL, z: destino.z })
  puntos.push({ x: destino.x, z: destino.z })
  return puntos
}
