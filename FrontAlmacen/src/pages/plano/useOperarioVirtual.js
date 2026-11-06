import { useCallback, useEffect, useRef, useState } from 'react'
import * as demoApi from '../../api/demostracion'
import {
  ALMACEN,
  CENTRO_BAHIA,
  PASO_PASILLO,
  PROF_RACK,
  necesitaEscalera,
} from '../../config/almacen'

const SX = ALMACEN.anchoBahia
const SY = ALMACEN.alturaNivel

// Duraciones base (ms) de cada fase caminada, antes de aplicar la velocidad.
// Se pausa solo entre fases (no a mitad de una), para no complicar el reloj.
const DURACION = {
  yendo: 1800,
  subiendo: 900,
  cogiendo: 700,
  bajando: 900,
  volviendo: 1800,
  dejando: 500,
}

/**
 * Orquesta al operario virtual: pide la siguiente tarea real, la «camina» (o
 * no, si se respeta prefers-reduced-motion) y confirma con la API real en el
 * momento exacto de dejar la caja en el carro. Nunca toca el stock por su
 * cuenta: todo el cambio pasa por ConfirmarTarea en el backend.
 */
export function useOperarioVirtual({
  posiciones,
  pasillos,
  zCamara,
  onConfirmado,
  reducida,
}) {
  const [activa, setActiva] = useState(false)
  const [pausada, setPausada] = useState(false)
  const [velocidad, setVelocidad] = useState(1)
  const [fase, setFase] = useState('parado')
  const [tarea, setTarea] = useState(null)
  const [mensaje, setMensaje] = useState('')

  const pausadaRef = useRef(pausada)
  useEffect(() => {
    pausadaRef.current = pausada
  }, [pausada])
  const corridaRef = useRef(0)

  const carro = { x: 0, z: zCamara + 1.8 }

  const destinoDeTarea = useCallback(
    (t) => {
      if (!t) return null
      const posicion = posiciones.find((p) => p.codigo === t.ubicacion)
      const yCaja = (t.nivel - 1) * SY

      if (posicion?.refrigerado) {
        return { x: 0, z: zCamara, yCaja, escalera: false }
      }

      const indice = pasillos.indexOf(t.pasillo)
      return {
        x: (t.estanteria - CENTRO_BAHIA) * SX,
        z: indice * PASO_PASILLO + PROF_RACK + 0.7,
        yCaja,
        escalera: necesitaEscalera(t.nivel),
      }
    },
    [posiciones, pasillos, zCamara]
  )

  // Espera `ms` (ajustados por velocidad); si está en pausa al cumplirse el
  // plazo, no pasa de fase hasta que se reanude.
  const esperar = useCallback((ms) => {
    return new Promise((resolve) => {
      const comprobar = () => {
        if (pausadaRef.current) {
          setTimeout(comprobar, 200)
        } else {
          resolve()
        }
      }
      setTimeout(comprobar, ms)
    })
  }, [])

  const ciclo = useCallback(
    async (miCorrida) => {
      const inicial = await demoApi.estado()
      let actual = inicial.tarea
      let pendientes = inicial.pendientes

      while (actual && miCorrida === corridaRef.current) {
        setTarea(actual)
        const destino = destinoDeTarea(actual)

        if (!reducida) {
          setFase('yendo')
          await esperar(DURACION.yendo / velocidad)
          if (miCorrida !== corridaRef.current) return

          if (destino.escalera) {
            setFase('subiendo')
            await esperar(DURACION.subiendo / velocidad)
            if (miCorrida !== corridaRef.current) return
          }

          setFase('cogiendo')
          await esperar(DURACION.cogiendo / velocidad)
          if (miCorrida !== corridaRef.current) return

          if (destino.escalera) {
            setFase('bajando')
            await esperar(DURACION.bajando / velocidad)
            if (miCorrida !== corridaRef.current) return
          }

          setFase('volviendo')
          await esperar(DURACION.volviendo / velocidad)
          if (miCorrida !== corridaRef.current) return
        }

        setFase('dejando')
        // Momento exacto del cambio de estado: se confirma aquí, nunca antes.
        const resultado = await demoApi.confirmar()
        onConfirmado()
        if (miCorrida !== corridaRef.current) return

        // En modo sin movimiento no hay paseo, pero se deja una pausa breve
        // para que se note cada cambio, en vez de confirmarlo todo de golpe.
        await esperar((reducida ? 500 : DURACION.dejando) / velocidad)
        if (miCorrida !== corridaRef.current) return

        actual = resultado.siguiente
        pendientes = resultado.pendientes
      }

      if (miCorrida === corridaRef.current) {
        setFase('terminado')
        setTarea(null)
        setMensaje(pendientes === 0 ? 'No quedan tareas pendientes.' : '')
        setActiva(false)
      }
    },
    [destinoDeTarea, velocidad, reducida, onConfirmado, esperar]
  )

  const iniciar = useCallback(() => {
    corridaRef.current += 1
    setActiva(true)
    setPausada(false)
    setMensaje('')
    ciclo(corridaRef.current)
  }, [ciclo])

  const detener = useCallback(() => {
    corridaRef.current += 1
    setActiva(false)
    setPausada(false)
    setFase('parado')
    setTarea(null)
  }, [])

  const reiniciar = useCallback(() => {
    corridaRef.current += 1
    setPausada(false)
    setActiva(true)
    setMensaje('')
    ciclo(corridaRef.current)
  }, [ciclo])

  const alternarPausa = useCallback(() => setPausada((p) => !p), [])

  const acelerar = useCallback(
    () => setVelocidad((v) => (v >= 4 ? 1 : v * 2)),
    []
  )

  // Al desmontar (salir del plano), corta cualquier ciclo en marcha.
  useEffect(
    () => () => {
      corridaRef.current += 1
    },
    []
  )

  return {
    activa,
    pausada,
    velocidad,
    fase,
    tarea,
    mensaje,
    destino: destinoDeTarea(tarea),
    carro,
    iniciar,
    detener,
    reiniciar,
    alternarPausa,
    acelerar,
  }
}
