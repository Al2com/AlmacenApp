import { useCallback, useEffect, useState } from 'react'
import * as authApi from '../api/auth'
import { SesionContext } from './contexto'

export function ProveedorSesion({ children }) {
  const [usuario, setUsuario] = useState(null)
  const [cargando, setCargando] = useState(true)

  // Carga inicial de la sesión al montar: si hay cookie válida, recupera el
  // usuario; si no, queda como invitado.
  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const { data } = await authApi.yo()
        if (activo) setUsuario(data)
      } catch {
        if (activo) setUsuario(null)
      } finally {
        if (activo) setCargando(false)
      }
    })()
    return () => {
      activo = false
    }
  }, [])

  const refrescar = useCallback(async () => {
    try {
      const { data } = await authApi.yo()
      setUsuario(data)
    } catch {
      setUsuario(null)
    }
  }, [])

  const entrar = useCallback(async (email, password) => {
    const { data } = await authApi.login(email, password)
    setUsuario(data)
    return data
  }, [])

  const salir = useCallback(async () => {
    try {
      await authApi.logout()
    } finally {
      setUsuario(null)
    }
  }, [])

  const valor = { usuario, cargando, entrar, salir, refrescar, setUsuario }

  return (
    <SesionContext.Provider value={valor}>{children}</SesionContext.Provider>
  )
}
