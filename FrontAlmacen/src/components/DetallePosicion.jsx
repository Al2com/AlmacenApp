// Detalle de una posición pulsada. Lo usan igual la estantería 2D y la vista 3D.
export function DetallePosicion({ posicion }) {
  const recienRecogida = posicion.contenido.some((linea) => linea.recogida)

  return (
    <div className="tarjeta" style={{ marginTop: '1rem' }}>
      <h3>{posicion.codigo}</h3>
      {posicion.contenido.length === 0 ? (
        <p className="vacio">Posición libre.</p>
      ) : recienRecogida ? (
        <ul className="recogida-lista">
          {posicion.contenido.map((linea) => (
            <li key={linea.lote}>
              Se acaba de recoger <strong>{linea.recogida}</strong> de{' '}
              {linea.articulo} (lote {linea.lote}) para el pedido{' '}
              <strong>{linea.recogida_orden}</strong>.
            </li>
          ))}
        </ul>
      ) : (
        <table>
          <thead>
            <tr>
              <th>artículo</th>
              <th>lote</th>
              <th>caducidad</th>
              <th>total</th>
              <th>reservado</th>
              <th>disponible ahora</th>
              <th>quedará tras recoger</th>
            </tr>
          </thead>
          <tbody>
            {posicion.contenido.map((linea) => (
              <tr key={linea.lote}>
                <td>{linea.articulo}</td>
                <td>{linea.lote}</td>
                <td>{linea.caducidad ?? '—'}</td>
                <td>{linea.total}</td>
                <td>
                  {linea.reservada > 0 ? (
                    <>
                      {linea.reservada}
                      <ReservasPorOrden reservas={linea.reservas} />
                    </>
                  ) : (
                    '—'
                  )}
                </td>
                <td>{linea.disponible}</td>
                <td>{linea.reservada > 0 ? linea.disponible : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

function ReservasPorOrden({ reservas }) {
  if (!reservas || reservas.length === 0) {
    return null
  }

  return (
    <span className="reservas-orden">
      {' '}
      (
      {reservas
        .map((reserva) => `${reserva.cantidad} para ${reserva.orden}`)
        .join(', ')}
      )
    </span>
  )
}
