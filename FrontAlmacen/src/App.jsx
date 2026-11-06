import { lazy, Suspense } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { Privada } from './auth/Privada'
import { Placeholder } from './components/Placeholder'
import { CambiarPassword } from './pages/cambiar-password/CambiarPassword'
import { Login } from './pages/login/Login'
import { Ordenes } from './pages/ordenes/Ordenes'
import { Panel } from './pages/panel/Panel'
import { Pda } from './pages/pda/Pda'
import { Recepciones } from './pages/recepciones/Recepciones'
import { Usuarios } from './pages/usuarios/Usuarios'

// La vista 3D arrastra Three.js: se carga solo al abrir el plano.
const Plano = lazy(() =>
  import('./pages/plano/Plano').then((modulo) => ({ default: modulo.Plano }))
)

const TODOS = [
  'admin',
  'gestor_inventario',
  'operador_produccion',
  'operador_almacen',
  'operador_recepcion',
]

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/cambiar-password" element={<CambiarPassword />} />

      <Route
        path="/"
        element={
          <Privada roles={['admin', 'gestor_inventario']}>
            <Panel />
          </Privada>
        }
      />
      <Route
        path="/plano"
        element={
          <Privada
            roles={['admin', 'gestor_inventario', 'operador_produccion']}
          >
            <Suspense fallback={<p className="cargando">Cargando plano…</p>}>
              <Plano />
            </Suspense>
          </Privada>
        }
      />
      <Route
        path="/ordenes"
        element={
          <Privada roles={['admin', 'operador_produccion']}>
            <Ordenes />
          </Privada>
        }
      />
      <Route
        path="/recepciones"
        element={
          <Privada roles={['admin', 'operador_recepcion']}>
            <Recepciones />
          </Privada>
        }
      />
      <Route
        path="/stock"
        element={
          <Privada roles={TODOS}>
            <Placeholder titulo="Stock y lotes" />
          </Privada>
        }
      />
      <Route
        path="/usuarios"
        element={
          <Privada roles={['admin']}>
            <Usuarios />
          </Privada>
        }
      />
      <Route
        path="/pda"
        element={
          <Privada roles={['operador_almacen']}>
            <Pda />
          </Privada>
        }
      />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
