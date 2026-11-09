import { api } from './cliente'

export const listar = () => api.get('/ordenes')

export const crear = (datos) => api.post('/ordenes', datos)

export const ver = (id) => api.get(`/ordenes/${id}`)

export const cancelar = (id) => api.post(`/ordenes/${id}/cancelar`)

export const entregar = (id) => api.post(`/ordenes/${id}/entregar`)

export const borrar = (id) => api.delete(`/ordenes/${id}`)
