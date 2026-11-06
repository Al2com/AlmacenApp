import { api } from './cliente'

export const listar = () => api.get('/usuarios')

export const crear = (datos) => api.post('/usuarios', datos)

export const actualizar = (id, datos) => api.put(`/usuarios/${id}`, datos)
