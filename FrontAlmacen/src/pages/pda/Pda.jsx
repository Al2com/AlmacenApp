import { useEffect, useState } from 'react'
import * as tareasApi from '../../api/tareas'

export function Pda() {
  const [tareas, setTareas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)
  const [confirmando, setConfirmando] = useState(false)

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

  async function confirmar(id) {
    setConfirmando(true)
    try {
      await tareasApi.confirmar(id)
      const lista = await tareasApi.listar()
      setTareas(lista.data)
    } finally {
      setConfirmando(false)
    }
  }

  if (error) {
    return <p className="error">No se pudieron cargar las tareas.</p>
  }

  if (cargando) {
    return <p className="cargando">Cargando tareas…</p>
  }

  const tarea = tareas[0]

  if (!tarea) {
    return (
      <div className="pda">
        <h1>picking</h1>
        <p className="vacio">No hay tareas pendientes. 🎉</p>
      </div>
    )
  }

  return (
    <div className="pda">
      <h1>picking</h1>
      <p className="pda-progreso">quedan {tareas.length} tareas</p>

      <div className="tarjeta pda-tarea">
        <span className="pda-etiqueta">ve a</span>
        <span className="pda-ubicacion">{tarea.ubicacion}</span>

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

        <button
          type="button"
          className="boton pda-confirmar"
          disabled={confirmando}
          onClick={() => confirmar(tarea.id)}
        >
          {confirmando ? 'confirmando…' : 'confirmar recogida'}
        </button>
      </div>
    </div>
  )
}
