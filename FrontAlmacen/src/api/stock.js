import { api } from './cliente'

export const listar = ({ articuloId, estado } = {}) => {
  const params = new URLSearchParams()
  if (articuloId) params.set('articulo_id', articuloId)
  if (estado) params.set('estado', estado)
  const cadena = params.toString()
  return api.get(`/stock${cadena ? `?${cadena}` : ''}`)
}
