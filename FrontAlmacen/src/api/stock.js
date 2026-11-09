import { api } from './cliente'

export const listar = (filtros = {}) => {
  const params = new URLSearchParams()
  if (filtros.articuloId) params.set('articulo_id', filtros.articuloId)
  if (filtros.estado) params.set('estado', filtros.estado)
  if (filtros.lote) params.set('lote', filtros.lote)
  if (filtros.ubicacion) params.set('ubicacion', filtros.ubicacion)
  const cadena = params.toString()
  return api.get(`/stock${cadena ? `?${cadena}` : ''}`)
}

export const posicion = (ubicacionId) =>
  api.get(`/stock/posicion/${ubicacionId}`)
