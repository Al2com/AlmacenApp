import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { cambiarPassword } from '../../api/auth'
import { useSesion } from '../../auth/contexto'
import { inicioDe } from '../../auth/roles'

export function CambiarPassword() {
  const { usuario, cargando, refrescar } = useSesion()
  const navegar = useNavigate()
  const [actual, setActual] = useState('')
  const [nueva, setNueva] = useState('')
  const [confirmacion, setConfirmacion] = useState('')
  const [errores, setErrores] = useState(null)
  const [enviando, setEnviando] = useState(false)

  if (cargando) {
    return <p className="cargando contenido">Cargando…</p>
  }

  if (!usuario) {
    return <Navigate to="/login" replace />
  }

  if (!usuario.debe_cambiar_password) {
    return <Navigate to={inicioDe(usuario.rol)} replace />
  }

  async function enviar(evento) {
    evento.preventDefault()
    setErrores(null)
    setEnviando(true)
    try {
      await cambiarPassword({
        password_actual: actual,
        password: nueva,
        password_confirmation: confirmacion,
      })
      await refrescar()
      navegar(inicioDe(usuario.rol), { replace: true })
    } catch (fallo) {
      setErrores(
        fallo.errores ?? { general: ['No se pudo cambiar la contraseña.'] }
      )
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="login">
      <form className="tarjeta" onSubmit={enviar}>
        <h1>cambia tu contraseña</h1>
        <p className="vacio">
          Es tu primer acceso: elige una contraseña nueva de al menos 12
          caracteres.
        </p>
        <div className="campo">
          <label htmlFor="actual">contraseña actual</label>
          <input
            id="actual"
            type="password"
            autoComplete="current-password"
            value={actual}
            onChange={(e) => setActual(e.target.value)}
            required
          />
          <Fallo errores={errores} campo="password_actual" />
        </div>
        <div className="campo">
          <label htmlFor="nueva">contraseña nueva</label>
          <input
            id="nueva"
            type="password"
            autoComplete="new-password"
            value={nueva}
            onChange={(e) => setNueva(e.target.value)}
            required
          />
          <Fallo errores={errores} campo="password" />
        </div>
        <div className="campo">
          <label htmlFor="confirmacion">repite la contraseña</label>
          <input
            id="confirmacion"
            type="password"
            autoComplete="new-password"
            value={confirmacion}
            onChange={(e) => setConfirmacion(e.target.value)}
            required
          />
        </div>
        {errores?.general && (
          <p className="error" role="alert">
            {errores.general[0]}
          </p>
        )}
        <button type="submit" className="boton" disabled={enviando}>
          {enviando ? 'guardando…' : 'guardar'}
        </button>
      </form>
    </div>
  )
}

function Fallo({ errores, campo }) {
  if (!errores?.[campo]) {
    return null
  }
  return <span className="error">{errores[campo][0]}</span>
}
