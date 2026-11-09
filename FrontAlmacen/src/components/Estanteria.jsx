import { useMemo, useState } from 'react'
import { BAHIAS, NIVELES } from '../config/almacen'
import { estado, ESTADOS } from '../estados'

export function Estanteria({ posiciones }) {
  const pasillos = useMemo(
    () => [...new Set(posiciones.map((p) => p.pasillo))].sort((a, b) => a - b),
    [posiciones]
  )
  const [pasillo, setPasillo] = useState(pasillos[0] ?? 1)
  const [seleccion, setSeleccion] = useState(null)
  const [denso, setDenso] = useState(false)

  const ocupacion = useMemo(() => {
    const mapa = {}
    for (const numero of pasillos) {
      const delPasillo = posiciones.filter((p) => p.pasillo === numero)
      const ocupadas = delPasillo.filter((p) => p.estado !== 'libre').length
      mapa[numero] = delPasillo.length
        ? Math.round((ocupadas / delPasillo.length) * 100)
        : 0
    }
    return mapa
  }, [posiciones, pasillos])

  const porCodigo = useMemo(() => {
    const mapa = new Map()
    for (const p of posiciones.filter((p) => p.pasillo === pasillo)) {
      mapa.set(`${p.estanteria}-${p.nivel}`, p)
    }
    return mapa
  }, [posiciones, pasillo])

  return (
    <section
      className={`estanteria ${denso ? 'denso' : ''}`}
      aria-label="Estantería"
    >
      <div className="densidad">
        <span>densidad</span>
        <button
          type="button"
          className={!denso ? 'activo' : undefined}
          aria-pressed={!denso}
          onClick={() => setDenso(false)}
        >
          compacta
        </button>
        <button
          type="button"
          className={denso ? 'activo' : undefined}
          aria-pressed={denso}
          onClick={() => setDenso(true)}
        >
          ampliada
        </button>
      </div>

      <div className="pasillos" role="tablist" aria-label="Pasillos">
        {pasillos.map((numero) => (
          <button
            key={numero}
            type="button"
            className={numero === pasillo ? 'activo' : undefined}
            aria-pressed={numero === pasillo}
            onClick={() => {
              setPasillo(numero)
              setSeleccion(null)
            }}
          >
            <span className="pasillo-cab">
              <span>pasillo {numero}</span>
              <span>{ocupacion[numero]} %</span>
            </span>
            <span className="pasillo-barra">
              <span style={{ width: `${ocupacion[numero]}%` }} />
            </span>
          </button>
        ))}
      </div>

      <div className="rack-scroll">
        <div className="rack-filas">
          <div className="rack-niveles">
            {NIVELES.map((nivel) => (
              <span key={nivel}>nivel {nivel}</span>
            ))}
          </div>
          <div className="rack">
            {NIVELES.map((nivel) => (
              <div className="rack-nivel" key={nivel}>
                {BAHIAS.map((bahia) => {
                  const posicion = porCodigo.get(`${bahia}-${nivel}`)
                  const info = estado(posicion?.estado)
                  const linea = posicion?.contenido?.[0]
                  const reservada = (posicion?.contenido ?? []).reduce(
                    (suma, l) => suma + l.reservada,
                    0
                  )
                  const seleccionada =
                    seleccion && posicion?.codigo === seleccion.codigo
                  return (
                    <button
                      key={`${bahia}-${nivel}`}
                      type="button"
                      className={`posicion ${info.clase} ${seleccionada ? 'seleccionada' : ''}`}
                      title={
                        posicion ? `${posicion.codigo} · ${info.etiqueta}` : ''
                      }
                      aria-label={
                        posicion
                          ? `${posicion.codigo}, ${info.etiqueta}${reservada > 0 ? `, ${reservada} reservado` : ''}`
                          : 'sin posición'
                      }
                      disabled={!posicion}
                      onClick={() => setSeleccion(posicion)}
                    >
                      {denso && posicion ? (
                        <span className="celda">
                          <span className="celda-art">
                            {linea?.codigo_articulo ?? ''}
                          </span>
                          <span className="celda-qty">
                            {linea ? linea.disponible : ''}
                          </span>
                        </span>
                      ) : (
                        info.simbolo
                      )}
                      {reservada > 0 && (
                        <span className="reservado-badge">
                          R{denso ? ` ${reservada}` : ''}
                        </span>
                      )}
                    </button>
                  )
                })}
              </div>
            ))}
            <div className="rack-bahias">
              {BAHIAS.map((bahia) => (
                <span key={bahia}>{String(bahia).padStart(2, '0')}</span>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="leyenda leyenda-oscura">
        {Object.values(ESTADOS).map((e) => (
          <span key={e.clase}>
            <span className={`muestra ${e.clase}`} aria-hidden="true">
              {e.simbolo}
            </span>
            {e.etiqueta}
          </span>
        ))}
      </div>

      <DetalleOscuro posicion={seleccion} />
    </section>
  )
}

function DetalleOscuro({ posicion }) {
  if (!posicion) {
    return (
      <div className="rack-detalle">
        <span className="rack-detalle-vacio">
          Pulsa una posición para ver su contenido.
        </span>
      </div>
    )
  }

  const linea = posicion.contenido[0]

  return (
    <div className="rack-detalle">
      <div className="rack-detalle-pos">
        <span className="etiqueta">posición</span>
        <span className="codigo">{posicion.codigo}</span>
      </div>
      {linea ? (
        <div className="rack-detalle-datos">
          <div>
            <span className="etiqueta">artículo</span>
            <span>{linea.articulo}</span>
          </div>
          <div>
            <span className="etiqueta">lote</span>
            <span>{linea.lote}</span>
          </div>
          <div>
            <span className="etiqueta">caducidad</span>
            <span>{linea.caducidad ?? 'sin caducidad'}</span>
          </div>
          <div>
            <span className="etiqueta">disponible</span>
            <span>
              {linea.disponible} de {linea.total}
            </span>
          </div>
        </div>
      ) : (
        <span className="rack-detalle-libre">
          Libre. Admite un lote; lista para recibir mercancía.
        </span>
      )}
    </div>
  )
}
