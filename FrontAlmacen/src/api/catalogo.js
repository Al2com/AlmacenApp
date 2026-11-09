import { api } from './cliente'

export const articulos = () => api.get('/articulos')

export const ubicaciones = () => api.get('/ubicaciones')
