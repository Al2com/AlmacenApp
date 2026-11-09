import { api } from './cliente'

export const regularizar = (datos) => api.post('/inventario/ajustes', datos)

export const trasladar = (datos) => api.post('/inventario/traslados', datos)

export const baja = (datos) => api.post('/inventario/bajas', datos)
