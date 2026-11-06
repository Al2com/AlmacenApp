import { useEffect, useState } from 'react'
import { articulos as cargarArticulos } from '../../api/catalogo'
import * as stockApi from '../../api/stock'

const ESTADOS = {
  ok: { label: 'válido', clase: 'tag-neutro' },
  caduca_pronto: { label: 'caduca pronto', clase: 'tag-fecha' },
  caducado: { label: 'caducado', clase: 'tag-baja' },
  sin_caducidad: { label: 'sin caducidad', clase: 'tag-neutro' },
}

export function Stock() {
  const [articulos, setArticulos] = useState([])
  const [filas, setFilas] = useState([])
  const [articuloId, setArticuloId] = useState('')
  const [estado, setEstado] = useState('')
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const [catalogo, stock] = await Promise.all([
          cargarArticulos(),
          stockApi.listar(),
        ])
        if (activo) {
          setArticulos(catalogo.data)
          setFilas(stock)
        }
      } catch {
        if (activo) setError(true)
      } finally {
        if (activo) setCargando(false)
      }
    })()
    return () => {
      activo = false
    }
  }, [])

  async function recargar(articulo, est) {
    const stock = await stockApi.listar({
      articuloId: articulo || undefined,
      estado: est || undefined,
    })
    setFilas(stock)
  }

  function cambiarArticulo(valor) {
    setArticuloId(valor)
    recargar(valor, estado)
  }

  function cambiarEstado(valor) {
    setEstado(valor)
    recargar(articuloId, valor)
  }

  if (error) {
    return <p className="error">No se pudo cargar el stock.</p>
  }

  if (cargando) {
    return <p className="cargando">Cargando stock…</p>
  }

  return (
    <section>
      <h1>stock y lotes</h1>

      <div className="filtros">
        <select
          aria-label="artículo"
          value={articuloId}
          onChange={(e) => cambiarArticulo(e.target.value)}
        >
          <option value="">todos los artículos</option>
          {articulos.map((a) => (
            <option key={a.id} value={a.id}>
              {a.nombre}
            </option>
          ))}
        </select>
        <select
          aria-label="estado"
          value={estado}
          onChange={(e) => cambiarEstado(e.target.value)}
        >
          <option value="">todos los estados</option>
          {Object.entries(ESTADOS).map(([valor, info]) => (
            <option key={valor} value={valor}>
              {info.label}
            </option>
          ))}
        </select>
      </div>

      <div className="tarjeta">
        {filas.length === 0 ? (
          <p className="vacio">Sin stock para ese filtro.</p>
        ) : (
          <table>
            <thead>
              <tr>
                <th>artículo</th>
                <th>lote</th>
                <th>ubicación</th>
                <th>caducidad</th>
                <th>disp. / total</th>
                <th>estado</th>
              </tr>
            </thead>
            <tbody>
              {filas.map((fila, indice) => (
                <tr key={`${fila.lote}-${fila.ubicacion}-${indice}`}>
                  <td>{fila.articulo}</td>
                  <td>{fila.lote}</td>
                  <td>{fila.ubicacion}</td>
                  <td>{fila.caducidad ?? '—'}</td>
                  <td>
                    {fila.disponible} / {fila.total}
                  </td>
                  <td>
                    <span
                      className={`tag ${ESTADOS[fila.estado]?.clase ?? 'tag-neutro'}`}
                    >
                      {ESTADOS[fila.estado]?.label ?? fila.estado}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </section>
  )
}
