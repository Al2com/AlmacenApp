import { NavLink } from 'react-router-dom'
import { useSesion } from '../auth/contexto'
import { menuDe, NOMBRE_ROL } from '../auth/roles'

export function Layout({ children }) {
  const { usuario, salir } = useSesion()
  const menu = usuario ? menuDe(usuario.rol) : []

  return (
    <>
      <header className="barra">
        <span className="logo">
          Almacen<span className="logo-app">App</span>
        </span>
        <nav aria-label="Menú principal">
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
          <div className="datos">
            <div className="nombre">{usuario?.nombre}</div>
            <div className="rol">
              {NOMBRE_ROL[usuario?.rol] ?? usuario?.rol}
            </div>
          </div>
          <button type="button" className="salir" onClick={salir}>
            salir
          </button>
        </div>
      </header>
      <main className="contenido">{children}</main>
    </>
  )
}
