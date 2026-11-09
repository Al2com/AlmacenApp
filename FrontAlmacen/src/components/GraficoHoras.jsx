export function GraficoHoras({ datos }) {
  const maximo = Math.max(1, ...datos.map((d) => d.entradas + d.salidas))

  return (
    <div
      className="flujo"
      role="img"
      aria-label="Entradas y salidas por hora del día"
    >
      {datos.map((d) => (
        <div
          className="hora"
          key={d.hora}
          title={`${String(d.hora).padStart(2, '0')}:00 · entradas ${d.entradas} · salidas ${d.salidas}`}
        >
          <div
            className="entrada"
            style={{ height: `${(d.entradas / maximo) * 100}%` }}
          />
          <div
            className="salida"
            style={{ height: `${(d.salidas / maximo) * 100}%` }}
          />
        </div>
      ))}
    </div>
  )
}
