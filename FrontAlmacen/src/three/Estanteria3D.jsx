import { Html } from '@react-three/drei'
import { estado } from '../estados'

const SEP = 1.15 // separación entre bahías / niveles
const PASO_PASILLO = 4 // separación entre pasillos (deja el hueco para andar)

// Colores del rack (docs/frontend.md): montantes azules, largueros naranjas.
const MONTANTE = '#2447d6'
const LARGUERO = '#e8590c'
const SUELO = '#cfd4da'

const ANCHO_RACK = 11.7
const PROF_RACK = 0.58

// Recrea el almacén entero: un rack por pasillo, en paralelo, con el pasillo
// (hueco) entre medias. Cada rack es una rejilla de 10 bahías por 3 niveles.
export function Estanteria3D({ posiciones, seleccion, onSeleccion }) {
  const pasillos = [...new Set(posiciones.map((p) => p.pasillo))].sort(
    (a, b) => a - b
  )

  return (
    <group>
      <Suelo pasillos={pasillos.length} />
      {pasillos.map((numero, indice) => (
        <group key={numero} position={[0, 0, indice * PASO_PASILLO]}>
          <Rack />
          <Html position={[-6.8, 2.6, 0]} center distanceFactor={14}>
            <span className="plano-label">pasillo {numero}</span>
          </Html>
          {posiciones
            .filter((p) => p.pasillo === numero)
            .map((p) => (
              <Caja
                key={p.codigo}
                posicion={p}
                seleccionada={seleccion?.codigo === p.codigo}
                onSeleccion={onSeleccion}
              />
            ))}
        </group>
      ))}
    </group>
  )
}

function Rack() {
  const postes = [
    [-5.85, PROF_RACK],
    [5.85, PROF_RACK],
    [-5.85, -PROF_RACK],
    [5.85, -PROF_RACK],
  ]
  // Un larguero bajo cada nivel y uno de remate arriba.
  const alturas = [
    -0.55 + 0 * SEP,
    -0.55 + 1 * SEP,
    -0.55 + 2 * SEP,
    -0.55 + 3 * SEP,
  ]

  return (
    <group>
      {postes.map(([x, z]) => (
        <mesh key={`p-${x}-${z}`} position={[x, 1.3, z]}>
          <boxGeometry args={[0.14, 3.9, 0.14]} />
          <meshStandardMaterial color={MONTANTE} />
        </mesh>
      ))}
      {alturas.map((y) =>
        [PROF_RACK, -PROF_RACK].map((z) => (
          <mesh key={`l-${y}-${z}`} position={[0, y, z]}>
            <boxGeometry args={[ANCHO_RACK, 0.1, 0.14]} />
            <meshStandardMaterial color={LARGUERO} />
          </mesh>
        ))
      )}
    </group>
  )
}

function Caja({ posicion, seleccionada, onSeleccion }) {
  const info = estado(posicion.estado)
  const libre = posicion.estado === 'libre'
  const x = (posicion.estanteria - 5.5) * SEP
  const y = (posicion.nivel - 1) * SEP

  return (
    <mesh
      position={[x, y, 0]}
      onClick={(evento) => {
        evento.stopPropagation()
        onSeleccion(posicion)
      }}
    >
      <boxGeometry args={[0.9, 0.9, 0.9]} />
      <meshStandardMaterial
        color={info.color}
        transparent={libre}
        opacity={libre ? 0.1 : 1}
        emissive={info.color}
        emissiveIntensity={seleccionada ? 0.6 : 0}
      />
    </mesh>
  )
}

function Suelo({ pasillos }) {
  const z = ((pasillos - 1) * PASO_PASILLO) / 2
  return (
    <mesh position={[0, -0.75, z]} rotation={[-Math.PI / 2, 0, 0]}>
      <planeGeometry args={[20, pasillos * PASO_PASILLO + 5]} />
      <meshStandardMaterial color={SUELO} />
    </mesh>
  )
}
