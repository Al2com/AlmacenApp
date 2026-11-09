import {
  ALMACEN,
  ANCHO_RACK,
  CENTRO_BAHIA,
  PASO_PASILLO,
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

// El operario camina por el centro del pasillo (a media distancia entre dos
// estanterías), no pegado al rack.
const FRENTE = PASO_PASILLO / 2

/**
 * Para una tarea: dónde se planta el operario (en el centro del pasillo, a la
 * altura de la bahía) y dónde está la caja en la estantería (de ahí sale la
 * bola hacia el transpalé). Para la cámara refrigerada, delante de ella.
 */
export function destinoDeTarea(tarea, posiciones, pasillos, zCamara) {
  if (!tarea) return null
  const posicion = posiciones.find((p) => p.codigo === tarea.ubicacion)
  const yCaja = (tarea.nivel - 1) * SY

  if (posicion?.refrigerado) {
    return { x: 0, z: zCamara - 2, caja: [0, yCaja, zCamara] }
  }

  const indice = pasillos.indexOf(tarea.pasillo)
  const zRack = indice * PASO_PASILLO
  const x = (tarea.estanteria - CENTRO_BAHIA) * SX
  return { x, z: zRack + FRENTE, caja: [x, yCaja, zRack] }
}

/**
 * Puntos por los que pasa el operario para ir de `origen` a `destino` sin
 * cruzar las estanterías. En el mismo pasillo avanza por el centro; para
 * cambiar de pasillo sale al carril lateral, lo recorre y entra por él.
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
