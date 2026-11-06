import { OrbitControls } from '@react-three/drei'
import { Canvas } from '@react-three/fiber'
import { useEffect, useMemo, useState } from 'react'
import { estanteria as cargarEstanteria } from '../../api/plano'
import { DetallePosicion } from '../../components/DetallePosicion'
import { ESTADOS } from '../../estados'
import { Estanteria3D } from '../../three/Estanteria3D'

export function Plano() {
  const [posiciones, setPosiciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)
  const [pasillo, setPasillo] = useState(1)
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

  const pasillos = useMemo(
    () => [...new Set(posiciones.map((p) => p.pasillo))].sort((a, b) => a - b),
    [posiciones]
  )
  const delPasillo = useMemo(
    () => posiciones.filter((p) => p.pasillo === pasillo),
    [posiciones, pasillo]
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
        Arrastra para girar, rueda para acercar. Pulsa una caja para ver su
        detalle.
      </p>

      <div className="pasillos" role="tablist" aria-label="Pasillos">
        {pasillos.map((numero) => (
          <button
            key={numero}
            type="button"
            className={numero === pasillo ? 'activo' : undefined}
            aria-pressed={numero === pasillo}
            onClick={() => {
              setPasillo(numero)
              setSeleccion(null)
            }}
          >
            pasillo {numero}
          </button>
        ))}
      </div>

      <div className="plano-escena">
        <Canvas camera={{ position: [0, 3, 12], fov: 50 }}>
          <ambientLight intensity={0.7} />
          <directionalLight position={[5, 10, 7]} intensity={1} />
          <Estanteria3D
            posiciones={delPasillo}
            seleccion={seleccion}
            onSeleccion={setSeleccion}
          />
          <OrbitControls target={[0, 1, 0]} enablePan={false} />
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
