import { Html } from '@react-three/drei'
import { estado } from '../estados'

const SEP = 1.15 // separación entre bahías / niveles
const PASO_PASILLO = 4 // separación entre pasillos (deja el hueco para andar)

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
          <Html position={[-6.6, 2.4, 0]} center distanceFactor={14}>
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
      <boxGeometry args={[1, 1, 1]} />
      <meshStandardMaterial
        color={info.color}
        transparent={libre}
        opacity={libre ? 0.12 : 1}
        emissive={info.color}
        emissiveIntensity={seleccionada ? 0.6 : 0}
      />
    </mesh>
  )
}

function Suelo({ pasillos }) {
  const z = ((pasillos - 1) * PASO_PASILLO) / 2
  return (
    <mesh position={[0, -0.65, z]} rotation={[-Math.PI / 2, 0, 0]}>
      <planeGeometry args={[18, pasillos * PASO_PASILLO + 4]} />
      <meshStandardMaterial color="#d2d7dd" />
    </mesh>
  )
}
