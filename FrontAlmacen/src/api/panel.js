import { api } from './cliente'

export const cifras = () => api.get('/panel/cifras')

export const estanteria = () => api.get('/panel/estanteria')

export const fefo = () => api.get('/panel/fefo')

export const movimientos = () => api.get('/panel/movimientos')

export const flujo = () => api.get('/panel/flujo')

export const actividad = (desde) =>
  api.get(
    `/panel/actividad${desde ? `?desde=${encodeURIComponent(desde)}` : ''}`
  )
