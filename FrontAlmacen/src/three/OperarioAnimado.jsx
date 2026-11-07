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
const COLOR_CAJA = '#c9a24b'
const COLOR_BOLA = '#2447d6'

function avanzar(actual, destino, paso) {
  const d = destino - actual
  if (Math.abs(d) <= paso) return destino
  return actual + Math.sign(d) * paso
}

function girar(actual, destino, paso) {
  let d = destino - actual
  while (d > Math.PI) d -= 2 * Math.PI
  while (d < -Math.PI) d += 2 * Math.PI
  if (Math.abs(d) <= paso) return destino
  return actual + Math.sign(d) * paso
}

/**
 * El operario virtual: una figura con casco y chaleco que empuja un transpalé
 * por el centro de los pasillos (nunca a través de las estanterías) y mueve las
 * piernas al andar. Al llegar a una ubicación se orienta hacia la estantería y
 * una bola azul sale de la caja y viaja hasta el transpalé (el picking); la
 * posición queda marcada como recogida. Puramente visual: no toca el stock.
 */
export function OperarioAnimado({
  fase,
  objetivo,
  destino,
  velocidad,
  cargadas,
  inicio,
  posicionRef,
}) {
  const grupo = useRef()
  const piernaIzq = useRef()
  const piernaDer = useRef()
  const bola = useRef()
  const rumbo = useRef(0)
  const paso = useRef(0)
  const faseAnterior = useRef(fase)
  const mundo = useRef(new THREE.Vector3(inicio.x, 1, inicio.z))

  useFrame((_, delta) => {
    if (!grupo.current || !objetivo) return
    const pos = grupo.current.position
    const dx = objetivo.x - pos.x
    const dz = objetivo.z - pos.z
    const resto = Math.hypot(dx, dz)
    const andando = fase === 'andando' && resto > 0.02

    // Orientación: hacia donde camina; parado para picar, hacia la estantería.
    let objetivoRumbo = rumbo.current
    if (andando) {
      objetivoRumbo = Math.atan2(dx, dz)
    } else if (destino) {
      objetivoRumbo = Math.atan2(
        destino.caja[0] - pos.x,
        destino.caja[2] - pos.z
      )
    }
    rumbo.current = girar(rumbo.current, objetivoRumbo, delta * 6)
    grupo.current.rotation.y = rumbo.current

    // Avance por el plano a ritmo constante.
    const avance = VEL_BASE * velocidad * delta
    pos.x = avanzar(pos.x, objetivo.x, avance)
    pos.z = avanzar(pos.z, objetivo.z, avance)

    // Piernas al andar.
    if (andando) paso.current += delta * velocidad * 9
    const balanceo = andando ? Math.sin(paso.current) * 0.5 : 0
    if (piernaIzq.current) piernaIzq.current.rotation.x = balanceo
    if (piernaDer.current) piernaDer.current.rotation.x = -balanceo

    // Bola del picking: sale de la caja (al entrar en «cogiendo») y viaja hasta
    // el operario/transpalé. Fuera de esa fase, oculta.
    if (bola.current) {
      if (fase === 'cogiendo' && destino) {
        if (faseAnterior.current !== 'cogiendo') {
          bola.current.position.set(
            destino.caja[0],
            destino.caja[1],
            destino.caja[2]
          )
          bola.current.visible = true
        }
        const k = Math.min(1, delta * 3)
        bola.current.position.x = THREE.MathUtils.lerp(
          bola.current.position.x,
          pos.x,
          k
        )
        bola.current.position.y = THREE.MathUtils.lerp(
          bola.current.position.y,
          0.55,
          k
        )
        bola.current.position.z = THREE.MathUtils.lerp(
          bola.current.position.z,
          pos.z,
          k
        )
      } else {
        bola.current.visible = false
      }
    }

    mundo.current.set(pos.x, 1, pos.z)
    if (posicionRef) posicionRef.current = mundo.current
    faseAnterior.current = fase
  })

  return (
    <>
      <group ref={grupo} position={[inicio.x, 0, inicio.z]}>
        <Transpalet cargadas={cargadas} />
        <Figura piernaIzq={piernaIzq} piernaDer={piernaDer} />
      </group>
      <mesh ref={bola} visible={false}>
        <sphereGeometry args={[0.22, 16, 16]} />
        <meshStandardMaterial
          color={COLOR_BOLA}
          emissive={COLOR_BOLA}
          emissiveIntensity={0.4}
        />
      </mesh>
    </>
  )
}

function Figura({ piernaIzq, piernaDer }) {
  return (
    <group position={[0, 0, -0.2]}>
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
 * las manos. Las cajas cargadas se apilan encima (se topa la pila).
 */
function Transpalet({ cargadas }) {
  const pila = Math.min(cargadas, 5)
  return (
    <group position={[0, 0, 0.55]}>
      <mesh position={[0, 0.1, 0]}>
        <boxGeometry args={[0.72, 0.14, 0.95]} />
        <meshStandardMaterial color={COLOR_PALET} />
      </mesh>
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
