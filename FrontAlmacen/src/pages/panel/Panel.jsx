import { useEffect, useState } from 'react'
import * as panelApi from '../../api/panel'
import { Estanteria } from '../../components/Estanteria'
import { GraficoHoras } from '../../components/GraficoHoras'
import { useSesion } from '../../auth/contexto'
import { ROLES } from '../../auth/roles'

const KPIS = [
  {
    clave: 'lotes_caducan_pronto',
    label: 'caducan en 7 días',
    note: 'salen primero en el picking',
    alerta: true,
  },
  {
    clave: 'ordenes_en_curso',
    label: 'órdenes en curso',
    note: 'lanzadas, en picking o preparadas',
    alerta: false,
  },
  {
    clave: 'tareas_pendientes',
    label: 'tareas de picking pendientes',
    note: 'en la cola de la PDA',
    alerta: false,
  },
  {
    clave: 'incidencias_abiertas',
    label: 'incidencias abiertas',
    note: 'picking sin material',
    alerta: true,
  },
]

function fechaDeHoy() {
  const texto = new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  return texto.charAt(0).toUpperCase() + texto.slice(1)
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
    return <p className="error">No se pudo cargar el panel.</p>
  }

  if (!datos) {
    return <p className="cargando">Cargando panel…</p>
  }

  return (
    <section className="panel">
      <p className="panel-fecha">{fechaDeHoy()}. Almacén central.</p>

      <div className="cifras">
        {KPIS.map((kpi) => (
          <div
            className={`cifra ${kpi.alerta ? 'alerta' : ''}`}
            key={kpi.clave}
          >
            <div className="numero">{datos.cifras[kpi.clave]}</div>
            <div className="etiqueta">{kpi.label}</div>
            <div className="note">{kpi.note}</div>
          </div>
        ))}
      </div>

      <div className="panel-cols">
        <Estanteria posiciones={datos.estanteria} />
        <SalenPrimero lotes={datos.fefo} />
      </div>

      <div className="panel-cols">
        <section aria-label="Entradas y salidas de hoy">
          <h2>entradas y salidas de hoy</h2>
          <div className="flujo-leyenda">
            <span>
              <span className="punto entrada" aria-hidden="true" /> entradas
            </span>
            <span>
              <span className="punto salida" aria-hidden="true" /> salidas
            </span>
          </div>
          <GraficoHoras datos={datos.flujo} />
        </section>

        <UltimosMovimientos movimientos={datos.movimientos} />
      </div>

      <BloqueDelRol rol={usuario.rol} fefo={datos.fefo} flujo={datos.flujo} />
    </section>
  )
}

function SalenPrimero({ lotes }) {
  return (
    <section aria-label="Orden de salida">
      <h2>salen primero</h2>
      <p className="subtitulo">
        Orden FEFO para el próximo picking. Un lote caducado no se consume: va a
        baja.
      </p>
      {lotes.length === 0 ? (
        <p className="vacio">No hay lotes con saldo.</p>
      ) : (
        <ol className="fefo">
          {lotes.map((lote, indice) => (
            <li key={`${lote.codigo_articulo}-${lote.lote}`}>
              <span className="n">{indice + 1}</span>
              <span className="desc">
                <span className="articulo">{lote.articulo}</span>
                <span className="sub">
                  {lote.lote} · {lote.disponible} disp.
                </span>
              </span>
              <Etiqueta lote={lote} />
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}

function Etiqueta({ lote }) {
  if (lote.para_baja) {
    return <span className="tag tag-baja">a baja</span>
  }
  if (lote.caducidad) {
    return <span className="tag tag-fecha">caduca {lote.caducidad}</span>
  }
  return <span className="tag tag-neutro">sin caducidad</span>
}

function UltimosMovimientos({ movimientos }) {
  return (
    <section aria-label="Últimos movimientos">
      <h2>últimos movimientos</h2>
      {movimientos.length === 0 ? (
        <p className="vacio">Sin movimientos todavía.</p>
      ) : (
        <ul className="movs">
          {movimientos.map((m) => (
            <li key={m.id}>
              <span className="hora">{hora(m.fecha)}</span>
              <span className={`tipo mov-${m.tipo}`}>{m.tipo}</span>
              <span className="desc">
                {m.lote} en {ubicacionDe(m)}
              </span>
              <span className="cant">{m.cantidad}</span>
              <span className="user">{m.usuario}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function hora(iso) {
  return new Date(iso).toLocaleTimeString('es-ES', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function ubicacionDe(m) {
  if (m.origen && m.destino) return `${m.origen} → ${m.destino}`
  return m.destino ?? m.origen ?? '—'
}

function BloqueDelRol({ rol, fefo, flujo }) {
  if (rol === ROLES.gestor) {
    const pendientes = fefo.filter((lote) => lote.para_baja)
    return (
      <section aria-label="Pendiente de aprobar">
        <h2>pendiente de tu aprobación</h2>
        {pendientes.length === 0 ? (
          <p className="vacio">No hay lotes caducados pendientes de baja.</p>
        ) : (
          <ul className="aprobaciones">
            {pendientes.map((lote) => (
              <li key={`${lote.codigo_articulo}-${lote.lote}`}>
                <div>
                  <div className="titulo">baja del lote {lote.lote}</div>
                  <div className="sub">
                    {lote.articulo} · caducó el {lote.caducidad}
                  </div>
                </div>
                <span className="tag tag-baja">{lote.disponible} a baja</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    )
  }

  if (rol === ROLES.admin) {
    const entradas = flujo.reduce((suma, h) => suma + h.entradas, 0)
    const salidas = flujo.reduce((suma, h) => suma + h.salidas, 0)
    return (
      <section aria-label="Actividad de hoy">
        <h2>actividad de hoy</h2>
        <div className="actividad">
          <div>
            <div className="numero">{entradas}</div>
            <div className="note">unidades recibidas</div>
          </div>
          <div>
            <div className="numero">{salidas}</div>
            <div className="note">unidades consumidas o dadas de baja</div>
          </div>
        </div>
      </section>
    )
  }

  return null
}
