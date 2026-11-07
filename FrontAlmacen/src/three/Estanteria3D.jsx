import { Html, Instance, Instances } from '@react-three/drei'
import {
  ALMACEN,
  ALTURA_SUELO,
  ANCHO_RACK,
  CENTRO_BAHIA,
  PASO_PASILLO,
  POSTE_X,
  PROF_RACK,
  Y_ALTURA_ALCANZABLE,
} from '../config/almacen'
import { estado } from '../estados'

const SX = ALMACEN.anchoBahia // separación entre bahías (eje X)
const SY = ALMACEN.alturaNivel // altura de cada nivel (eje Y)
const ETIQUETA_X = -(ANCHO_RACK / 2 + 1)

// Colores del rack (docs/frontend.md): montantes azules, largueros naranjas.
const MONTANTE = '#2447d6'
const LARGUERO = '#e8590c'
const BALDA = '#9aa3ad'
const SUELO = '#cfd4da'
const CRISTAL = '#bfe3ef'
const ZONA_RECEPCION = '#2447d6'
const ZONA_PRODUCCION = '#e8590c'

/**
 * Recrea el almacén: un rack por pasillo en paralelo (con baldas por nivel,
 * montantes y largueros), el hueco del pasillo entre medias, rótulos de
 * pasillo/bahía/nivel, una cámara refrigerada al fondo y las zonas de
 * recepción y producción en los extremos. Las cajas se dibujan con instancias
 * (InstancedMesh) para ir fluidas con cientos de posiciones.
 */
export function Estanteria3D({
  posiciones,
  seleccion,
  resaltada,
  foco,
  visitadas = [],
  onSeleccion,
}) {
  const recogidas = new Set(visitadas)
  // Las posiciones que ya ha recorrido el operario en la demostración se pintan
  // como recogidas (no desaparecen: no se consume stock).
  const estadoDe = (p) => (recogidas.has(p.codigo) ? 'recogida' : p.estado)

  const normales = posiciones.filter((p) => !p.refrigerado)
  const refrigeradas = posiciones.filter((p) => p.refrigerado)
  const pasillos = [...new Set(normales.map((p) => p.pasillo))].sort(
    (a, b) => a - b
  )
  const zCamara = pasillos.length * PASO_PASILLO + 1.5
  const camara = refrigeradas.length
    ? construirCamara(refrigeradas, zCamara, estadoDe)
    : null

  const cajas = []
  pasillos.forEach((numero, indice) => {
    const z = indice * PASO_PASILLO
    normales
      .filter((p) => p.pasillo === numero && estadoDe(p) !== 'libre')
      .forEach((p) => {
        cajas.push({
          posicion: p,
          pos: [(p.estanteria - CENTRO_BAHIA) * SX, (p.nivel - 1) * SY, z],
          color: estado(estadoDe(p)).color,
        })
      })
  })
  if (camara) {
    cajas.push(...camara.cajas)
  }

  return (
    <group>
      <Suelo hastaZ={zCamara} numPasillos={pasillos.length} />
      <ZonaExtremo tipo="recepción" z={-2.6} color={ZONA_RECEPCION} />
      <ZonaExtremo
        tipo="producción"
        z={zCamara + 2.6}
        color={ZONA_PRODUCCION}
      />
      <LineaAlcanzable hastaZ={zCamara} />

      {pasillos.map((numero, indice) => (
        <group key={numero} position={[0, 0, indice * PASO_PASILLO]}>
          <Rack mostrarBahias={foco === numero} />
          <RotulosPasillo numero={numero} />
        </group>
      ))}

      {camara && <SalaFria info={camara} />}

      <Cajas
        cajas={cajas}
        limite={posiciones.length}
        seleccion={seleccion}
        resaltada={resaltada}
        onSeleccion={onSeleccion}
      />
    </group>
  )
}

