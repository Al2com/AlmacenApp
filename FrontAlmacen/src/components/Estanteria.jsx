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

  const porCodigo = useMemo(() => {
    const mapa = new Map()
    for (const p of posiciones.filter((p) => p.pasillo === pasillo)) {
      mapa.set(`${p.estanteria}-${p.nivel}`, p)
    }
    return mapa
  }, [posiciones, pasillo])

  return (
    <section className="estanteria">
      <h2>estantería</h2>

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
            pasillo {numero}
          </button>
        ))}
      </div>

      <div className="rejilla">
        {NIVELES.flatMap((nivel) =>
          BAHIAS.map((bahia) => {
            const posicion = porCodigo.get(`${bahia}-${nivel}`)
            const info = estado(posicion?.estado)
            const seleccionada =
              seleccion && posicion?.codigo === seleccion.codigo
            return (
              <button
                key={`${bahia}-${nivel}`}
                type="button"
                className={`posicion ${info.clase} ${seleccionada ? 'seleccionada' : ''}`}
                title={posicion ? `${posicion.codigo} · ${info.etiqueta}` : ''}
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
          })
        )}
      </div>

      <div className="leyenda">
        {Object.values(ESTADOS).map((e) => (
          <span key={e.clase}>
            <span className={`muestra ${e.clase}`} aria-hidden="true" />
            {e.etiqueta}
            {e.simbolo ? ` (${e.simbolo})` : ''}
          </span>
        ))}
      </div>

      {seleccion && <DetallePosicion posicion={seleccion} />}
    </section>
  )
}

function DetallePosicion({ posicion }) {
  return (
    <div className="tarjeta" style={{ marginTop: '1rem' }}>
      <h3>{posicion.codigo}</h3>
      {posicion.contenido.length === 0 ? (
        <p className="vacio">Posición libre.</p>
      ) : (
        <table>
          <thead>
            <tr>
              <th>artículo</th>
              <th>lote</th>
              <th>caducidad</th>
              <th>disponible / total</th>
            </tr>
          </thead>
          <tbody>
            {posicion.contenido.map((linea) => (
              <tr key={linea.lote}>
                <td>{linea.articulo}</td>
                <td>{linea.lote}</td>
                <td>{linea.caducidad ?? '—'}</td>
                <td>
                  {linea.disponible} / {linea.total}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}
