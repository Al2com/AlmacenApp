import { useFrame } from '@react-three/fiber'
import { useRef } from 'react'
import * as THREE from 'three'

const COLOR_OPERARIO = '#0e1b2e'
const COLOR_CASCO = '#ff8a3d'
const COLOR_CARRO = '#46546a'
const COLOR_ESCALERA = '#9aa3ad'

/**
 * El operario virtual del modo demostración: una figura sencilla que camina
 * (interpolando cada fotograma hacia el objetivo de su fase actual) hacia la
 * posición de la tarea, sube a una escalera si el nivel lo pide, y vuelve al
 * carro de picking. Puramente visual: el cambio real de stock lo hace la API,
 * no esta animación.
 */
export function OperarioAnimado({ fase, destino, carro, visible }) {
  const grupo = useRef()
  const objetivo = objetivoDe(fase, destino, carro)

  useFrame((_, delta) => {
    if (!grupo.current) return
    const actual = grupo.current.position
    const alfa = Math.min(1, delta * 2.5)
    actual.x = THREE.MathUtils.lerp(actual.x, objetivo.x, alfa)
    actual.y = THREE.MathUtils.lerp(actual.y, objetivo.y, alfa)
    actual.z = THREE.MathUtils.lerp(actual.z, objetivo.z, alfa)
  })

  const enEscalera =
    fase === 'subiendo' ||
    fase === 'bajando' ||
    (fase === 'cogiendo' && destino?.escalera)

  return (
    <>
      <CarroPicking posicion={carro} />
      {visible && (
        <group ref={grupo} position={[carro.x, 0, carro.z]}>
          <Figura inclinado={fase === 'cogiendo'} />
        </group>
      )}
      {visible && enEscalera && destino && (
        <Escalera x={destino.x} z={destino.z} altura={destino.yCaja} />
      )}
    </>
  )
}

function objetivoDe(fase, destino, carro) {
  if (!destino) {
    return { x: carro.x, y: 0, z: carro.z }
  }
  if (fase === 'yendo') return { x: destino.x, y: 0, z: destino.z }
  if (fase === 'subiendo')
    return { x: destino.x, y: destino.yCaja, z: destino.z }
  if (fase === 'cogiendo') {
    return {
      x: destino.x,
      y: destino.escalera ? destino.yCaja : 0,
      z: destino.z,
    }
  }
  if (fase === 'bajando') return { x: destino.x, y: 0, z: destino.z }
  if (fase === 'volviendo') return { x: carro.x, y: 0, z: carro.z }

  return { x: carro.x, y: 0, z: carro.z }
}

function Figura({ inclinado }) {
  return (
    <group rotation={[inclinado ? 0.3 : 0, 0, 0]}>
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

function CarroPicking({ posicion }) {
  return (
    <group position={[posicion.x, 0, posicion.z]}>
      <mesh position={[0, 0.3, 0]}>
        <boxGeometry args={[0.7, 0.5, 0.5]} />
        <meshStandardMaterial color={COLOR_CARRO} />
      </mesh>
      {[
        [-0.3, -0.2],
        [0.3, -0.2],
        [-0.3, 0.2],
        [0.3, 0.2],
      ].map(([dx, dz]) => (
        <mesh
          key={`${dx}-${dz}`}
          position={[dx, 0.08, dz]}
          rotation={[Math.PI / 2, 0, 0]}
        >
          <cylinderGeometry args={[0.08, 0.08, 0.05, 10]} />
          <meshStandardMaterial color="#1b2d5e" />
        </mesh>
      ))}
    </group>
  )
}
