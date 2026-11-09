import { useEffect, useState } from 'react'
import {
  articulos as cargarArticulos,
  ubicaciones as cargarUbicaciones,
} from '../../api/catalogo'
import * as inventarioApi from '../../api/inventario'
import * as stockApi from '../../api/stock'
import { useSesion } from '../../auth/contexto'

const ESTADOS = {
  ok: { label: 'válido', clase: 'tag-neutro' },
  caduca_pronto: { label: 'caduca pronto', clase: 'tag-fecha' },
  caducado: { label: 'caducado', clase: 'tag-baja' },
  sin_caducidad: { label: 'sin caducidad', clase: 'tag-neutro' },
}

const GESTORES = ['admin', 'gestor_inventario']

export function Stock() {
  const { usuario } = useSesion()
  const esGestor = GESTORES.includes(usuario.rol)

  const [articulos, setArticulos] = useState([])
  const [ubicaciones, setUbicaciones] = useState([])
  const [filas, setFilas] = useState([])
  const [filtros, setFiltros] = useState({
    articuloId: '',
    estado: '',
    lote: '',
    ubicacion: '',
  })
  const [posicion, setPosicion] = useState(null)
  const [accion, setAccion] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    let activo = true
    ;(async () => {
      try {
        const [catalogo, ubis, stock] = await Promise.all([
          cargarArticulos(),
          cargarUbicaciones(),
          stockApi.listar(),
        ])
        if (activo) {
          setArticulos(catalogo.data)
          setUbicaciones(ubis.data)
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

  async function recargarStock(nuevos) {
    setFilas(await stockApi.listar(nuevos))
  }

  function cambiarFiltro(campo, valor) {
    const nuevos = { ...filtros, [campo]: valor }
    setFiltros(nuevos)
    recargarStock(nuevos)
  }

  async function verPosicion(ubicacionId) {
    setAccion(null)
    setPosicion(await stockApi.posicion(ubicacionId))
  }

  async function trasReacondicionar() {
    setAccion(null)
    if (posicion) {
      setPosicion(await stockApi.posicion(posicion.ubicacion_id))
    }
    recargarStock(filtros)
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
          value={filtros.articuloId}
          onChange={(e) => cambiarFiltro('articuloId', e.target.value)}
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
          value={filtros.estado}
          onChange={(e) => cambiarFiltro('estado', e.target.value)}
        >
          <option value="">todos los estados</option>
          {Object.entries(ESTADOS).map(([valor, info]) => (
            <option key={valor} value={valor}>
              {info.label}
            </option>
          ))}
        </select>
        <input
          aria-label="lote"
          placeholder="lote"
          value={filtros.lote}
          onChange={(e) => cambiarFiltro('lote', e.target.value)}
        />
        <input
          aria-label="ubicación"
          placeholder="ubicación"
          value={filtros.ubicacion}
          onChange={(e) => cambiarFiltro('ubicacion', e.target.value)}
        />
      </div>

      <div className="columnas">
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
                  <th>disp. / total</th>
                  <th>estado</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {filas.map((fila, i) => (
                  <tr key={`${fila.lote}-${fila.ubicacion}-${i}`}>
                    <td>{fila.articulo}</td>
                    <td>{fila.lote}</td>
                    <td>{fila.ubicacion}</td>
                    <td>
                      {fila.disponible} / {fila.total}
                      {fila.reservada > 0 && (
                        <span className="tag tag-reservado">
                          R {fila.reservada}
                        </span>
                      )}
                    </td>
                    <td>
                      <span
                        className={`tag ${ESTADOS[fila.estado]?.clase ?? 'tag-neutro'}`}
                      >
                        {ESTADOS[fila.estado]?.label ?? fila.estado}
                      </span>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="enlace"
                        onClick={() => verPosicion(fila.ubicacion_id)}
                      >
                        ver posición
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div>
          {accion ? (
            <FormularioInventario
              accion={accion}
              posicion={posicion}
              ubicaciones={ubicaciones}
              alHecho={trasReacondicionar}
              alCancelar={() => setAccion(null)}
            />
          ) : (
            <DetallePosicion
              posicion={posicion}
              esGestor={esGestor}
              alElegirAccion={(tipo, linea) => setAccion({ tipo, linea })}
            />
          )}
        </div>
      </div>
    </section>
  )
}

function DetallePosicion({ posicion, esGestor, alElegirAccion }) {
  if (!posicion) {
    return (
      <div className="tarjeta">
        <p className="vacio">Pulsa «ver posición» para ver su contenido.</p>
      </div>
    )
  }

  return (
    <div className="tarjeta">
      <h2>
        {posicion.codigo}
        {posicion.refrigerado && <span className="tag tag-frio"> frío</span>}
      </h2>
      {posicion.contenido.length === 0 ? (
        <p className="vacio">Posición libre.</p>
      ) : (
        posicion.contenido.map((linea) => (
          <div className="linea-stock" key={linea.lote_id}>
            <div>
              <div className="titulo">{linea.articulo}</div>
              <div className="sub">
                lote {linea.lote} · {linea.disponible} disp. de {linea.total}
                {linea.reservada > 0 && ` · ${linea.reservada} reservado`}
              </div>
            </div>
            {esGestor && (
              <div className="acciones">
                <button
                  type="button"
                  className="boton boton-secundario"
                  onClick={() => alElegirAccion('regularizar', linea)}
                >
                  regularizar
                </button>
                <button
                  type="button"
                  className="boton boton-secundario"
                  onClick={() => alElegirAccion('trasladar', linea)}
                >
                  trasladar
                </button>
                <button
                  type="button"
                  className="boton boton-secundario"
                  onClick={() => alElegirAccion('baja', linea)}
                >
                  baja
                </button>
              </div>
            )}
          </div>
        ))
      )}
    </div>
  )
}

const TITULOS = {
  regularizar: 'regularizar cantidad',
  trasladar: 'trasladar a otra ubicación',
  baja: 'dar de baja',
}

function FormularioInventario({
  accion,
  posicion,
  ubicaciones,
  alHecho,
  alCancelar,
}) {
  const { tipo, linea } = accion
  const [cantidad, setCantidad] = useState(
    tipo === 'regularizar' ? String(linea.total) : ''
  )
  const [motivo, setMotivo] = useState('')
  const [destinoId, setDestinoId] = useState('')
  const [fase, setFase] = useState('editar')
  const [errores, setErrores] = useState(null)
  const [enviando, setEnviando] = useState(false)

  const destino = ubicaciones.find((u) => u.id === Number(destinoId))

  function aRevisar(evento) {
    evento.preventDefault()
    setErrores(null)
    setFase('revisar')
  }

  async function confirmar() {
    setEnviando(true)
    setErrores(null)
    try {
      if (tipo === 'regularizar') {
        await inventarioApi.regularizar({
          lote_id: linea.lote_id,
          ubicacion_id: posicion.ubicacion_id,
          cantidad: Number(cantidad),
          motivo,
        })
      } else if (tipo === 'trasladar') {
        await inventarioApi.trasladar({
          lote_id: linea.lote_id,
          origen_id: posicion.ubicacion_id,
          destino_id: Number(destinoId),
          cantidad: Number(cantidad),
        })
      } else {
        await inventarioApi.baja({
          lote_id: linea.lote_id,
          ubicacion_id: posicion.ubicacion_id,
          cantidad: Number(cantidad),
          motivo,
        })
      }
      await alHecho()
    } catch (fallo) {
      setErrores(fallo.errores ?? { general: ['No se pudo guardar.'] })
      setFase('editar')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="tarjeta">
      <h2>{TITULOS[tipo]}</h2>
      <p className="sub">
        {linea.articulo} · lote {linea.lote} · {linea.disponible} disp. de{' '}
        {linea.total}
      </p>

      {fase === 'editar' ? (
        <form onSubmit={aRevisar}>
          {tipo === 'trasladar' ? (
            <div className="campo">
              <label htmlFor="destino">ubicación de destino</label>
              <select
                id="destino"
                value={destinoId}
                onChange={(e) => setDestinoId(e.target.value)}
                required
              >
                <option value="">— elige destino —</option>
                {ubicaciones
                  .filter((u) => u.id !== posicion.ubicacion_id)
                  .map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.codigo}
                      {u.refrigerado ? ' (frío)' : ''}
                    </option>
                  ))}
              </select>
            </div>
          ) : null}

          <div className="campo">
            <label htmlFor="cantidad">
              {tipo === 'regularizar' ? 'cantidad contada' : 'cantidad'}
            </label>
            <input
              id="cantidad"
              type="number"
              min={tipo === 'regularizar' ? '0' : '0.001'}
              step="0.001"
              value={cantidad}
              onChange={(e) => setCantidad(e.target.value)}
              required
            />
          </div>

          {tipo !== 'trasladar' && (
            <div className="campo">
              <label htmlFor="motivo">motivo</label>
              <input
                id="motivo"
                value={motivo}
                onChange={(e) => setMotivo(e.target.value)}
                required
              />
            </div>
          )}

          {errores?.general && (
            <p className="error" role="alert">
              {errores.general[0]}
            </p>
          )}

          <div className="acciones">
            <button type="submit" className="boton">
              revisar
            </button>
            <button
              type="button"
              className="boton boton-secundario"
              onClick={alCancelar}
            >
              cancelar
            </button>
          </div>
        </form>
      ) : (
        <div>
          <div className="resumen">
            {tipo === 'regularizar' && (
              <p>
                Cantidad actual <strong>{linea.total}</strong> → nueva{' '}
                <strong>{cantidad}</strong>. Motivo: <strong>{motivo}</strong>.
              </p>
            )}
            {tipo === 'trasladar' && (
              <p>
                Mover <strong>{cantidad}</strong> del lote {linea.lote} a{' '}
                <strong>{destino?.codigo}</strong>. Disponible en origen:{' '}
                {linea.disponible}.
              </p>
            )}
            {tipo === 'baja' && (
              <p>
                Dar de baja <strong>{cantidad}</strong> del lote {linea.lote}.
                Disponible: {linea.disponible}. Motivo:{' '}
                <strong>{motivo}</strong>.
              </p>
            )}
          </div>
          <div className="acciones">
            <button
              type="button"
              className="boton"
              disabled={enviando}
              onClick={confirmar}
            >
              {enviando ? 'guardando…' : 'confirmar'}
            </button>
            <button
              type="button"
              className="boton boton-secundario"
              disabled={enviando}
              onClick={() => setFase('editar')}
            >
              volver
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
