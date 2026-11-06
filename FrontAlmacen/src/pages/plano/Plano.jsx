import { OrbitControls } from '@react-three/drei'
import { Canvas } from '@react-three/fiber'
import { useEffect, useMemo, useState } from 'react'
import { estanteria as cargarEstanteria } from '../../api/plano'
import { DetallePosicion } from '../../components/DetallePosicion'
import { ESTADOS } from '../../estados'
import { Estanteria3D } from '../../three/Estanteria3D'

const PASO_PASILLO = 4

export function Plano() {
  const [posiciones, setPosiciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)
  const [seleccion, setSeleccion] = useState(null)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const datos = await cargarEstanteria()
        if (activo) setPosiciones(datos)
      } catch {
        if (activo) setError(true)
      } finally {
        if (activo) setCargando(false)
      }
    })()
    return () => {
      activo = false
    }
  }, [])

  const centroZ = useMemo(() => {
    const normales = new Set(
      posiciones.filter((p) => !p.refrigerado).map((p) => p.pasillo)
    ).size
    // Centro aproximado del conjunto: pasillos + la cámara al fondo.
    return (normales * PASO_PASILLO + 1.5) / 2
  }, [posiciones])

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
        Los cuatro pasillos a la vez. Arrastra para girar, rueda para acercar.
        Pulsa una posición para ver su contenido.
      </p>

      <div className="plano-escena">
        <Canvas camera={{ position: [13, 10, centroZ + 18], fov: 50 }}>
          <ambientLight intensity={0.75} />
          <directionalLight position={[8, 14, 10]} intensity={1} />
          <Estanteria3D
            posiciones={posiciones}
            seleccion={seleccion}
            onSeleccion={setSeleccion}
          />
          <OrbitControls target={[0, 1, centroZ]} enablePan={false} />
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
