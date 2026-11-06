import { OrbitControls, Stats } from '@react-three/drei'
import { Canvas, useThree } from '@react-three/fiber'
import { useEffect, useMemo, useRef, useState } from 'react'
import { actividad } from '../../api/panel'
import { estanteria as cargarEstanteria } from '../../api/plano'
import { useSesion } from '../../auth/contexto'
import { DetallePosicion } from '../../components/DetallePosicion'
import { PASO_PASILLO } from '../../config/almacen'
import { ESTADOS } from '../../estados'
import { Estanteria3D } from '../../three/Estanteria3D'

export function Plano() {
  const { usuario } = useSesion()
  const esAdmin = usuario.rol === 'admin'

  const [posiciones, setPosiciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)
  const [seleccion, setSeleccion] = useState(null)
  const [foco, setFoco] = useState(null)
  const [resaltada, setResaltada] = useState(null)

  useEffect(() => {
    let activo = true

    async function cargar(inicial) {
      try {
        const datos = await cargarEstanteria()
        if (activo) {
          setPosiciones(datos)
          setError(false)
        }
      } catch {
        if (activo && inicial) setError(true)
      } finally {
        if (activo && inicial) setCargando(false)
      }
    }

    cargar(true)
    // Se refresca cada 5 s para reflejar en vivo lo que se reserva (pedidos) y
    // lo que se consume (picking en la PDA).
    const id = setInterval(() => cargar(false), 5000)
    return () => {
      activo = false
      clearInterval(id)
    }
  }, [])

  // El admin ve resaltarse la posición de cada operación que llega por el feed.
  useEffect(() => {
    if (!esAdmin) return undefined
    let activo = true
    let primera = true
    let limpiar
    const desde = { current: null }

    async function sondear() {
      try {
        const nuevos = await actividad(desde.current)
        if (!activo || nuevos.length === 0) {
          primera = false
          return
        }
        desde.current = nuevos[0].fecha
        if (!primera) {
          const conUbicacion = nuevos.find((evento) => evento.ubicacion)
          if (conUbicacion) {
            setResaltada(conUbicacion.ubicacion)
            clearTimeout(limpiar)
            limpiar = setTimeout(() => activo && setResaltada(null), 3000)
          }
        }
        primera = false
      } catch {
        // Un fallo puntual no rompe el plano.
      }
    }

    sondear()
    const id = setInterval(sondear, 5000)
    return () => {
      activo = false
      clearInterval(id)
      clearTimeout(limpiar)
    }
  }, [esAdmin])

  const pasillos = useMemo(
    () =>
      [
        ...new Set(
          posiciones.filter((p) => !p.refrigerado).map((p) => p.pasillo)
        ),
      ].sort((a, b) => a - b),
    [posiciones]
  )

  if (error) {
    return <p className="error">No se pudo cargar el plano.</p>
  }
  if (cargando) {
    return <p className="cargando">Cargando plano…</p>
  }

  return (
    <section>
      <h1>plano del almacén</h1>
      <p className="pda-progreso">
        Arrastra para girar, rueda para acercar. Pulsa una posición para ver su
        contenido. Centra un pasillo para ver el número de cada bahía.
      </p>

      <div className="pasillos" role="group" aria-label="Centrar en un pasillo">
        <button
          type="button"
          className={foco == null ? 'activo' : undefined}
          onClick={() => setFoco(null)}
        >
          todo
        </button>
        {pasillos.map((numero) => (
          <button
            key={numero}
            type="button"
            className={foco === numero ? 'activo' : undefined}
            onClick={() => setFoco(numero)}
          >
            pasillo {numero}
          </button>
        ))}
      </div>

      <div className="plano-escena">
        <Canvas camera={{ position: [13, 10, 20], fov: 50 }}>
          {import.meta.env.DEV && <Stats />}
          <ambientLight intensity={0.6} />
          <hemisphereLight args={['#ffffff', '#5b6472', 0.5]} />
          <directionalLight position={[8, 14, 10]} intensity={1} />
          <Estanteria3D
            posiciones={posiciones}
            seleccion={seleccion}
            resaltada={resaltada}
            foco={foco}
            onSeleccion={setSeleccion}
          />
          <ControlCamara foco={foco} pasillos={pasillos} />
        </Canvas>
      </div>

      <div className="leyenda">
        {Object.values(ESTADOS).map((e) => (
          <span key={e.clase}>
            <span className={`muestra ${e.clase}`} aria-hidden="true" />
            {e.etiqueta}
            {e.simbolo ? ` (${e.simbolo})` : ''}
          </span>
        ))}
      </div>

      {seleccion && <DetallePosicion posicion={seleccion} />}
    </section>
  )
}

function ControlCamara({ foco, pasillos }) {
  const ref = useRef()
  const { camera } = useThree()

  useEffect(() => {
    const control = ref.current
    if (!control) return
    const centro = (pasillos.length * PASO_PASILLO) / 2
    if (foco == null) {
      control.target.set(0, 1, centro)
      camera.position.set(15, 11, centro + 21)
    } else {
      const z = pasillos.indexOf(foco) * PASO_PASILLO
      control.target.set(0, 1, z)
      camera.position.set(9, 6, z + 10)
    }
    control.update()
  }, [foco, pasillos, camera])

  return <OrbitControls ref={ref} enablePan={false} makeDefault />
}
