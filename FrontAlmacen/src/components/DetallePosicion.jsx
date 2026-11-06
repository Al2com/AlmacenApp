// Detalle de una posición pulsada. Lo usan igual la estantería 2D y la vista 3D.
export function DetallePosicion({ posicion }) {
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
