import { useMemo, useState } from 'react'
import { estado, ESTADOS } from '../estados'

const NIVELES = [3, 2, 1]
const BAHIAS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]

export function Estanteria({ posiciones }) {
  const pasillos = useMemo(
    () => [...new Set(posiciones.map((p) => p.pasillo))].sort((a, b) => a - b),
    [posiciones]
  )
  const [pasillo, setPasillo] = useState(pasillos[0] ?? 1)
  const [seleccion, setSeleccion] = useState(null)

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
    <section className="estanteria" aria-label="Estantería">
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
                          ? `${posicion.codigo}, ${info.etiqueta}`
                          : 'sin posición'
                      }
                      disabled={!posicion}
                      onClick={() => setSeleccion(posicion)}
                    >
                      {info.simbolo}
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
