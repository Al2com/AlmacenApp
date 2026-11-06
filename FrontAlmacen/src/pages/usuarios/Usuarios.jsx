import { useEffect, useState } from 'react'
import * as usuariosApi from '../../api/usuarios'
import { NOMBRE_ROL } from '../../auth/roles'

const ROLES = Object.entries(NOMBRE_ROL)

const FORM_INICIAL = {
  nombre: '',
  email: '',
  rol: 'operador_almacen',
  password: '',
  password_confirmation: '',
}

export function Usuarios() {
  const [usuarios, setUsuarios] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const lista = await usuariosApi.listar()
        if (activo) setUsuarios(lista.data)
      } catch {
        if (activo) setError(true)
      } finally {
        if (activo) setCargando(false)
      }
    })()
    return () => {
      activo = false
    }
  }, [])

  async function recargar() {
    const lista = await usuariosApi.listar()
    setUsuarios(lista.data)
  }

  if (error) {
    return <p className="error">No se pudieron cargar las cuentas.</p>
  }

  if (cargando) {
    return <p className="cargando">Cargando cuentas…</p>
  }

  return (
    <section>
      <h1>usuarios</h1>
      <div className="columnas">
        <FormularioCuenta alCrear={recargar} />
        <ListaCuentas usuarios={usuarios} alCambiar={recargar} />
      </div>
    </section>
  )
}

function FormularioCuenta({ alCrear }) {
  const [form, setForm] = useState(FORM_INICIAL)
  const [errores, setErrores] = useState(null)
  const [enviando, setEnviando] = useState(false)

  function cambiar(campo, valor) {
    setForm((actual) => ({ ...actual, [campo]: valor }))
  }

  async function enviar(evento) {
    evento.preventDefault()
    setErrores(null)
    setEnviando(true)
    try {
      await usuariosApi.crear(form)
      setForm(FORM_INICIAL)
      await alCrear()
    } catch (fallo) {
      setErrores(fallo.errores ?? { general: ['No se pudo crear la cuenta.'] })
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form className="tarjeta" onSubmit={enviar}>
      <h2>nueva cuenta</h2>
      <p className="subtitulo">
        La cuenta nace con el cambio de contraseña obligatorio en el primer
        acceso.
      </p>

      <div className="campo">
        <label htmlFor="nombre">nombre</label>
        <input
          id="nombre"
          value={form.nombre}
          onChange={(e) => cambiar('nombre', e.target.value)}
          required
        />
        <Fallo errores={errores} campo="nombre" />
      </div>

      <div className="campo">
        <label htmlFor="email">correo</label>
        <input
          id="email"
          type="email"
          value={form.email}
          onChange={(e) => cambiar('email', e.target.value)}
          required
        />
        <Fallo errores={errores} campo="email" />
      </div>

      <div className="campo">
        <label htmlFor="rol">rol</label>
        <select
          id="rol"
          value={form.rol}
          onChange={(e) => cambiar('rol', e.target.value)}
        >
          {ROLES.map(([valor, nombre]) => (
            <option key={valor} value={valor}>
              {nombre}
            </option>
          ))}
        </select>
      </div>

      <div className="campo">
        <label htmlFor="password">contraseña (mínimo 12)</label>
        <input
          id="password"
          type="password"
          autoComplete="new-password"
          value={form.password}
          onChange={(e) => cambiar('password', e.target.value)}
          required
        />
        <Fallo errores={errores} campo="password" />
      </div>

      <div className="campo">
        <label htmlFor="password_confirmation">repite la contraseña</label>
        <input
          id="password_confirmation"
          type="password"
          autoComplete="new-password"
          value={form.password_confirmation}
          onChange={(e) => cambiar('password_confirmation', e.target.value)}
          required
        />
      </div>

      {errores?.general && (
        <p className="error" role="alert">
          {errores.general[0]}
        </p>
      )}

      <button type="submit" className="boton" disabled={enviando}>
        {enviando ? 'creando…' : 'crear cuenta'}
      </button>
    </form>
  )
}

function ListaCuentas({ usuarios, alCambiar }) {
  return (
    <div className="tarjeta">
      <h2>cuentas</h2>
      <table>
        <thead>
          <tr>
            <th>nombre</th>
            <th>rol</th>
            <th>estado</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {usuarios.map((usuario) => (
            <FilaCuenta
              key={usuario.id}
              usuario={usuario}
              alCambiar={alCambiar}
            />
          ))}
        </tbody>
      </table>
    </div>
  )
}

function FilaCuenta({ usuario, alCambiar }) {
  const [ocupado, setOcupado] = useState(false)

  async function cambiar(datos) {
    setOcupado(true)
    try {
      await usuariosApi.actualizar(usuario.id, datos)
      await alCambiar()
    } finally {
      setOcupado(false)
    }
  }

  return (
    <tr>
      <td>
        {usuario.nombre}
        <div className="sub">{usuario.email}</div>
      </td>
      <td>
        <select
          aria-label={`rol de ${usuario.nombre}`}
          value={usuario.rol}
          disabled={ocupado}
          onChange={(e) => cambiar({ rol: e.target.value })}
        >
          {ROLES.map(([valor, nombre]) => (
            <option key={valor} value={valor}>
              {nombre}
            </option>
          ))}
        </select>
      </td>
      <td>
        <span
          className={`estado ${usuario.activo ? 'estado-preparada' : 'estado-cancelada'}`}
        >
          {usuario.activo ? 'activa' : 'inactiva'}
        </span>
      </td>
      <td style={{ textAlign: 'right' }}>
        <button
          type="button"
          className="boton boton-secundario"
          disabled={ocupado}
          onClick={() => cambiar({ activo: !usuario.activo })}
        >
          {usuario.activo ? 'desactivar' : 'activar'}
        </button>
      </td>
    </tr>
  )
}

function Fallo({ errores, campo }) {
  if (!errores?.[campo]) {
    return null
  }
  return <span className="error">{errores[campo][0]}</span>
}
