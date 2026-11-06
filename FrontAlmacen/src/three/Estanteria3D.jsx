import { Html, Instance, Instances } from '@react-three/drei'
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
const ETIQUETA_X = -(ANCHO_RACK / 2 + 1)

// Colores del rack (docs/frontend.md): montantes azules, largueros naranjas.
const MONTANTE = '#2447d6'
const LARGUERO = '#e8590c'
const SUELO = '#cfd4da'
const CRISTAL = '#bfe3ef'

// Recrea el almacén: un rack por pasillo en paralelo, con el hueco del pasillo
// entre medias, y una cámara refrigerada al fondo. Las cajas se dibujan con
// instancias (InstancedMesh) para ir fluidas con cientos de posiciones.
export function Estanteria3D({
  posiciones,
  seleccion,
  resaltada,
  onSeleccion,
}) {
  const normales = posiciones.filter((p) => !p.refrigerado)
  const refrigeradas = posiciones.filter((p) => p.refrigerado)
  const pasillos = [...new Set(normales.map((p) => p.pasillo))].sort(
    (a, b) => a - b
  )
  const zCamara = pasillos.length * PASO_PASILLO + 1.5
  const camara = refrigeradas.length
    ? construirCamara(refrigeradas, zCamara)
    : null

  const cajas = []
  pasillos.forEach((numero, indice) => {
    const z = indice * PASO_PASILLO
    normales
      .filter((p) => p.pasillo === numero && p.estado !== 'libre')
      .forEach((p) => {
        cajas.push({
          posicion: p,
          pos: [(p.estanteria - CENTRO_BAHIA) * SX, (p.nivel - 1) * SY, z],
          color: estado(p.estado).color,
        })
      })
  })
  if (camara) {
    cajas.push(...camara.cajas)
  }

  return (
    <group>
      <Suelo hastaZ={zCamara} />

      {pasillos.map((numero, indice) => (
        <group key={numero} position={[0, 0, indice * PASO_PASILLO]}>
          <Rack />
          <Html position={[ETIQUETA_X, 2.6, 0]} center distanceFactor={14}>
            <span className="plano-label">pasillo {numero}</span>
          </Html>
        </group>
      ))}

      {camara && <SalaFria info={camara} />}

      <Cajas
        cajas={cajas}
        seleccion={seleccion}
        resaltada={resaltada}
        onSeleccion={onSeleccion}
      />
    </group>
  )
}

function Cajas({ cajas, seleccion, resaltada, onSeleccion }) {
  if (cajas.length === 0) {
    return null
  }

  return (
    <Instances limit={cajas.length} range={cajas.length}>
      <boxGeometry args={[0.9, 0.9, 0.9]} />
      <meshStandardMaterial />
      {cajas.map((caja) => {
        const escala =
          resaltada === caja.posicion.codigo
            ? 1.3
            : seleccion?.codigo === caja.posicion.codigo
              ? 1.12
              : 1
        return (
          <Instance
            key={caja.posicion.codigo}
            position={caja.pos}
            color={caja.color}
            scale={escala}
            onClick={(evento) => {
              evento.stopPropagation()
              onSeleccion(caja.posicion)
            }}
          />
        )
      })}
    </Instances>
  )
}

function construirCamara(refrigeradas, zCamara) {
  const ests = refrigeradas.map((p) => p.estanteria)
  const minE = Math.min(...ests)
  const maxE = Math.max(...ests)
  const centroE = (minE + maxE) / 2
  const celdas = maxE - minE + 1
  const maxN = Math.max(...refrigeradas.map((p) => p.nivel))
  const ancho = celdas * SX

  const cajas = refrigeradas
    .filter((p) => p.estado !== 'libre')
    .map((p) => ({
      posicion: p,
      pos: [(p.estanteria - centroE) * SX, (p.nivel - 1) * SY, zCamara],
      color: estado(p.estado).color,
    }))

  return {
    zCamara,
    ancho,
    maxN,
    cajas,
    salaW: ancho + 1.4,
    salaH: maxN * SY + 1.6,
    salaD: 2 * 0.5 + 1.4,
    salaY: -0.55 + (maxN * SY) / 2 + 0.1,
  }
}

function SalaFria({ info }) {
  return (
    <group position={[0, 0, info.zCamara]}>
      <mesh position={[0, info.salaY, 0]}>
        <boxGeometry args={[info.salaW, info.salaH, info.salaD]} />
        <meshStandardMaterial
          color={CRISTAL}
          transparent
          opacity={0.16}
          depthWrite={false}
        />
      </mesh>
      <Html
        position={[0, info.salaY + info.salaH / 2 + 0.3, 0]}
        center
        distanceFactor={14}
      >
        <span className="plano-label frio">❄ cámara refrigerada</span>
      </Html>
      <Rack
        postX={info.ancho / 2 + 0.3}
        ancho={info.ancho + 0.4}
        niveles={info.maxN}
        prof={0.5}
      />
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

function Suelo({ hastaZ }) {
  return (
    <mesh position={[0, -0.75, hastaZ / 2]} rotation={[-Math.PI / 2, 0, 0]}>
      <planeGeometry args={[20, hastaZ + 5]} />
      <meshStandardMaterial color={SUELO} />
    </mesh>
  )
}
