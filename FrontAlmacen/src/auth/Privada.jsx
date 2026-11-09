import { Navigate } from 'react-router-dom'
import { Layout } from '../components/Layout'
import { useSesion } from './contexto'
import { inicioDe } from './roles'

// Envuelve una pantalla: exige sesión, fuerza el cambio de contraseña pendiente
// y comprueba el rol. La protección de verdad la hace el backend.
export function Privada({ roles, children }) {
  const { usuario, cargando } = useSesion()

  if (cargando) {
    return <p className="cargando contenido">Cargando…</p>
  }

  if (!usuario) {
    return <Navigate to="/login" replace />
  }

  if (usuario.debe_cambiar_password) {
    return <Navigate to="/cambiar-password" replace />
  }

  if (roles && !roles.includes(usuario.rol)) {
    return <Navigate to={inicioDe(usuario.rol)} replace />
  }

  return <Layout>{children}</Layout>
}
