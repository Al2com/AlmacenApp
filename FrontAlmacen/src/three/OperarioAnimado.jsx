import { useFrame } from '@react-three/fiber'
import { useRef } from 'react'
import * as THREE from 'three'
import { VEL_BASE } from '../pages/plano/rutaOperario'

const COLOR_PIEL = '#e0a878'
const COLOR_CHALECO = '#eab308'
const COLOR_ROPA = '#1f3350'
const COLOR_CASCO = '#ff8a3d'
const COLOR_PALET = '#b07b3f'
const COLOR_TRANSPALET = '#46546a'
const COLOR_ESCALERA = '#9aa3ad'
const COLOR_CAJA = '#c9a24b'

// Avanza `actual` hacia `destino` a ritmo constante (unidades por fotograma).
function avanzar(actual, destino, paso) {
  const d = destino - actual
  if (Math.abs(d) <= paso) return destino
  return actual + Math.sign(d) * paso
}

// Gira `actual` hacia `destino` por el camino corto (ángulos en radianes).
function girar(actual, destino, paso) {
  let d = destino - actual
  while (d > Math.PI) d -= 2 * Math.PI
  while (d < -Math.PI) d += 2 * Math.PI
  if (Math.abs(d) <= paso) return destino
  return actual + Math.sign(d) * paso
}

/**
 * El operario virtual del modo demostración: una figura con casco y chaleco que
 * empuja un transpalé. Avanza siguiendo los puntos de ruta del hook (por los
 * pasillos, nunca a través de las estanterías), se orienta hacia donde camina y
 * mueve las piernas al andar. Las cajas que coge se apilan sobre el palé.
 * Puramente visual: el cambio real de stock lo hace la API.
 */
export function OperarioAnimado({
  fase,
  objetivo,
  velocidad,
  cargadas,
  escalera,
  inicio,
  posicionRef,
}) {
  const grupo = useRef()
  const figura = useRef()
  const piernaIzq = useRef()
  const piernaDer = useRef()
  const rumbo = useRef(0)
  const paso = useRef(0)
  const mundo = useRef(new THREE.Vector3(inicio.x, 1, inicio.z))

  useFrame((_, delta) => {
    if (!grupo.current || !objetivo) return
    const pos = grupo.current.position
    const dx = objetivo.x - pos.x
    const dz = objetivo.z - pos.z
    const resto = Math.hypot(dx, dz)
    const andando = fase === 'andando' && resto > 0.02

    // Orientarse hacia donde se camina (el +Z local es el frente).
    if (andando) {
      rumbo.current = girar(rumbo.current, Math.atan2(dx, dz), delta * 6)
    }
    grupo.current.rotation.y = rumbo.current

    // Avanzar en el plano a ritmo constante.
    const avance = VEL_BASE * velocidad * delta
    pos.x = avanzar(pos.x, objetivo.x, avance)
    pos.z = avanzar(pos.z, objetivo.z, avance)

    // Subir o bajar de la escalera (algo más lento).
    if (figura.current) {
      figura.current.position.y = avanzar(
        figura.current.position.y,
        objetivo.y,
        VEL_BASE * 0.7 * velocidad * delta
      )
    }

    // Balanceo de piernas al andar; si no, se enderezan.
    if (andando) {
      paso.current += delta * velocidad * 9
    }
    const balanceo = andando ? Math.sin(paso.current) * 0.5 : 0
    if (piernaIzq.current) piernaIzq.current.rotation.x = balanceo
    if (piernaDer.current) piernaDer.current.rotation.x = -balanceo

    mundo.current.set(pos.x, 1, pos.z)
    if (posicionRef) posicionRef.current = mundo.current
  })

  const enEscalera =
    escalera &&
    (fase === 'subiendo' || fase === 'bajando' || fase === 'cogiendo')

  return (
    <>
      <group ref={grupo} position={[inicio.x, 0, inicio.z]}>
        <Transpalet cargadas={cargadas} />
        <group ref={figura}>
          <Figura
            inclinado={fase === 'cogiendo'}
            piernaIzq={piernaIzq}
            piernaDer={piernaDer}
          />
        </group>
      </group>
      {enEscalera && (
        <Escalera x={escalera.x} z={escalera.z} altura={escalera.altura} />
      )}
    </>
  )
}

