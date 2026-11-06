import { api } from './cliente'

export const estado = () => api.get('/demostracion/estado')

export const confirmar = () => api.post('/demostracion/confirmar')
