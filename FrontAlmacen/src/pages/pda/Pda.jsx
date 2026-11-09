import { useEffect, useState } from 'react'
import * as tareasApi from '../../api/tareas'

export function Pda() {
  const [tareas, setTareas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const lista = await tareasApi.listar()
        if (activo) setTareas(lista.data)
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
    const lista = await tareasApi.listar()
    setTareas(lista.data)
  }

  if (error) {
    return <p className="error">No se pudieron cargar las tareas.</p>
  }

  if (cargando) {
    return <p className="cargando">Cargando tareas…</p>
  }

  const tarea = tareas[0]

  return (
    <div className="pda">
      <h1>picking</h1>
      {tarea ? (
        <>
          <p className="pda-progreso">quedan {tareas.length} tareas</p>
          <TareaActual key={tarea.id} tarea={tarea} alResolver={recargar} />
        </>
      ) : (
        <p className="vacio">No hay tareas pendientes. 🎉</p>
      )}
    </div>
  )
}

function TareaActual({ tarea, alResolver }) {
  const [ubicacion, setUbicacion] = useState('')
  const [lote, setLote] = useState('')
  const [cantidad, setCantidad] = useState(String(tarea.cantidad))
  const [errores, setErrores] = useState(null)
  const [ocupado, setOcupado] = useState(false)

  async function confirmar(evento) {
    evento.preventDefault()
    setErrores(null)
    setOcupado(true)
    try {
      await tareasApi.confirmar(tarea.id, {
        codigo_ubicacion: ubicacion,
        codigo_lote: lote,
        cantidad: Number(cantidad),
      })
      await alResolver()
    } catch (fallo) {
      setErrores(fallo.errores ?? { general: ['No se pudo confirmar.'] })
    } finally {
      setOcupado(false)
    }
  }

  async function incidencia() {
    setOcupado(true)
    try {
      await tareasApi.incidencia(tarea.id)
      await alResolver()
    } finally {
      setOcupado(false)
    }
  }

  return (
    <form className="tarjeta pda-tarea" onSubmit={confirmar}>
      <span className="pda-etiqueta">ve a la posición</span>
      <span className="pda-ubicacion">{tarea.ubicacion}</span>
      <span className="pda-ruta">
        pasillo {tarea.pasillo} · estantería {tarea.estanteria} · nivel{' '}
        {tarea.nivel}
      </span>

      <dl className="pda-datos">
        <div>
          <dt>artículo</dt>
          <dd>{tarea.articulo}</dd>
        </div>
        <div>
          <dt>lote</dt>
          <dd>{tarea.lote}</dd>
        </div>
        <div>
          <dt>cantidad</dt>
          <dd className="pda-cantidad">{tarea.cantidad}</dd>
        </div>
        <div>
          <dt>orden</dt>
          <dd>{tarea.orden}</dd>
        </div>
      </dl>

      <p className="pda-etiqueta">confirma lo que ves en la estantería</p>
      <div className="campo">
        <label htmlFor="ubicacion">código de ubicación</label>
        <input
          id="ubicacion"
          value={ubicacion}
          onChange={(e) => setUbicacion(e.target.value)}
          required
        />
        {errores?.codigo_ubicacion && (
          <span className="error">{errores.codigo_ubicacion[0]}</span>
        )}
      </div>
      <div className="campo">
        <label htmlFor="lote">código de lote</label>
        <input
          id="lote"
          value={lote}
          onChange={(e) => setLote(e.target.value)}
          required
        />
        {errores?.codigo_lote && (
          <span className="error">{errores.codigo_lote[0]}</span>
        )}
      </div>
      <div className="campo">
        <label htmlFor="cantidad">cantidad</label>
        <input
          id="cantidad"
          type="number"
          min="0"
          step="0.001"
          value={cantidad}
          onChange={(e) => setCantidad(e.target.value)}
          required
        />
        {errores?.cantidad && (
          <span className="error">{errores.cantidad[0]}</span>
        )}
      </div>

      {errores?.general && (
        <p className="error" role="alert">
          {errores.general[0]}
        </p>
      )}

      <button type="submit" className="boton pda-confirmar" disabled={ocupado}>
        {ocupado ? 'procesando…' : 'confirmar recogida'}
      </button>
      <button
        type="button"
        className="boton boton-secundario pda-incidencia"
        disabled={ocupado}
        onClick={incidencia}
      >
        no hay material (incidencia)
      </button>
    </form>
  )
}
