import { Navigate, Route, Routes } from 'react-router-dom'
import { Privada } from './auth/Privada'
import { Placeholder } from './components/Placeholder'
import { CambiarPassword } from './pages/cambiar-password/CambiarPassword'
import { Login } from './pages/login/Login'
import { Panel } from './pages/panel/Panel'

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
            <Placeholder titulo="Plano 3D" />
          </Privada>
        }
      />
      <Route
        path="/ordenes"
        element={
          <Privada roles={['admin', 'operador_produccion']}>
            <Placeholder titulo="Órdenes de producción" />
          </Privada>
        }
      />
      <Route
        path="/recepciones"
        element={
          <Privada roles={['admin', 'operador_recepcion']}>
            <Placeholder titulo="Recepciones" />
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
            <Placeholder titulo="Usuarios" />
          </Privada>
        }
      />
      <Route
        path="/pda"
        element={
          <Privada roles={['operador_almacen']}>
            <Placeholder titulo="PDA" />
          </Privada>
        }
      />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
