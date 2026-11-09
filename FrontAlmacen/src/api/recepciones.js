import { api } from './cliente'

export const registrar = (datos) => api.post('/recepciones', datos)
