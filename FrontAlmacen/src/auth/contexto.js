import { createContext, useContext } from 'react'

export const SesionContext = createContext(null)

export function useSesion() {
  const contexto = useContext(SesionContext)
  if (!contexto) {
    throw new Error('useSesion debe usarse dentro de ProveedorSesion')
  }
  return contexto
}
