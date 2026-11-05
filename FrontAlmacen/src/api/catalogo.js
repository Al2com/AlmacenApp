import { api } from './cliente'

export const articulos = () => api.get('/articulos')
