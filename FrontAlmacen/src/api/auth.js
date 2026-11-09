import { api } from './cliente'

export const login = (email, password) =>
  api.post('/login', { email, password })

export const logout = () => api.post('/logout')

export const yo = () => api.get('/me')

export const cambiarPassword = (datos) => api.put('/password', datos)
