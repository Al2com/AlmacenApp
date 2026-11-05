import { api } from './cliente'

export const listar = () => api.get('/tareas')

export const confirmar = (id, datos) =>
  api.post(`/tareas/${id}/confirmar`, datos)

export const incidencia = (id) => api.post(`/tareas/${id}/incidencia`)
