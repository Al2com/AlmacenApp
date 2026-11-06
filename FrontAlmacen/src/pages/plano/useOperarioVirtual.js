import { useCallback, useEffect, useRef, useState } from 'react'
import * as demoApi from '../../api/demostracion'
import { VEL_BASE, X_CARRIL, destinoDeTarea, rutaEntre } from './rutaOperario'

// Duraciones base (ms) de las fases quietas (las caminadas se calculan según la
// distancia). Se ajustan luego por la velocidad elegida.
const DURACION = {
  subiendo: 900,
  cogiendo: 650,
  bajando: 800,
  cargando: 450,
  descargando: 1000,
}

// El operario entra por el carril, cerca de recepción.
const INICIO = { x: X_CARRIL, z: -2 }

function distancia(a, b) {
  return Math.hypot(a.x - b.x, a.z - b.z)
}

/**
 * Orquesta al operario virtual: pide la siguiente tarea real, lo lleva por los
 * pasillos (nunca a través de las estanterías) empujando un transpalé, carga la
 * caja encima y confirma con la API real en ese momento. Acumula las cajas en
 * el transpalé y solo baja a producción una vez, al terminar. Nunca toca el
 * stock por su cuenta: todo el cambio pasa por ConfirmarTarea en el backend.
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
  const [objetivo, setObjetivo] = useState({ x: INICIO.x, y: 0, z: INICIO.z })
  const [escalera, setEscalera] = useState(null)
  const [cargadas, setCargadas] = useState(0)
  const [mensaje, setMensaje] = useState('')
  const [corrida, setCorrida] = useState(0)

  const corridaRef = useRef(0)
  const pausadaRef = useRef(pausada)
  const velocidadRef = useRef(velocidad)
  useEffect(() => {
    pausadaRef.current = pausada
  }, [pausada])
  useEffect(() => {
    velocidadRef.current = velocidad
  }, [velocidad])

  // Espera `ms`; si está en pausa al cumplirse el plazo, no pasa de fase hasta
  // que se reanude.
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
      const pos = { x: INICIO.x, z: INICIO.z }
      let llevadas = 0

      const vivo = () => miCorrida === corridaRef.current

      const andarHasta = async (destino) => {
        for (const punto of rutaEntre(pos, destino)) {
          setFase('andando')
          setObjetivo({ x: punto.x, y: 0, z: punto.z })
          await esperar(
            (distancia(pos, punto) / VEL_BASE / velocidadRef.current) * 1000
          )
          if (!vivo()) return false
          pos.x = punto.x
          pos.z = punto.z
        }
        return true
      }

      const inicial = await demoApi.estado()
      if (!vivo()) return
      let actual = inicial.tarea
      let pendientes = inicial.pendientes

      while (actual && vivo()) {
        setTarea(actual)
        const destino = destinoDeTarea(actual, posiciones, pasillos, zCamara)

        if (!reducida) {
          if (!(await andarHasta(destino))) return

          if (destino.escalera) {
            setEscalera({ x: destino.x, z: destino.z, altura: destino.yCaja })
            setFase('subiendo')
            setObjetivo({ x: destino.x, y: destino.yCaja, z: destino.z })
            await esperar(DURACION.subiendo / velocidadRef.current)
            if (!vivo()) return
          }

          setFase('cogiendo')
          setObjetivo({
            x: destino.x,
            y: destino.escalera ? destino.yCaja : 0,
            z: destino.z,
          })
          await esperar(DURACION.cogiendo / velocidadRef.current)
          if (!vivo()) return

          if (destino.escalera) {
            setFase('bajando')
            setObjetivo({ x: destino.x, y: 0, z: destino.z })
            await esperar(DURACION.bajando / velocidadRef.current)
            if (!vivo()) return
          }
          setEscalera(null)
        }

        // Momento exacto del cambio de estado: se confirma al cargar la caja en
        // el transpalé, nunca antes.
        setFase('cargando')
        const resultado = await demoApi.confirmar()
        if (!vivo()) return
        llevadas += 1
        setCargadas(llevadas)
        onConfirmado()
        await esperar(
          (reducida ? 450 : DURACION.cargando) / velocidadRef.current
        )
        if (!vivo()) return

        pos.x = destino.x
        pos.z = destino.z
        actual = resultado.siguiente
        pendientes = resultado.pendientes
      }

      if (!vivo()) return

      // Al terminar, un único viaje a producción a dejar el palé cargado.
      if (llevadas > 0 && !reducida) {
        setTarea(null)
        if (!(await andarHasta({ x: 0, z: zCamara + 2.4 }))) return
        setFase('descargando')
        await esperar(DURACION.descargando / velocidadRef.current)
        if (!vivo()) return
        setCargadas(0)
      }

      setFase('terminado')
      setTarea(null)
      setEscalera(null)
      setMensaje(
        pendientes === 0 ? 'Pedido preparado: no quedan tareas pendientes.' : ''
      )
      setActiva(false)
    },
    [posiciones, pasillos, zCamara, reducida, onConfirmado, esperar]
  )

  const arrancar = useCallback(
    (mensajeInicial) => {
      corridaRef.current += 1
      setCorrida(corridaRef.current)
      setActiva(true)
      setPausada(false)
      setMensaje(mensajeInicial)
      setCargadas(0)
      setEscalera(null)
      ciclo(corridaRef.current)
    },
    [ciclo]
  )

  const iniciar = useCallback(() => arrancar(''), [arrancar])
  const reiniciar = useCallback(() => arrancar(''), [arrancar])

  const detener = useCallback(() => {
    corridaRef.current += 1
    setActiva(false)
    setPausada(false)
    setFase('parado')
    setTarea(null)
    setEscalera(null)
  }, [])

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
    objetivo,
    escalera,
    cargadas,
    corrida,
    inicio: INICIO,
    iniciar,
    detener,
    reiniciar,
    alternarPausa,
    acelerar,
  }
}
