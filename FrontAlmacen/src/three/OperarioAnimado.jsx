import { useFrame } from '@react-three/fiber'
import { useRef } from 'react'
import { VEL_BASE } from '../pages/plano/rutaOperario'

const COLOR_OPERARIO = '#0e1b2e'
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

/**
 * El operario virtual del modo demostración: una figura sencilla que empuja un
 * transpalé con un palé. Se mueve siguiendo los puntos de ruta que marca el
 * hook (por los pasillos, nunca a través de las estanterías) y las cajas que
 * va cogiendo se apilan sobre el palé. Puramente visual: el cambio real de
 * stock lo hace la API, no esta animación.
 */
export function OperarioAnimado({
  fase,
  objetivo,
  velocidad,
  cargadas,
  escalera,
  inicio,
}) {
  const grupo = useRef()
  const figura = useRef()

  useFrame((_, delta) => {
    if (!grupo.current || !objetivo) return
    const pos = grupo.current.position
    const paso = VEL_BASE * velocidad * delta
    pos.x = avanzar(pos.x, objetivo.x, paso)
    pos.z = avanzar(pos.z, objetivo.z, paso)
    if (figura.current) {
      const subir = VEL_BASE * 0.7 * velocidad * delta
      figura.current.position.y = avanzar(
        figura.current.position.y,
        objetivo.y,
        subir
      )
    }
  })

  const enEscalera =
    escalera &&
    (fase === 'subiendo' || fase === 'bajando' || fase === 'cogiendo')

  return (
    <>
      <group ref={grupo} position={[inicio.x, 0, inicio.z]}>
        <Transpalet cargadas={cargadas} />
        <group ref={figura}>
          <Figura inclinado={fase === 'cogiendo'} />
        </group>
      </group>
      {enEscalera && (
        <Escalera x={escalera.x} z={escalera.z} altura={escalera.altura} />
      )}
    </>
  )
}

function Figura({ inclinado }) {
  return (
    <group position={[0, 0, 0.15]} rotation={[inclinado ? 0.3 : 0, 0, 0]}>
      <mesh position={[0, 0.55, 0]}>
        <capsuleGeometry args={[0.18, 0.5, 4, 8]} />
        <meshStandardMaterial color={COLOR_OPERARIO} />
      </mesh>
      <mesh position={[0, 1.05, 0]}>
        <sphereGeometry args={[0.14, 12, 12]} />
        <meshStandardMaterial color={COLOR_CASCO} />
      </mesh>
    </group>
  )
}

/**
 * Transpalé con su palé, detrás del operario. Las cajas cargadas se apilan
 * encima (se topa la pila para no formar una torre infinita).
 */
function Transpalet({ cargadas }) {
  const pila = Math.min(cargadas, 5)
  return (
    <group position={[0, 0, -0.55]}>
      <mesh position={[0, 0.1, 0]}>
        <boxGeometry args={[0.7, 0.14, 0.9]} />
        <meshStandardMaterial color={COLOR_PALET} />
      </mesh>
      <mesh position={[0, 0.45, -0.5]}>
        <boxGeometry args={[0.55, 0.9, 0.06]} />
        <meshStandardMaterial color={COLOR_TRANSPALET} />
      </mesh>
      {Array.from({ length: pila }, (_, i) => i).map((i) => (
        <mesh key={i} position={[0, 0.32 + i * 0.26, 0]}>
          <boxGeometry args={[0.5, 0.24, 0.6]} />
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
