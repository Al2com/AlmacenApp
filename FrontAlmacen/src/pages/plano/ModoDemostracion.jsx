const ETIQUETA_FASE = {
  parado: 'en espera',
  andando: 'caminando por el pasillo',
  cogiendo: 'recogiendo de la ubicación',
  cargando: 'cargando en el transpalé',
  descargando: 'dejando el palé en producción',
  terminado: 'terminado',
}

/**
 * Controles del modo demostración (solo admin), en una barra fina encima del
 * lienzo. El detalle de lo que hace el operario se ve en la PDA; aquí solo el
 * estado en una línea. Pausar, acelerar, reiniciar y seguir son controles de la
 * animación.
 */
export function ModoDemostracion({ demo, seguir, onSeguir }) {
  const {
    activa,
    pausada,
    velocidad,
    fase,
    mensaje,
    iniciar,
    detener,
    reiniciar,
    alternarPausa,
    acelerar,
  } = demo

  return (
    <div className="demo-barra" role="group" aria-label="Modo demostración">
      {!activa ? (
        <button type="button" className="boton" onClick={iniciar}>
          iniciar demostración
        </button>
      ) : (
        <>
          <button
            type="button"
            className="boton boton-secundario"
            onClick={detener}
          >
            detener
          </button>
          <button
            type="button"
            className="boton boton-secundario"
            onClick={alternarPausa}
          >
            {pausada ? 'reanudar' : 'pausar'}
          </button>
          <button
            type="button"
            className="boton boton-secundario"
            onClick={acelerar}
          >
            ×{velocidad}
          </button>
          <button
            type="button"
            className="boton boton-secundario"
            onClick={reiniciar}
          >
            reiniciar
          </button>
          <button
            type="button"
            className={`boton boton-secundario${seguir ? ' activo' : ''}`}
            aria-pressed={seguir}
            onClick={onSeguir}
          >
            {seguir ? 'vista libre' : 'seguir'}
          </button>
        </>
      )}
      <span className="demo-barra-estado">
        {activa
          ? (ETIQUETA_FASE[fase] ?? fase)
          : mensaje || 'listo para demostrar'}
      </span>
    </div>
  )
}
