import { useEffect, useRef, useState } from 'react'
import { articulos as cargarArticulos } from '../../api/catalogo'
import * as ordenesApi from '../../api/ordenes'

const ESTADO_ORDEN = {
  lanzada: 'lanzada',
  en_picking: 'en picking',
  preparada: 'preparada',
  entregada: 'entregada',
  cancelada: 'cancelada',
}

// Las órdenes se agrupan por estado para que la vista de trabajo quede limpia y
// lo ya cerrado se consulte aparte.
const PESTANAS = [
  {
    clave: 'curso',
    etiqueta: 'en curso',
    estados: ['lanzada', 'en_picking', 'preparada'],
  },
  { clave: 'entregadas', etiqueta: 'entregadas', estados: ['entregada'] },
  { clave: 'canceladas', etiqueta: 'canceladas', estados: ['cancelada'] },
]

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

function ListaOrdenes({ ordenes, alCambiar }) {
  const [pestana, setPestana] = useState('curso')

  const grupos = PESTANAS.map((p) => ({
    ...p,
    ordenes: ordenes.filter((o) => p.estados.includes(o.estado)),
  }))
  const activa = grupos.find((g) => g.clave === pestana) ?? grupos[0]

  return (
    <div>
      <div
        className="ordenes-pestanas"
        role="tablist"
        aria-label="Filtrar órdenes"
      >
        {grupos.map((g) => (
          <button
            key={g.clave}
            type="button"
            role="tab"
            aria-selected={g.clave === pestana}
            className={`boton boton-secundario${g.clave === pestana ? ' activo' : ''}`}
            onClick={() => setPestana(g.clave)}
          >
            {g.etiqueta} ({g.ordenes.length})
          </button>
        ))}
      </div>

      {activa.ordenes.length === 0 ? (
        <div className="tarjeta">
          <p className="vacio">No hay órdenes {activa.etiqueta}.</p>
        </div>
      ) : (
        activa.ordenes.map((orden) => (
          <OrdenCard key={orden.id} orden={orden} alCambiar={alCambiar} />
        ))
      )}
    </div>
  )
}

function FormularioOrden({ articulos, alCrear }) {
  const [nombre, setNombre] = useState('')
  const [lineas, setLineas] = useState([])
  const [busqueda, setBusqueda] = useState('')
  const [cantidad, setCantidad] = useState('')
  const [errores, setErrores] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const articuloRef = useRef(null)
  const cantidadRef = useRef(null)

  // El artículo elegido es el que coincide exactamente con el texto (lo pone el
  // datalist al seleccionar una opción).
  const articuloSel = articulos.find((a) => a.nombre === busqueda) ?? null
  const cantidadNum = Number(cantidad)
  const superaStock =
    articuloSel && cantidadNum > 0 && cantidadNum > articuloSel.disponible

  function alElegirArticulo(valor) {
    setBusqueda(valor)
    if (articulos.some((a) => a.nombre === valor)) {
      cantidadRef.current?.focus()
    }
  }

  function anadirLinea() {
    if (!articuloSel || !(cantidadNum > 0)) return
    setLineas((prev) => [
      ...prev,
      {
        articulo_id: articuloSel.id,
        nombre: articuloSel.nombre,
        unidad: articuloSel.unidad,
        cantidad: cantidadNum,
      },
    ])
    setBusqueda('')
    setCantidad('')
    articuloRef.current?.focus()
  }

  function alTeclearCantidad(evento) {
    if (evento.key === 'Enter') {
      evento.preventDefault()
      anadirLinea()
    }
  }

  async function enviar(evento) {
    evento.preventDefault()
    setErrores(null)
    if (lineas.length === 0) {
      setErrores({ general: ['Añade al menos una línea.'] })
      return
    }
    setEnviando(true)
    try {
      await ordenesApi.crear({
        nombre,
        lineas: lineas.map((linea) => ({
          articulo_id: linea.articulo_id,
          cantidad: linea.cantidad,
        })),
      })
      setNombre('')
      setLineas([])
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
      <p className="ayuda-campo">
        El código se genera solo (OP-0001…). Ponle un nombre y añade líneas.
      </p>

      <div className="campo">
        <label htmlFor="nombre-orden">nombre</label>
        <input
          id="nombre-orden"
          value={nombre}
          placeholder="p. ej. Línea 2"
          onChange={(e) => setNombre(e.target.value)}
          required
        />
        {errores?.nombre && <span className="error">{errores.nombre[0]}</span>}
      </div>

      <div className="alta-linea">
        <input
          ref={articuloRef}
          aria-label="artículo"
          list="articulos-lista"
          placeholder="busca un artículo…"
          value={busqueda}
          onChange={(e) => alElegirArticulo(e.target.value)}
        />
        <datalist id="articulos-lista">
          {articulos.map((articulo) => (
            <option key={articulo.id} value={articulo.nombre} />
          ))}
        </datalist>
        <input
          ref={cantidadRef}
          aria-label="cantidad"
          type="number"
          min="0.001"
          step="0.001"
          placeholder="cantidad"
          value={cantidad}
          onChange={(e) => setCantidad(e.target.value)}
          onKeyDown={alTeclearCantidad}
        />
        <button
          type="button"
          className="boton boton-secundario"
          onClick={anadirLinea}
          disabled={!articuloSel || !(cantidadNum > 0)}
        >
          añadir
        </button>
      </div>

      {articuloSel && (
        <p className={`disponible-hint${superaStock ? ' aviso' : ''}`}>
          disponible: {articuloSel.disponible} {articuloSel.unidad}
          {superaStock && ' — pides más de lo que hay (saldrá parcial)'}
        </p>
      )}

      {lineas.length > 0 && (
        <ul className="lineas-anadidas">
          {lineas.map((linea, indice) => (
            <li key={indice}>
              <span>
                {linea.cantidad} {linea.unidad} · {linea.nombre}
              </span>
              <button
                type="button"
                className="quitar"
                aria-label={`quitar ${linea.nombre}`}
                onClick={() =>
                  setLineas((prev) => prev.filter((_, i) => i !== indice))
                }
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}

      {errores?.general && (
        <p className="error" role="alert">
          {errores.general[0]}
        </p>
      )}

      <button
        type="submit"
        className="boton"
        disabled={enviando || lineas.length === 0}
      >
        {enviando ? 'lanzando…' : 'lanzar orden'}
      </button>
    </form>
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
        <span className="orden-titulo">
          <strong>{orden.nombre ?? orden.codigo}</strong>
          {orden.nombre && <span className="orden-codigo">{orden.codigo}</span>}
        </span>
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
