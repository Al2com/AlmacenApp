// Única puerta a la API. La sesión viaja por cookie HttpOnly (no se guardan
// tokens); en las peticiones que modifican datos se adjunta el token CSRF que
// Sanctum deja en la cookie XSRF-TOKEN.

const BASE = import.meta.env.VITE_API_URL || 'http://localhost:8000'

function leerCookie(nombre) {
  const fila = document.cookie
    .split('; ')
    .find((trozo) => trozo.startsWith(nombre + '='))
  return fila ? decodeURIComponent(fila.slice(nombre.length + 1)) : null
}

async function asegurarCsrf() {
  await fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' })
}

export class ErrorApi extends Error {
  constructor(estado, cuerpo) {
    super(cuerpo?.message || 'Error de red')
    this.estado = estado
    this.cuerpo = cuerpo
    this.errores = cuerpo?.errors || null
  }
}

async function peticion(metodo, ruta, datos) {
  const modifica = metodo !== 'GET'
  if (modifica) await asegurarCsrf()

  const cabeceras = { Accept: 'application/json' }
  if (datos !== undefined) cabeceras['Content-Type'] = 'application/json'
  if (modifica) {
    const token = leerCookie('XSRF-TOKEN')
    if (token) cabeceras['X-XSRF-TOKEN'] = token
  }

  const respuesta = await fetch(`${BASE}/api${ruta}`, {
    method: metodo,
    credentials: 'include',
    headers: cabeceras,
    body: datos !== undefined ? JSON.stringify(datos) : undefined,
  })

  if (respuesta.status === 204) return null

  const cuerpo = await respuesta.json().catch(() => null)
  if (!respuesta.ok) throw new ErrorApi(respuesta.status, cuerpo)
  return cuerpo
}

export const api = {
  get: (ruta) => peticion('GET', ruta),
  post: (ruta, datos) => peticion('POST', ruta, datos),
  put: (ruta, datos) => peticion('PUT', ruta, datos),
}
