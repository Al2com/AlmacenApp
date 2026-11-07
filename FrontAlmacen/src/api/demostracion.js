import { api } from './cliente'

// La ruta de picking del último pedido lanzado, para recrearla en el plano.
export const ruta = () => api.get('/demostracion/ruta')
