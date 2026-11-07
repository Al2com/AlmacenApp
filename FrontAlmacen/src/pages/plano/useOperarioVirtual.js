import { useCallback, useEffect, useRef, useState } from 'react'
import * as demoApi from '../../api/demostracion'
import { VEL_BASE, X_CARRIL, destinoDeTarea, rutaEntre } from './rutaOperario'

// Duraciones base (ms) de las fases quietas (las caminadas se calculan según la
// distancia). Se ajustan luego por la velocidad elegida.
const DURACION = {
  cogiendo: 900, // lo que tarda la bola en salir de la estantería y llegar al carro
  cargando: 350,
  descargando: 1000,
}

// El operario entra por el carril, cerca de recepción.
const INICIO = { x: X_CARRIL, z: -2 }

function distancia(a, b) {
  return Math.hypot(a.x - b.x, a.z - b.z)
}

/**
 * Orquesta al operario virtual para recrear la ruta de picking de la orden
 * activa (la pide una vez a la API). Lo lleva por el centro de los pasillos
 * hasta cada ubicación; allí sale una bola de la estantería hacia el transpalé
 * y la posición queda marcada como recogida. No confirma ni consume stock: es
 * una recreación visual, repetible.
 */
export function useOperarioVirtual({
  posiciones,
  pasillos,
  zCamara,
  reducida,
}) {
  const [activa, setActiva] = useState(false)
  const [pausada, setPausada] = useState(false)
  const [velocidad, setVelocidad] = useState(1)
  const [fase, setFase] = useState('parado')
  const [tarea, setTarea] = useState(null)
  const [tareas, setTareas] = useState([])
  const [indice, setIndice] = useState(0)
  const [orden, setOrden] = useState(null)
  const [objetivo, setObjetivo] = useState({ x: INICIO.x, z: INICIO.z })
  const [destino, setDestino] = useState(null)
  const [cargadas, setCargadas] = useState(0)
  const [visitadas, setVisitadas] = useState([])
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

      const andarHasta = async (meta) => {
        for (const punto of rutaEntre(pos, meta)) {
          setFase('andando')
          setObjetivo({ x: punto.x, z: punto.z })
          await esperar(
            (distancia(pos, punto) / VEL_BASE / velocidadRef.current) * 1000
          )
          if (!vivo()) return false
          pos.x = punto.x
          pos.z = punto.z
        }
        return true
      }

      const datos = await demoApi.ruta()
      if (!vivo()) return
      const ruta = datos.tareas ?? []
      setTareas(ruta)
      setOrden(datos.orden)

      if (ruta.length === 0) {
        setFase('terminado')
        setTarea(null)
        setMensaje(
          datos.orden
            ? 'El pedido no tiene tareas de picking que recorrer.'
            : 'No hay ninguna orden activa. Crea un pedido en «órdenes».'
        )
        setActiva(false)
        return
      }

      for (let i = 0; i < ruta.length; i++) {
        if (!vivo()) return
        const t = ruta[i]
        setIndice(i)
        setTarea(t)
        const meta = destinoDeTarea(t, posiciones, pasillos, zCamara)
        setDestino(meta)

        if (!reducida) {
          if (!(await andarHasta(meta))) return

          // La bola sale de la estantería y viaja al transpalé (lo anima el 3D).
          setFase('cogiendo')
          await esperar(DURACION.cogiendo / velocidadRef.current)
          if (!vivo()) return
        }

        // La bola llega al carro: se marca la posición como recogida (sin
        // confirmar ni consumir stock) y se apila una caja en el transpalé.
        setFase('cargando')
        llevadas += 1
        setCargadas(llevadas)
        setVisitadas((prev) =>
          prev.includes(t.ubicacion) ? prev : [...prev, t.ubicacion]
        )
        await esperar(
          (reducida ? 400 : DURACION.cargando) / velocidadRef.current
        )
        if (!vivo()) return

        pos.x = meta.x
        pos.z = meta.z
      }

      if (!vivo()) return

      // Al terminar, un único viaje a producción a dejar el palé cargado.
      if (llevadas > 0 && !reducida) {
        setTarea(null)
        setDestino(null)
        if (!(await andarHasta({ x: 0, z: zCamara + 2.4 }))) return
        setFase('descargando')
        await esperar(DURACION.descargando / velocidadRef.current)
        if (!vivo()) return
        setCargadas(0)
      }

      setFase('terminado')
      setTarea(null)
      setDestino(null)
      setMensaje('Ruta recorrida. Pulsa «reiniciar recorrido» para repetirla.')
      setActiva(false)
    },
    [posiciones, pasillos, zCamara, reducida, esperar]
  )

  const arrancar = useCallback(() => {
    corridaRef.current += 1
    setCorrida(corridaRef.current)
    setActiva(true)
    setPausada(false)
    setMensaje('')
    setCargadas(0)
    setVisitadas([])
    setDestino(null)
    setTareas([])
    setIndice(0)
    setOrden(null)
    ciclo(corridaRef.current)
  }, [ciclo])

  const iniciar = arrancar
  const reiniciar = arrancar

  const detener = useCallback(() => {
    corridaRef.current += 1
    setActiva(false)
    setPausada(false)
    setFase('parado')
    setTarea(null)
    setDestino(null)
    setVisitadas([])
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
    tareas,
    indice,
    orden,
    mensaje,
    objetivo,
    destino,
    cargadas,
    visitadas,
    corrida,
    inicio: INICIO,
    iniciar,
    detener,
    reiniciar,
    alternarPausa,
    acelerar,
  }
}
