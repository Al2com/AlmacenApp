import { useEffect, useRef, useState } from 'react'

const ACCION_FASE = {
  andando: '➜ yendo a la ubicación',
  cogiendo: '⊙ fichando la etiqueta',
  cargando: '▣ cargando en el transpalé',
  descargando: '⇩ dejando el palé en producción',
  terminado: '✓ pedido recorrido',
  parado: 'en espera',
}

/**
 * Pantalla a modo de PDA del operario, como columna propia al lado del plano
 * (no tapa el almacén) y plegable. Mientras recorre la ruta muestra qué etiqueta
 * está fichando y cómo avanza. Es solo un reflejo visual de la demostración.
 */
export function PdaOperario({ demo }) {
  const { activa, orden, tareas, indice, fase, cargadas } = demo
  const [plegada, setPlegada] = useState(false)
  const actualRef = useRef(null)

  useEffect(() => {
    actualRef.current?.scrollIntoView({ block: 'nearest' })
  }, [indice])

  if (!activa) return null

  if (plegada) {
    return (
      <aside className="pda-operario pda-plegada">
        <button
          type="button"
          className="pda-op-toggle"
          aria-expanded="false"
          onClick={() => setPlegada(false)}
        >
          PDA ▸
        </button>
      </aside>
    )
  }

  const total = tareas.length
  const enRuta = fase !== 'terminado' && fase !== 'descargando'

  return (
    <aside className="pda-operario" aria-live="polite">
      <div className="pda-op-cabecera">
        <span className="pda-op-marca">PDA · operario</span>
        <button
          type="button"
          className="pda-op-toggle"
          aria-expanded="true"
          aria-label="Plegar la PDA"
          onClick={() => setPlegada(true)}
        >
          ▾
        </button>
      </div>

      {orden && <div className="pda-op-orden">{orden}</div>}

      <div className="pda-op-progreso">
        <span>
          {enRuta
            ? `línea ${Math.min(indice + 1, total)} de ${total}`
            : `${total} líneas`}
        </span>
        <span>{cargadas} recogidas</span>
      </div>

      <div className="pda-op-accion">{ACCION_FASE[fase] ?? fase}</div>

      <ul className="pda-op-lista">
        {tareas.map((t, i) => {
          const estado =
            i < indice ? 'hecha' : i === indice ? 'actual' : 'pendiente'
          return (
            <li
              key={t.id}
              ref={i === indice ? actualRef : null}
              className={`pda-op-linea pda-op-${estado}`}
            >
              <span className="pda-op-marca-linea">
                {estado === 'hecha' ? '✓' : estado === 'actual' ? '▸' : '·'}
              </span>
              <span className="pda-op-ubic">{t.ubicacion}</span>
              <span className="pda-op-lote">{t.lote}</span>
              <span className="pda-op-cant">{t.cantidad}</span>
            </li>
          )
        })}
      </ul>
    </aside>
  )
}
