import { useEffect, useRef } from 'react'

const ACCION_FASE = {
  andando: '➜ yendo a la ubicación',
  cogiendo: '⊙ fichando la etiqueta',
  cargando: '▣ cargando en el transpalé',
  descargando: '⇩ dejando el palé en producción',
  terminado: '✓ pedido recorrido',
  parado: 'en espera',
}

/**
 * Pantalla a modo de PDA del operario: muestra, mientras recorre la ruta, qué
 * etiqueta está fichando y cómo avanza por el picking. Es solo un reflejo visual
 * de la demostración (no es la PDA real de la pantalla `/pda`).
 */
export function PdaOperario({ demo }) {
  const { activa, orden, tareas, indice, fase, cargadas } = demo
  const actualRef = useRef(null)

  useEffect(() => {
    actualRef.current?.scrollIntoView({ block: 'nearest' })
  }, [indice])

  if (!activa) return null

  const total = tareas.length
  const enRuta = fase !== 'terminado' && fase !== 'descargando'

  return (
    <div className="pda-operario" aria-live="polite">
      <div className="pda-op-cabecera">
        <span className="pda-op-marca">PDA · operario</span>
        {orden && <span className="pda-op-orden">{orden}</span>}
      </div>

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
    </div>
  )
}
