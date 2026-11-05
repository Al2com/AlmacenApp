import { api } from './cliente'

export const listar = () => api.get('/tareas')

export const confirmar = (id) => api.post(`/tareas/${id}/confirmar`)
