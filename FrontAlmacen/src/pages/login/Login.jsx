import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useSesion } from '../../auth/contexto'
import { inicioDe } from '../../auth/roles'

export function Login() {
  const { usuario, entrar } = useSesion()
  const navegar = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [ver, setVer] = useState(false)
  const [error, setError] = useState(null)
  const [enviando, setEnviando] = useState(false)

  if (usuario) {
    return <Navigate to={inicioDe(usuario.rol)} replace />
  }

  async function enviar(evento) {
    evento.preventDefault()
    setError(null)
    setEnviando(true)
    try {
      const entrado = await entrar(email, password)
      navegar(
        entrado.debe_cambiar_password
          ? '/cambiar-password'
          : inicioDe(entrado.rol),
        { replace: true }
      )
    } catch (fallo) {
      if (fallo.estado === 429) {
        setError('Demasiados intentos. Prueba de nuevo en un minuto.')
      } else {
        setError('Correo o contraseña incorrectos.')
      }
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="login">
      <form className="tarjeta" onSubmit={enviar}>
        <h1>AlmacenApp</h1>
        <div className="campo">
          <label htmlFor="email">correo</label>
          <input
            id="email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div className="campo">
          <label htmlFor="password">contraseña</label>
          <input
            id="password"
            type={ver ? 'text' : 'password'}
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          <button
            type="button"
            className="mostrar"
            aria-pressed={ver}
            onClick={() => setVer((v) => !v)}
          >
            {ver ? 'ocultar contraseña' : 'mostrar contraseña'}
          </button>
        </div>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        <button type="submit" className="boton" disabled={enviando}>
          {enviando ? 'entrando…' : 'entrar'}
        </button>
      </form>
    </div>
  )
}
