import { api } from './cliente'

// La ruta de picking del último pedido activo, para recrearla en el plano.
export const ruta = () => api.get('/demostracion/ruta')

// Confirma una línea de la ruta de verdad (movimiento real que descuenta stock).
export const confirmar = (id) => api.post(`/demostracion/confirmar/${id}`)