/**
 * Figura humana sencilla, de pie detrás del transpalé (hacia -Z) con los brazos
 * hacia el timón. Las piernas cuelgan de sus pivotes para poder balancearlas.
 */
function Figura({ inclinado, piernaIzq, piernaDer }) {
  return (
    <group position={[0, 0, -0.2]} rotation={[inclinado ? 0.25 : 0, 0, 0]}>
      <group ref={piernaIzq} position={[-0.1, 0.46, 0]}>
        <mesh position={[0, -0.23, 0]}>
          <boxGeometry args={[0.13, 0.46, 0.15]} />
          <meshStandardMaterial color={COLOR_ROPA} />
        </mesh>
      </group>
      <group ref={piernaDer} position={[0.1, 0.46, 0]}>
        <mesh position={[0, -0.23, 0]}>
          <boxGeometry args={[0.13, 0.46, 0.15]} />
          <meshStandardMaterial color={COLOR_ROPA} />
        </mesh>
      </group>

      <mesh position={[0, 0.7, 0]}>
        <boxGeometry args={[0.36, 0.5, 0.22]} />
        <meshStandardMaterial color={COLOR_CHALECO} />
      </mesh>

      <mesh position={[-0.24, 0.66, 0.18]} rotation={[1.0, 0, 0]}>
        <boxGeometry args={[0.09, 0.42, 0.09]} />
        <meshStandardMaterial color={COLOR_CHALECO} />
      </mesh>
      <mesh position={[0.24, 0.66, 0.18]} rotation={[1.0, 0, 0]}>
        <boxGeometry args={[0.09, 0.42, 0.09]} />
        <meshStandardMaterial color={COLOR_CHALECO} />
      </mesh>

      <mesh position={[0, 1.02, 0]}>
        <sphereGeometry args={[0.13, 14, 14]} />
        <meshStandardMaterial color={COLOR_PIEL} />
      </mesh>
      <mesh position={[0, 1.08, 0]}>
        <sphereGeometry args={[0.15, 14, 14, 0, Math.PI * 2, 0, Math.PI / 2]} />
        <meshStandardMaterial color={COLOR_CASCO} />
      </mesh>
    </group>
  )
}

/**
 * Transpalé con su palé por delante del operario (hacia +Z), con el timón hacia
 * las manos. Las cajas cargadas se apilan encima (se topa la pila para no hacer
 * una torre).
 */
function Transpalet({ cargadas }) {
  const pila = Math.min(cargadas, 5)
  return (
    <group position={[0, 0, 0.55]}>
      <mesh position={[0, 0.1, 0]}>
        <boxGeometry args={[0.72, 0.14, 0.95]} />
        <meshStandardMaterial color={COLOR_PALET} />
      </mesh>
      {/* timón: barra inclinada del transpalé hacia las manos del operario */}
      <mesh position={[0, 0.42, -0.55]} rotation={[-0.5, 0, 0]}>
        <boxGeometry args={[0.1, 0.7, 0.08]} />
        <meshStandardMaterial color={COLOR_TRANSPALET} />
      </mesh>
      {Array.from({ length: pila }, (_, i) => i).map((i) => (
        <mesh key={i} position={[0, 0.33 + i * 0.26, 0]}>
          <boxGeometry args={[0.52, 0.24, 0.62]} />
          <meshStandardMaterial color={COLOR_CAJA} />
        </mesh>
      ))}
    </group>
  )
}

function Escalera({ x, z, altura }) {
  return (
    <group position={[x, 0, z + 0.35]}>
      <mesh position={[0, altura / 2, 0]} rotation={[0.15, 0, 0]}>
        <boxGeometry args={[0.45, altura, 0.06]} />
        <meshStandardMaterial color={COLOR_ESCALERA} wireframe />
      </mesh>
    </group>
  )
}
