import { NavLink } from 'react-router-dom'
import { useSesion } from '../auth/contexto'
import { menuDe, NOMBRE_ROL } from '../auth/roles'

export function Layout({ children }) {
  const { usuario, salir } = useSesion()
  const menu = usuario ? menuDe(usuario.rol) : []

  return (
    <>
      <header className="barra">
        <span className="logo">AlmacenApp</span>
        <nav>
          {menu.map((entrada) => (
            <NavLink
              key={entrada.ruta}
              to={entrada.ruta}
              end
              className={({ isActive }) => (isActive ? 'activo' : undefined)}
            >
              {entrada.etiqueta}
            </NavLink>
          ))}
        </nav>
        <div className="usuario">
          <span>
            {usuario?.nombre} · {NOMBRE_ROL[usuario?.rol] ?? usuario?.rol}
          </span>
          <button type="button" className="salir" onClick={salir}>
            salir
          </button>
        </div>
      </header>
      <main className="contenido">{children}</main>
    </>
  )
}
