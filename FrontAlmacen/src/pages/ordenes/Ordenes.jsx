import { useEffect, useState } from 'react'
import { articulos as cargarArticulos } from '../../api/catalogo'
import * as ordenesApi from '../../api/ordenes'

const ESTADO_ORDEN = {
  lanzada: 'lanzada',
  en_picking: 'en picking',
  preparada: 'preparada',
  entregada: 'entregada',
  cancelada: 'cancelada',
}

export function Ordenes() {
  const [ordenes, setOrdenes] = useState([])
  const [articulos, setArticulos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const [listaOrdenes, listaArticulos] = await Promise.all([
          ordenesApi.listar(),
          cargarArticulos(),
        ])
        if (activo) {
          setOrdenes(listaOrdenes.data)
          setArticulos(listaArticulos.data)
        }
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

  async function recargar() {
    const lista = await ordenesApi.listar()
    setOrdenes(lista.data)
  }

  if (error) {
    return <p className="error">No se pudieron cargar las órdenes.</p>
  }

  if (cargando) {
    return <p className="cargando">Cargando órdenes…</p>
  }

  return (
    <section>
      <h1>órdenes de producción</h1>
      <div className="columnas">
        <FormularioOrden articulos={articulos} alCrear={recargar} />
        <ListaOrdenes ordenes={ordenes} alCambiar={recargar} />
      </div>
    </section>
  )
}

function FormularioOrden({ articulos, alCrear }) {
  const [codigo, setCodigo] = useState('')
  const [lineas, setLineas] = useState([{ articulo_id: '', cantidad: '' }])
  const [errores, setErrores] = useState(null)
  const [enviando, setEnviando] = useState(false)

  function cambiarLinea(indice, campo, valor) {
    setLineas((actuales) =>
      actuales.map((linea, i) =>
        i === indice ? { ...linea, [campo]: valor } : linea
      )
    )
  }

  async function enviar(evento) {
    evento.preventDefault()
    setErrores(null)
    setEnviando(true)
    try {
      await ordenesApi.crear({
        codigo,
        lineas: lineas.map((linea) => ({
          articulo_id: Number(linea.articulo_id),
          cantidad: Number(linea.cantidad),
        })),
      })
      setCodigo('')
      setLineas([{ articulo_id: '', cantidad: '' }])
      await alCrear()
    } catch (fallo) {
      setErrores(fallo.errores ?? { general: ['No se pudo crear la orden.'] })
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form className="tarjeta" onSubmit={enviar}>
      <h2>nueva orden</h2>
      <div className="campo">
        <label htmlFor="codigo">código</label>
        <input
          id="codigo"
          value={codigo}
          onChange={(e) => setCodigo(e.target.value)}
          required
        />
        {errores?.codigo && <span className="error">{errores.codigo[0]}</span>}
      </div>

      {lineas.map((linea, indice) => (
        <div className="linea-form" key={indice}>
          <select
            aria-label="artículo"
            value={linea.articulo_id}
            onChange={(e) =>
              cambiarLinea(indice, 'articulo_id', e.target.value)
            }
            required
          >
            <option value="">— artículo —</option>
            {articulos.map((articulo) => (
              <option key={articulo.id} value={articulo.id}>
                {articulo.nombre}
              </option>
            ))}
          </select>
          <input
            aria-label="cantidad"
            type="number"
            min="0.001"
            step="0.001"
            value={linea.cantidad}
            onChange={(e) => cambiarLinea(indice, 'cantidad', e.target.value)}
            required
          />
          {lineas.length > 1 && (
            <button
              type="button"
              className="quitar"
              aria-label="quitar línea"
              onClick={() =>
                setLineas((actuales) => actuales.filter((_, i) => i !== indice))
              }
            >
              ×
            </button>
          )}
        </div>
      ))}

      <button
        type="button"
        className="boton boton-secundario"
        onClick={() =>
          setLineas((actuales) => [
            ...actuales,
            { articulo_id: '', cantidad: '' },
          ])
        }
      >
        añadir línea
      </button>

      {errores?.general && (
        <p className="error" role="alert">
          {errores.general[0]}
        </p>
      )}

      <button type="submit" className="boton" disabled={enviando}>
        {enviando ? 'lanzando…' : 'lanzar orden'}
      </button>
    </form>
  )
}

function ListaOrdenes({ ordenes, alCambiar }) {
  if (ordenes.length === 0) {
    return (
      <div className="tarjeta">
        <p className="vacio">No hay órdenes todavía.</p>
      </div>
    )
  }

  return (
    <div>
      {ordenes.map((orden) => (
        <OrdenCard key={orden.id} orden={orden} alCambiar={alCambiar} />
      ))}
    </div>
  )
}

function OrdenCard({ orden, alCambiar }) {
  const [ocupado, setOcupado] = useState(false)

  async function accion(ejecutar) {
    setOcupado(true)
    try {
      await ejecutar(orden.id)
      await alCambiar()
    } finally {
      setOcupado(false)
    }
  }

  async function borrar() {
    if (
      !window.confirm(`¿Borrar la orden ${orden.codigo}? No se puede deshacer.`)
    ) {
      return
    }
    await accion(ordenesApi.borrar)
  }

  // Solo se puede borrar antes de empezar el picking (lanzada) o si está cancelada.
  const sePuedeBorrar =
    orden.estado === 'lanzada' || orden.estado === 'cancelada'

  return (
    <div className="tarjeta orden">
      <header className="orden-cabecera">
        <strong>{orden.codigo}</strong>
        <span className={`estado estado-${orden.estado}`}>
          {ESTADO_ORDEN[orden.estado] ?? orden.estado}
        </span>
      </header>

      <table>
        <thead>
          <tr>
            <th>artículo</th>
            <th>pedido</th>
            <th>preparado</th>
            <th>falta</th>
          </tr>
        </thead>
        <tbody>
          {orden.lineas.map((linea) => (
            <tr key={linea.codigo_articulo}>
              <td>{linea.articulo}</td>
              <td>{linea.solicitada}</td>
              <td>{linea.preparada}</td>
              <td>{linea.faltante > 0 ? linea.faltante : '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="acciones">
        {orden.estado === 'lanzada' && (
          <button
            type="button"
            className="boton boton-secundario"
            disabled={ocupado}
            onClick={() => accion(ordenesApi.cancelar)}
          >
            cancelar
          </button>
        )}
        {orden.estado === 'preparada' && (
          <button
            type="button"
            className="boton"
            disabled={ocupado}
            onClick={() => accion(ordenesApi.entregar)}
          >
            marcar entregada
          </button>
        )}
        {sePuedeBorrar && (
          <button
            type="button"
            className="boton boton-peligro"
            disabled={ocupado}
            onClick={borrar}
          >
            borrar
          </button>
        )}
      </div>
    </div>
  )
}
