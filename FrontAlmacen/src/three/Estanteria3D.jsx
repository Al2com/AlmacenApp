import { Html } from '@react-three/drei'
import {
  ALMACEN,
  ANCHO_RACK,
  CENTRO_BAHIA,
  PASO_PASILLO,
  POSTE_X,
  PROF_RACK,
} from '../config/almacen'
import { estado } from '../estados'

const SX = ALMACEN.anchoBahia // separación entre bahías (eje X)
const SY = ALMACEN.alturaNivel // altura de cada nivel (eje Y)
const ETIQUETA_X = -(ANCHO_RACK / 2 + 1) // posición de la etiqueta del pasillo

// Colores del rack (docs/frontend.md): montantes azules, largueros naranjas.
const MONTANTE = '#2447d6'
const LARGUERO = '#e8590c'
const SUELO = '#cfd4da'
const CRISTAL = '#bfe3ef'

// Recrea el almacén: un rack por pasillo en paralelo, con el hueco del pasillo
// entre medias, y una cámara refrigerada (sala de cristal con su propia
// estantería pequeña) al fondo para los productos que necesitan frío.
export function Estanteria3D({ posiciones, seleccion, onSeleccion }) {
  const normales = posiciones.filter((p) => !p.refrigerado)
  const refrigeradas = posiciones.filter((p) => p.refrigerado)
  const pasillos = [...new Set(normales.map((p) => p.pasillo))].sort(
    (a, b) => a - b
  )
  const zCamara = pasillos.length * PASO_PASILLO + 1.5

  return (
    <group>
      <Suelo hastaZ={zCamara} />

      {pasillos.map((numero, indice) => (
        <group key={numero} position={[0, 0, indice * PASO_PASILLO]}>
          <Rack />
          <Html position={[ETIQUETA_X, 2.6, 0]} center distanceFactor={14}>
            <span className="plano-label">pasillo {numero}</span>
          </Html>
          {normales
            .filter((p) => p.pasillo === numero)
            .map((p) => (
              <Caja
                key={p.codigo}
                posicion={p}
                x={(p.estanteria - CENTRO_BAHIA) * SX}
                y={(p.nivel - 1) * SY}
                seleccionada={seleccion?.codigo === p.codigo}
                onSeleccion={onSeleccion}
              />
            ))}
        </group>
      ))}

      {refrigeradas.length > 0 && (
        <group position={[0, 0, zCamara]}>
          <CamaraRefrigerada
            posiciones={refrigeradas}
            seleccion={seleccion}
            onSeleccion={onSeleccion}
          />
        </group>
      )}
    </group>
  )
}

function Rack({
  postX = POSTE_X,
  ancho = ANCHO_RACK,
  niveles = ALMACEN.niveles,
  prof = PROF_RACK,
}) {
  const postes = [
    [-postX, prof],
    [postX, prof],
    [-postX, -prof],
    [postX, -prof],
  ]
  const alturas = Array.from({ length: niveles + 1 }, (_, i) => -0.55 + i * SY)
  const alto = niveles * SY + 1
  const postY = -0.55 + (niveles * SY) / 2

  return (
    <group>
      {postes.map(([x, z]) => (
        <mesh key={`p-${x}-${z}`} position={[x, postY, z]}>
          <boxGeometry args={[0.14, alto, 0.14]} />
          <meshStandardMaterial color={MONTANTE} />
        </mesh>
      ))}
      {alturas.map((y) =>
        [prof, -prof].map((z) => (
          <mesh key={`l-${y}-${z}`} position={[0, y, z]}>
            <boxGeometry args={[ancho, 0.1, 0.14]} />
            <meshStandardMaterial color={LARGUERO} />
          </mesh>
        ))
      )}
    </group>
  )
}

function CamaraRefrigerada({ posiciones, seleccion, onSeleccion }) {
  const ests = posiciones.map((p) => p.estanteria)
  const minE = Math.min(...ests)
  const maxE = Math.max(...ests)
  const centroE = (minE + maxE) / 2
  const celdas = maxE - minE + 1
  const maxN = Math.max(...posiciones.map((p) => p.nivel))

  const ancho = celdas * SX
  const prof = 0.5
  const salaW = ancho + 1.4
  const salaH = maxN * SY + 1.6
  const salaD = 2 * prof + 1.4
  const salaY = -0.55 + (maxN * SY) / 2 + 0.1

  return (
    <group>
      {/* Sala de cristal que encierra la estantería refrigerada. */}
      <mesh position={[0, salaY, 0]}>
        <boxGeometry args={[salaW, salaH, salaD]} />
        <meshStandardMaterial
          color={CRISTAL}
          transparent
          opacity={0.16}
          depthWrite={false}
        />
      </mesh>
      <Html
        position={[0, salaY + salaH / 2 + 0.3, 0]}
        center
        distanceFactor={14}
      >
        <span className="plano-label frio">❄ cámara refrigerada</span>
      </Html>

      <Rack
        postX={ancho / 2 + 0.3}
        ancho={ancho + 0.4}
        niveles={maxN}
        prof={prof}
      />

      {posiciones.map((p) => (
        <Caja
          key={p.codigo}
          posicion={p}
          x={(p.estanteria - centroE) * SX}
          y={(p.nivel - 1) * SY}
          seleccionada={seleccion?.codigo === p.codigo}
          onSeleccion={onSeleccion}
        />
      ))}
    </group>
  )
}

function Caja({ posicion, x, y, seleccionada, onSeleccion }) {
  const info = estado(posicion.estado)
  const libre = posicion.estado === 'libre'

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

function Suelo({ hastaZ }) {
  return (
    <mesh position={[0, -0.75, hastaZ / 2]} rotation={[-Math.PI / 2, 0, 0]}>
      <planeGeometry args={[20, hastaZ + 5]} />
      <meshStandardMaterial color={SUELO} />
    </mesh>
  )
}
