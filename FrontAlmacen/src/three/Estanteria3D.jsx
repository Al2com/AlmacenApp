import { estado } from '../estados'

const SEPARACION = 1.15

// Recrea en 3D las posiciones de un pasillo: cada bahía en su eje X y cada nivel
// en su eje Y, con el color del estado (el mismo de la vista 2D).
export function Estanteria3D({ posiciones, seleccion, onSeleccion }) {
  return (
    <group>
      {posiciones.map((posicion) => {
        const info = estado(posicion.estado)
        const libre = posicion.estado === 'libre'
        const seleccionada = seleccion?.codigo === posicion.codigo
        const x = (posicion.estanteria - 5.5) * SEPARACION
        const y = (posicion.nivel - 1) * SEPARACION

        return (
          <mesh
            key={posicion.codigo}
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
              opacity={libre ? 0.15 : 1}
              emissive={info.color}
              emissiveIntensity={seleccionada ? 0.6 : 0}
            />
          </mesh>
        )
      })}
    </group>
  )
}
