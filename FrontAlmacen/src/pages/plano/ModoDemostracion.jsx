const ETIQUETA_FASE = {
  parado: 'en espera',
  andando: 'caminando por el pasillo',
  cogiendo: 'recogiendo de la ubicación',
  cargando: 'cargando en el transpalé',
  descargando: 'dejando el palé en producción',
  terminado: 'terminado',
}

/**
 * Controles del modo demostración: solo para el admin. Pone en marcha al
 * operario virtual, que confirma tareas reales por la API (ver
 * useOperarioVirtual). Pausar, acelerar y reiniciar son controles de la
 * animación; no deshacen ninguna confirmación ya hecha.
 */
export function ModoDemostracion({ demo, seguir, onSeguir }) {
  const {
    activa,
    pausada,
    velocidad,
    fase,
    tarea,
    mensaje,
    iniciar,
    detener,
    reiniciar,
    alternarPausa,
    acelerar,
  } = demo

  return (
    <div className="tarjeta demo-panel">
      <div className="demo-cabecera">
        <h2>modo demostración</h2>
        {!activa ? (
          <button type="button" className="boton" onClick={iniciar}>
            iniciar demostración
          </button>
        ) : (
          <button
            type="button"
            className="boton boton-secundario"
            onClick={detener}
          >
            detener
          </button>
        )}
      </div>

      {activa && (
        <div className="demo-controles">
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
            velocidad ×{velocidad}
          </button>
          <button
            type="button"
            className="boton boton-secundario"
            onClick={reiniciar}
          >
            reiniciar recorrido
          </button>
          <button
            type="button"
            className={`boton boton-secundario${seguir ? ' activo' : ''}`}
            aria-pressed={seguir}
            onClick={onSeguir}
          >
            {seguir ? 'vista libre' : 'seguir al operario'}
          </button>
        </div>
      )}

      <p className="demo-estado">
        {activa && tarea && (
          <>
            {ETIQUETA_FASE[fase] ?? fase}: <strong>{tarea.articulo}</strong> ·
            lote {tarea.lote} · {tarea.ubicacion} · {tarea.cantidad} uds para{' '}
            {tarea.orden}
          </>
        )}
        {activa && !tarea && (mensaje || 'preparando…')}
        {!activa &&
          (mensaje ||
            'pulsa «iniciar demostración» para ver picar al operario.')}
      </p>
    </div>
  )
}
