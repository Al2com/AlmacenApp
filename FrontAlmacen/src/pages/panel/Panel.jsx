import { useEffect, useState } from 'react'
import * as panelApi from '../../api/panel'
import { Estanteria } from '../../components/Estanteria'
import { GraficoHoras } from '../../components/GraficoHoras'
import { useSesion } from '../../auth/contexto'
import { ROLES } from '../../auth/roles'

const ETIQUETAS_CIFRAS = {
  lotes_caducan_pronto: 'caducan en 7 días',
  ordenes_en_curso: 'órdenes en curso',
  tareas_pendientes: 'tareas pendientes',
  incidencias_abiertas: 'incidencias abiertas',
}

export function Panel() {
  const { usuario } = useSesion()
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState(false)

  useEffect(() => {
    let vigente = true
    Promise.all([
      panelApi.cifras(),
      panelApi.estanteria(),
      panelApi.fefo(),
      panelApi.movimientos(),
      panelApi.flujo(),
    ])
      .then(([cifras, estanteria, fefo, movimientos, flujo]) => {
        if (vigente) {
          setDatos({
            cifras,
            estanteria,
            fefo,
            movimientos: movimientos.data,
            flujo,
          })
        }
      })
      .catch(() => vigente && setError(true))
    return () => {
      vigente = false
    }
  }, [])

  if (error) {
    return <p className="error contenido">No se pudo cargar el panel.</p>
  }

  if (!datos) {
    return <p className="cargando">Cargando panel…</p>
  }

  return (
    <section>
      <h1>panel general</h1>

      <div className="cifras">
        {Object.entries(ETIQUETAS_CIFRAS).map(([clave, etiqueta]) => (
          <div className="tarjeta cifra" key={clave}>
            <div className="numero">{datos.cifras[clave]}</div>
            <div className="etiqueta">{etiqueta}</div>
          </div>
        ))}
      </div>

      <Estanteria posiciones={datos.estanteria} />

      <div className="columnas">
        <SalenPrimero lotes={datos.fefo} />
        <div className="tarjeta">
          <h2>entradas y salidas por hora</h2>
          <GraficoHoras datos={datos.flujo} />
          <UltimosMovimientos movimientos={datos.movimientos} />
        </div>
      </div>

      <BloqueDelRol rol={usuario.rol} fefo={datos.fefo} flujo={datos.flujo} />
    </section>
  )
}

function SalenPrimero({ lotes }) {
  return (
    <div className="tarjeta">
      <h2>salen primero</h2>
      {lotes.length === 0 ? (
        <p className="vacio">No hay lotes con saldo.</p>
      ) : (
        <table>
          <thead>
            <tr>
              <th>artículo</th>
              <th>lote</th>
              <th>caducidad</th>
              <th>disponible</th>
            </tr>
          </thead>
          <tbody>
            {lotes.map((lote) => (
              <tr key={`${lote.codigo_articulo}-${lote.lote}`}>
                <td>{lote.articulo}</td>
                <td>{lote.lote}</td>
                <td>{lote.caducidad ?? '—'}</td>
                <td>
                  {lote.disponible}
                  {lote.para_baja && (
                    <span className="marca-baja"> · baja</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

function UltimosMovimientos({ movimientos }) {
  return (
    <>
      <h3>últimos movimientos</h3>
      {movimientos.length === 0 ? (
        <p className="vacio">Sin movimientos todavía.</p>
      ) : (
        <table>
          <thead>
            <tr>
              <th>tipo</th>
              <th>artículo</th>
              <th>lote</th>
              <th>cantidad</th>
            </tr>
          </thead>
          <tbody>
            {movimientos.map((m) => (
              <tr key={m.id}>
                <td>{m.tipo}</td>
                <td>{m.articulo}</td>
                <td>{m.lote}</td>
                <td>{m.cantidad}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}

function BloqueDelRol({ rol, fefo, flujo }) {
  if (rol === ROLES.gestor) {
    const pendientes = fefo.filter((lote) => lote.para_baja)
    return (
      <div className="tarjeta" style={{ marginTop: '1.5rem' }}>
        <h2>bajas pendientes de aprobar</h2>
        {pendientes.length === 0 ? (
          <p className="vacio">No hay lotes caducados pendientes de baja.</p>
        ) : (
          <ul>
            {pendientes.map((lote) => (
              <li key={`${lote.codigo_articulo}-${lote.lote}`}>
                {lote.articulo} · lote {lote.lote} · caducó el {lote.caducidad}
              </li>
            ))}
          </ul>
        )}
      </div>
    )
  }

  if (rol === ROLES.admin) {
    const entradas = flujo.reduce((suma, h) => suma + h.entradas, 0)
    const salidas = flujo.reduce((suma, h) => suma + h.salidas, 0)
    return (
      <div className="tarjeta" style={{ marginTop: '1.5rem' }}>
        <h2>actividad del día</h2>
        <p>
          entradas: {entradas} · salidas: {salidas}
        </p>
      </div>
    )
  }

  return null
}