function Cajas({ cajas, limite, seleccion, resaltada, onSeleccion }) {
  if (cajas.length === 0) {
    return null
  }

  // `limit` fijo (el total de posiciones) para que el buffer de instancias no
  // se reinicie cuando cambia el número de cajas visibles (p. ej. al marcarse
  // una posición como recogida durante la demostración).
  return (
    <Instances limit={Math.max(limite, cajas.length)} range={cajas.length}>
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

function construirCamara(refrigeradas, zCamara, estadoDe) {
  const ests = refrigeradas.map((p) => p.estanteria)
  const minE = Math.min(...ests)
  const maxE = Math.max(...ests)
  const centroE = (minE + maxE) / 2
  const celdas = maxE - minE + 1
  const maxN = Math.max(...refrigeradas.map((p) => p.nivel))
  const ancho = celdas * SX

  const cajas = refrigeradas
    .filter((p) => estadoDe(p) !== 'libre')
    .map((p) => ({
      posicion: p,
      pos: [(p.estanteria - centroE) * SX, (p.nivel - 1) * SY, zCamara],
      color: estado(estadoDe(p)).color,
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

/**
 * Montantes, largueros y una balda sólida en cada nivel (para que se
 * distinga, en vez de una simple línea). Con el foco puesto en este pasillo,
 * además numera sus bahías.
 */
function Rack({
  postX = POSTE_X,
  ancho = ANCHO_RACK,
  niveles = ALMACEN.niveles,
  prof = PROF_RACK,
  mostrarBahias = false,
}) {
  const postes = [
    [-postX, prof],
    [postX, prof],
    [-postX, -prof],
    [postX, -prof],
  ]
  const baseNivel = (n) => -0.55 + n * SY
  const alturas = Array.from({ length: niveles + 1 }, (_, i) => baseNivel(i))
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
      {/* Balda: panel sólido bajo las cajas de cada nivel, para que se note
          que es una balda y no solo dos barras. */}
      {Array.from({ length: niveles }, (_, i) => i).map((i) => (
        <mesh key={`b-${i}`} position={[0, baseNivel(i) + 0.03, 0]}>
          <boxGeometry args={[ancho, 0.06, prof * 2]} />
          <meshStandardMaterial color={BALDA} />
        </mesh>
      ))}
      {mostrarBahias &&
        Array.from({ length: ALMACEN.bahias }, (_, i) => i + 1).map((bahia) => (
          <Html
            key={`bahia-${bahia}`}
            position={[(bahia - CENTRO_BAHIA) * SX, -0.55, prof + 0.05]}
            center
            distanceFactor={8}
          >
            <span className="plano-label plano-label-bahia">
              {String(bahia).padStart(2, '0')}
            </span>
          </Html>
        ))}
      <NivelLabels postX={postX} prof={prof} niveles={niveles} />
    </group>
  )
}

function NivelLabels({ postX, prof, niveles }) {
  return (
    <>
      {Array.from({ length: niveles }, (_, i) => i + 1).map((nivel) => (
        <Html
          key={`nivel-${nivel}`}
          position={[-postX - 0.35, (nivel - 1) * SY, prof]}
          center
          distanceFactor={10}
        >
          <span className="plano-label plano-label-nivel">N{nivel}</span>
        </Html>
      ))}
    </>
  )
}

function RotulosPasillo({ numero }) {
  return (
    <>
      <Html position={[ETIQUETA_X, 2.6, 0]} center distanceFactor={14}>
        <span className="plano-label">pasillo {numero}</span>
      </Html>
      <Html position={[-ETIQUETA_X, 2.6, 0]} center distanceFactor={14}>
        <span className="plano-label">pasillo {numero}</span>
      </Html>
    </>
  )
}

function LineaAlcanzable({ hastaZ }) {
  const ancho = Math.max(ANCHO_RACK * 1.4, 6)
  return (
    <group position={[0, Y_ALTURA_ALCANZABLE, hastaZ / 2]}>
      <mesh>
        <boxGeometry args={[ancho, 0.015, hastaZ + 4]} />
        <meshStandardMaterial
          color="#ffffff"
          transparent
          opacity={0.22}
          depthWrite={false}
        />
      </mesh>
      <Html position={[ancho / 2 + 0.6, 0, 0]} center distanceFactor={16}>
        <span className="plano-label plano-label-alcance">
          alcance sin escalera
        </span>
      </Html>
    </group>
  )
}

function ZonaExtremo({ tipo, z, color }) {
  return (
    <group position={[0, ALTURA_SUELO + 0.01, z]}>
      <mesh rotation={[-Math.PI / 2, 0, 0]}>
        <planeGeometry args={[ANCHO_RACK + 1, 3.6]} />
        <meshStandardMaterial color={color} transparent opacity={0.25} />
      </mesh>
      <Html position={[0, 0.4, 0]} center distanceFactor={16}>
        <span className="plano-label">zona de {tipo}</span>
      </Html>
    </group>
  )
}

function Suelo({ hastaZ, numPasillos }) {
  return (
    <group>
      <mesh
        position={[0, ALTURA_SUELO, hastaZ / 2]}
        rotation={[-Math.PI / 2, 0, 0]}
      >
        <planeGeometry args={[20, hastaZ + 9]} />
        <meshStandardMaterial color={SUELO} />
      </mesh>
      {/* Marcas de suelo: una franja a lo largo de cada calle de paso (el
          hueco entre dos estanterías), como la pintura de un almacén real. */}
      {Array.from({ length: numPasillos + 1 }, (_, i) => i).map((i) => (
        <mesh
          key={`marca-${i}`}
          position={[
            0,
            ALTURA_SUELO + 0.005,
            i * PASO_PASILLO - PASO_PASILLO / 2,
          ]}
          rotation={[-Math.PI / 2, 0, 0]}
        >
          <planeGeometry args={[ANCHO_RACK - 1, 0.12]} />
          <meshStandardMaterial color="#e8d9a0" />
        </mesh>
      ))}
    </group>
  )
}
