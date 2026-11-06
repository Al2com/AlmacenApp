import { useEffect, useState } from 'react'
import { articulos as cargarArticulos } from '../../api/catalogo'
import * as recepcionesApi from '../../api/recepciones'

function hoy() {
  return new Date().toISOString().slice(0, 10)
}

const FORM_INICIAL = {
  articulo_id: '',
  codigo_lote: '',
  fecha_recepcion: hoy(),
  caducidad: '',
  cantidad: '',
}

export function Recepciones() {
  const [articulos, setArticulos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)
  const [form, setForm] = useState(FORM_INICIAL)
  const [errores, setErrores] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const [ultima, setUltima] = useState(null)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const lista = await cargarArticulos()
        if (activo) setArticulos(lista.data)
      } catch {
        if (activo) setError(true)
      } finally {
        if (activo) setCargando(false)
      }
    })()
    return () => {
      activo = false
    }
  }, [])

  if (error) {
    return <p className="error">No se pudo cargar el catálogo.</p>
  }

  if (cargando) {
    return <p className="cargando">Cargando…</p>
  }

  const articulo = articulos.find((a) => a.id === Number(form.articulo_id))
  const tieneCaducidad = articulo?.tiene_caducidad ?? false

  function cambiar(campo, valor) {
    setForm((actual) => ({ ...actual, [campo]: valor }))
  }

  async function enviar(evento) {
    evento.preventDefault()
    setErrores(null)
    setEnviando(true)
    try {
      const datos = {
        articulo_id: Number(form.articulo_id),
        codigo_lote: form.codigo_lote,
        fecha_recepcion: form.fecha_recepcion,
        cantidad: Number(form.cantidad),
      }
      if (tieneCaducidad) {
        datos.caducidad = form.caducidad
      }
      const respuesta = await recepcionesApi.registrar(datos)
      setUltima(respuesta)
      setForm({ ...FORM_INICIAL, fecha_recepcion: hoy() })
    } catch (fallo) {
      setErrores(
        fallo.errores ?? { general: ['No se pudo registrar la entrada.'] }
      )
    } finally {
      setEnviando(false)
    }
  }

  return (
    <section>
      <h1>recepciones</h1>

      <div className="columnas">
        <form className="tarjeta" onSubmit={enviar}>
          <h2>registrar entrada</h2>

          <div className="campo">
            <label htmlFor="articulo">artículo</label>
            <select
              id="articulo"
              value={form.articulo_id}
              onChange={(e) => cambiar('articulo_id', e.target.value)}
              required
            >
              <option value="">— elige un artículo —</option>
              {articulos.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.nombre} ({a.unidad})
                </option>
              ))}
            </select>
            <Fallo errores={errores} campo="articulo_id" />
          </div>

          <div className="campo">
            <label htmlFor="codigo_lote">código de lote</label>
            <input
              id="codigo_lote"
              value={form.codigo_lote}
              onChange={(e) => cambiar('codigo_lote', e.target.value)}
              required
            />
            <Fallo errores={errores} campo="codigo_lote" />
          </div>

          <div className="campo">
            <label htmlFor="fecha_recepcion">fecha de recepción</label>
            <input
              id="fecha_recepcion"
              type="date"
              value={form.fecha_recepcion}
              onChange={(e) => cambiar('fecha_recepcion', e.target.value)}
              required
            />
          </div>

          {tieneCaducidad && (
            <div className="campo">
              <label htmlFor="caducidad">caducidad</label>
              <input
                id="caducidad"
                type="date"
                value={form.caducidad}
                onChange={(e) => cambiar('caducidad', e.target.value)}
                required
              />
              <Fallo errores={errores} campo="caducidad" />
            </div>
          )}

          <div className="campo">
            <label htmlFor="cantidad">cantidad</label>
            <input
              id="cantidad"
              type="number"
              min="0.001"
              step="0.001"
              value={form.cantidad}
              onChange={(e) => cambiar('cantidad', e.target.value)}
              required
            />
            <Fallo errores={errores} campo="cantidad" />
          </div>

          {errores?.general && (
            <p className="error" role="alert">
              {errores.general[0]}
            </p>
          )}

          <button type="submit" className="boton" disabled={enviando}>
            {enviando ? 'registrando…' : 'registrar y ubicar'}
          </button>
        </form>

        <div>
          <h2>última entrada</h2>
          {ultima ? (
            <div className="tarjeta">
              <p>
                Recibidas <strong>{ultima.cantidad}</strong> uds del lote{' '}
                <strong>{ultima.lote.codigo_lote}</strong> (
                {ultima.lote.articulo}).
              </p>
              <p>
                Ubicadas en <strong>{ultima.ubicacion}</strong>.
              </p>
            </div>
          ) : (
            <p className="vacio">
              Registra una entrada y el sistema propondrá una ubicación libre
              con capacidad.
            </p>
          )}
        </div>
      </div>
    </section>
  )
}

function Fallo({ errores, campo }) {
  if (!errores?.[campo]) {
    return null
  }
  return <span className="error">{errores[campo][0]}</span>
}
