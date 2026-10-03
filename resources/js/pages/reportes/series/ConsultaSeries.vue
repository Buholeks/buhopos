<template>
  <div class="mx-auto max-w-screen-2xl space-y-6 p-4 md:p-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div><p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Inventario · Trazabilidad</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Consulta de series</h1><p class="mt-1 text-sm text-slate-500">Del ingreso a la última operación: consulta el recorrido de cada unidad.</p></div>
      <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">Solo consulta · Sucursales autorizadas</span>
    </header>
    <nav class="flex gap-2 border-b border-slate-200" aria-label="Vistas de series">
      <button v-for="t in tabs" :key="t.id" class="border-b-2 px-4 py-3 text-sm font-semibold" :class="tab === t.id ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500'" :aria-current="tab === t.id ? 'page' : undefined" @click="cambiarTab(t.id)">{{ t.nombre }}</button>
    </nav>
    <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <section v-if="tab === 'consulta'" class="space-y-5">
      <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="buscar(1)">
        <label for="serie-busqueda" class="text-sm font-semibold text-slate-800">Número de serie, IMEI o IMEI 2</label>
        <div class="mt-2 flex gap-2"><input id="serie-busqueda" v-model="busqueda" autofocus autocomplete="off" class="input flex-1 font-mono" placeholder="Escribe o escanea el identificador" minlength="2" maxlength="100" required /><button class="primary" :disabled="buscando">{{ buscando ? 'Buscando…' : 'Buscar serie' }}</button></div>
        <p class="mt-2 text-xs text-slate-500">Incluye unidades vendidas, devueltas y dadas de baja. Las coincidencias exactas aparecen primero.</p>
      </form>
      <div v-if="resultados" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex justify-between bg-slate-50 px-4 py-3 text-sm"><strong>{{ resultados.total }} coincidencias</strong><span>Selecciona una unidad para abrir su expediente</span></div>
        <p v-if="!resultados.total" class="p-8 text-center text-slate-500">No se encontraron series dentro de tu alcance autorizado.</p>
        <button v-for="s in resultados.data" :key="s.id" class="flex w-full flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-left hover:bg-teal-50" :class="detalle?.serie.id === s.id ? 'bg-teal-50' : ''" @click="abrir(s.id)">
          <div><div class="font-mono font-semibold text-slate-900">{{ identificador(s) }}</div><div class="text-sm text-slate-500">{{ s.producto }} <span v-if="s.sku">· {{ s.sku }}</span></div></div><div class="flex items-center gap-4 text-sm"><span class="text-slate-500">{{ s.sucursal }}</span><span class="badge">{{ etiqueta(s.estado) }}</span><span aria-hidden="true">→</span></div>
        </button>
        <div v-if="resultados.last_page > 1" class="flex items-center justify-end gap-3 border-t p-3"><button class="secondary" :disabled="buscando || resultados.current_page === 1" @click="buscar(resultados.current_page - 1)">Anterior</button><span class="text-sm">{{ resultados.current_page }} / {{ resultados.last_page }}</span><button class="secondary" :disabled="buscando || resultados.current_page === resultados.last_page" @click="buscar(resultados.current_page + 1)">Siguiente</button></div>
      </div>
      <p v-if="cargandoDetalle" class="p-8 text-center text-slate-500" role="status">Cargando expediente…</p>
      <template v-else-if="detalle">
        <section class="rounded-2xl border border-teal-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Expediente de la unidad</p><h2 class="mt-1 text-xl font-bold">{{ detalle.serie.producto }}</h2><p class="mt-1 font-mono text-lg text-slate-700">{{ identificador(detalle.serie) }}</p></div><div class="text-right"><span class="badge">{{ detalle.en_transito ? 'En tránsito' : etiqueta(detalle.serie.estado) }}</span><p class="mt-2 text-sm text-slate-500">{{ detalle.serie.sucursal }}</p></div></div>
          <dl class="mt-5 grid grid-cols-2 gap-4 border-t pt-4 text-sm md:grid-cols-4"><div v-for="[nombre, valor] in datosSerie" :key="nombre"><dt class="text-xs text-slate-500">{{ nombre }}</dt><dd class="mt-1 break-all font-medium">{{ valor || 'Sin información' }}</dd></div></dl>
        </section>
        <div class="grid gap-4 md:grid-cols-2">
          <section class="card"><h3 class="font-semibold text-slate-900">Compra de origen</h3><template v-if="detalle.compra"><button class="folio mt-3" @click="verDocumento('compra', detalle.compra.id)">{{ detalle.compra.folio }}</button><p class="mt-2 text-sm">{{ fecha(detalle.compra.fecha) }} · {{ detalle.compra.proveedor || 'Sin proveedor registrado' }}</p><p class="mt-2 text-sm text-slate-500">{{ etiqueta(detalle.compra.estado) }}</p><p v-if="detalle.ver_costos" class="mt-3 text-xl font-semibold">{{ dinero(detalle.serie.precio_costo) }} <span class="text-xs font-normal text-slate-500">costo registrado de la serie</span></p></template><p v-else class="mt-3 text-sm text-slate-500">Sin compra disponible en tu alcance autorizado.</p></section>
          <section class="card"><h3 class="font-semibold text-slate-900">Venta vigente</h3><template v-if="ventaActual"><button class="folio mt-3" @click="verDocumento('venta', ventaActual.venta_id)">{{ ventaActual.folio_venta }}</button><p class="mt-2 text-sm">{{ fecha(ventaActual.fecha_venta) }} · {{ ventaActual.cliente || 'Sin cliente registrado' }}</p><p class="mt-2 text-sm text-slate-500">{{ ventaActual.sucursal }} · {{ ventaActual.vendedor || 'Sin vendedor registrado' }}</p><p class="mt-3 text-xl font-semibold">{{ dinero(ventaActual.importe_venta) }} <span class="text-xs font-normal text-slate-500">después de descuentos</span></p></template><p v-else class="mt-3 text-sm text-slate-500">Sin venta vigente disponible. Las operaciones anteriores permanecen en el historial.</p></section>
        </div>
        <section class="card">
          <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold">Historial de la serie</h3><p class="mt-1 text-xs text-slate-500">{{ detalle.eventos.length }} movimientos</p></div><select v-model="tipoEvento" class="input" aria-label="Filtrar movimientos"><option value="">Todos los movimientos</option><option v-for="tipo in tiposEventos" :key="tipo" :value="tipo">{{ etiqueta(tipo) }}</option></select></div>
          <div v-if="!eventos.length" class="p-8 text-center text-slate-500">No hay movimientos registrados para esta selección.</div>
          <ol class="mt-5 space-y-0"><li v-for="(e, i) in eventos" :key="`${e.tipo}-${e.referencia_id}-${i}`" class="relative ml-2 border-l-2 border-slate-200 pb-6 pl-6 last:pb-0"><span class="absolute -left-2 top-1 h-3.5 w-3.5 rounded-full border-2 border-white bg-teal-600"></span><div class="flex flex-wrap items-center justify-between gap-2"><strong class="text-sm">{{ etiqueta(e.tipo) }}</strong><span class="text-xs text-slate-500">{{ fecha(e.fecha, e.precision === 'fecha_hora') }}</span></div><div class="mt-1 flex flex-wrap items-center gap-2 text-sm"><button v-if="tieneDocumento(e)" class="folio" @click="verDocumento(e.referencia_tipo, e.referencia_id)">{{ e.folio || `Documento #${e.referencia_id}` }}</button><span v-else>{{ e.folio || 'Sin folio' }}</span><span class="text-slate-500">{{ e.sucursal }}<template v-if="e.usuario"> · {{ e.usuario }}</template></span></div><p v-if="e.motivo || e.notas" class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ e.motivo }}{{ e.motivo && e.notas ? ' · ' : '' }}{{ e.notas }}</p><span class="mt-2 inline-block rounded bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">{{ e.fuente === 'Kardex' ? 'Movimiento registrado' : 'Recuperado del documento' }}</span></li></ol>
        </section>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-900"><p v-for="aviso in detalle.avisos" :key="aviso">{{ aviso }}</p></div>
      </template>
      <div v-else-if="!resultados && !buscando" class="rounded-2xl border border-dashed border-slate-300 p-12 text-center"><Search class="mx-auto h-9 w-9 text-teal-600" /><h2 class="mt-4 font-semibold text-slate-700">Toda la historia de una unidad, en un lugar</h2><p class="mt-2 text-sm text-slate-500">Busca una serie para consultar su compra, ventas, traspasos y devoluciones.</p></div>
    </section>
    <section v-else class="space-y-5">
      <form class="card" @submit.prevent="consultar(1)">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <label class="field">Venta desde<input v-model="filtros.fecha_desde" type="date" class="input" required /></label><label class="field">Venta hasta<input v-model="filtros.fecha_hasta" type="date" class="input" :min="filtros.fecha_desde" required /></label>
          <label class="field">Sucursal de venta<select v-model="filtros.sucursal_id" class="input"><option value="">Todas las autorizadas</option><option v-for="s in catalogos.sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option></select></label>
          <label class="field">Estado de la operación<select v-model="filtros.estado" class="input"><option value="vigente">Vendido</option><option value="cancelada">Canceladas</option><option value="devuelta">Con devolución</option><option value="todas">Todas las operaciones</option></select></label>
          <label class="field sm:col-span-2">Categorías <span class="text-xs font-normal text-slate-400">Ctrl / ⌘ para seleccionar varias</span><select v-model="filtros.categoria_ids" multiple class="input min-h-24"><option :value="0">Sin categoría</option><option v-for="c in catalogos.categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option></select><span class="text-xs font-normal text-slate-400">Sin selección: todas</span></label>
          <label class="field">Producto<input v-model="filtros.producto" class="input" maxlength="100" placeholder="Nombre del producto" /></label><label class="field">Proveedor<input v-model="filtros.proveedor" class="input" maxlength="100" placeholder="Nombre comercial" /></label>
        </div>
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4"><p class="max-w-2xl text-xs text-slate-500">Una fila por serie y venta. Una reventa se muestra como otra operación. Las categorías corresponden al catálogo actual; las fechas y sucursales corresponden a la venta.</p><div class="flex gap-2"><button type="button" class="secondary" @click="limpiar">Limpiar</button><button class="primary" :disabled="cargandoReporte">{{ cargandoReporte ? 'Consultando…' : 'Aplicar filtros' }}</button></div></div>
      </form>
      <div v-if="reporte" class="grid grid-cols-2 gap-3 lg:grid-cols-5"><div v-for="m in metricas" :key="m.nombre" class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ m.nombre }}</p><p class="mt-2 text-xl font-bold text-slate-900">{{ m.valor }}</p></div></div>
      <div v-if="reporte" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b p-4"><div><h3 class="font-semibold">Operaciones de venta</h3><p class="mt-1 text-xs text-slate-500">{{ aplicados.fecha_desde }} al {{ aplicados.fecha_hasta }} · {{ etiqueta(aplicados.estado) }}</p></div><div class="flex flex-wrap gap-2"><select v-model="orden" class="input" aria-label="Orden del reporte" @change="consultar(1, true)"><option value="fecha_venta:desc">Venta más reciente</option><option value="fecha_venta:asc">Venta más antigua</option><option value="producto:asc">Producto A–Z</option><option value="importe_venta:desc">Mayor importe</option></select><button class="primary" :disabled="exportando || cargandoReporte || !reporte.items.total" @click="exportar('excel')">{{ exportando === 'excel' ? 'Exportando…' : 'Exportar Excel' }}</button><button class="secondary" :disabled="exportando || cargandoReporte || !reporte.items.total" @click="exportar('pdf')">{{ exportando === 'pdf' ? 'Exportando…' : 'Exportar PDF' }}</button></div></div>
        <p v-if="cargandoReporte" role="status" class="p-3 text-center text-sm text-teal-700">Actualizando resultados…</p>
        <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">Expediente</th><th v-for="c in columnasVisibles" :key="c.key" class="px-4 py-3">{{ c.nombre }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="r in reporte.items.data" :key="r.id" class="hover:bg-slate-50"><td class="px-4 py-3"><button class="folio" @click="abrirDesdeReporte(r.serie_id)">Ver serie →</button></td><td v-for="c in columnasVisibles" :key="c.key" class="px-4 py-3" :class="['serie', 'imei', 'imei2'].includes(c.key) ? 'font-mono' : ''"><span v-if="c.key === 'estado_operacion'" class="badge">{{ etiqueta(r[c.key]) }}</span><template v-else>{{ valorCelda(r, c) }}</template></td></tr><tr v-if="!reporte.items.total"><td :colspan="columnasVisibles.length + 1" class="p-10 text-center text-slate-500">No hay operaciones para los filtros seleccionados.</td></tr></tbody></table></div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t p-4 text-sm"><span>{{ reporte.items.total }} operaciones · Totales de todos los resultados filtrados</span><div class="flex items-center gap-3"><select v-model="porPagina" class="input" aria-label="Filas por página" @change="consultar(1, true)"><option :value="20">20 filas</option><option :value="50">50 filas</option><option :value="100">100 filas</option></select><button class="secondary" :disabled="cargandoReporte || reporte.items.current_page === 1" @click="consultar(reporte.items.current_page - 1, true)">Anterior</button><span>{{ reporte.items.current_page }} / {{ reporte.items.last_page }}</span><button class="secondary" :disabled="cargandoReporte || reporte.items.current_page >= reporte.items.last_page" @click="consultar(reporte.items.current_page + 1, true)">Siguiente</button></div></div>
      </div>
      <p v-else class="p-8 text-center text-sm text-slate-500">Aplica los filtros para consultar las series vendidas.</p>
    </section>
    <div v-if="documento" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="documento = null" @keydown.esc="documento = null">
      <section role="dialog" aria-modal="true" aria-labelledby="documento-titulo" tabindex="-1" class="max-h-[85vh] w-full max-w-2xl overflow-auto rounded-2xl bg-white p-6 shadow-xl"><div class="flex justify-between gap-3"><div><h2 id="documento-titulo" class="text-lg font-bold">{{ documento.titulo }}</h2><p class="mt-1 text-xs text-slate-500">Información del documento correspondiente a esta serie</p></div><button autofocus class="secondary" aria-label="Cerrar documento" @click="documento = null">Cerrar</button></div><dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2"><div v-for="[k, v] in documento.campos" :key="k"><dt class="text-xs text-slate-500">{{ k }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm font-medium">{{ v ?? 'Sin información registrada' }}</dd></div></dl></section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Search } from 'lucide-vue-next';
import http from '@/lib/http';

const tabs = [{ id: 'consulta', nombre: 'Consultar serie' }, { id: 'reporte', nombre: 'Reporte de series' }];
const tab = ref('consulta'), error = ref(''), busqueda = ref(''), buscando = ref(false), cargandoDetalle = ref(false), cargandoReporte = ref(false), exportando = ref('');
const resultados = ref(null), detalle = ref(null), reporte = ref(null), documento = ref(null), tipoEvento = ref('');
const catalogos = ref({ sucursales: [], categorias: [], ver_costos: false });
const hoy = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Mexico_City', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const iniciales = () => ({ fecha_desde: `${hoy().slice(0, 7)}-01`, fecha_hasta: hoy(), sucursal_id: '', categoria_ids: [], estado: 'vigente', producto: '', proveedor: '' });
const filtros = reactive(iniciales()), aplicados = ref(null), orden = ref('fecha_venta:desc'), porPagina = ref(20);
const columnas = [ ['serie', 'Serie'], ['imei', 'IMEI'], ['producto', 'Producto'], ['categoria', 'Categoría'], ['fecha_compra', 'Fecha de compra'], ['folio_compra', 'Folio de compra'], ['proveedor', 'Proveedor'], ['costo_compra', 'Costo de compra'], ['fecha_venta', 'Fecha de venta'], ['folio_venta', 'Folio de venta'], ['cliente', 'Cliente'], ['sucursal', 'Sucursal de venta'], ['importe_venta', 'Venta después de descuentos'], ['estado_operacion', 'Estado'] ].map(([key, nombre]) => ({ key, nombre }));
const columnasVisibles = computed(() => columnas.filter(c => c.key !== 'costo_compra' || reporte.value?.ver_costos));
const identificador = s => s.serie || s.imei || s.imei2 || `ID #${s.id}`;
const etiqueta = s => ({ todas: 'Todas las operaciones', vigente: 'Vendido', devuelta: 'Con devolución', cancelacion_venta: 'Cancelación de venta', cancelacion_compra: 'Cancelación de compra', traspaso_salida: 'Envío de traspaso', traspaso_entrada: 'Recepción de traspaso', cancelacion_traspaso: 'Cancelación de traspaso', rechazo_traspaso: 'Rechazo de traspaso', devolucion_cliente: 'Devolución de cliente', devolucion_proveedor: 'Devolución a proveedor', actualizacion_serie: 'Actualización de serie' }[s] || (s ? s.replaceAll('_', ' ').replace(/^./, c => c.toUpperCase()) : 'Sin información'));
const dinero = n => n === null || n === undefined ? 'Sin información' : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n));
function fecha(v, hora = false) { if (!v) return 'Fecha no registrada'; const [dia, tiempo] = v.replace('T', ' ').split(' '); const partes = dia.split('-'); return `${partes[2]}/${partes[1]}/${partes[0]}${hora && tiempo ? ` ${tiempo.slice(0, 5)}` : ''}`; }
const ventaActual = computed(() => detalle.value?.serie.estado === 'vendido' ? detalle.value.ventas.find(v => Number(v.venta_id) === Number(detalle.value.serie.venta_id) && v.estado_operacion === 'vigente') : null);
const datosSerie = computed(() => {
  const s = detalle.value.serie;
  const ultimo = [...detalle.value.eventos].reverse().find(e => e.fecha);
  const origen = detalle.value.compra?.fecha?.slice(0, 10);
  const destino = ventaActual.value?.fecha_venta?.slice(0, 10) || hoy();
  const dias = origen ? Math.max(0, Math.floor((Date.parse(`${destino}T12:00:00Z`) - Date.parse(`${origen}T12:00:00Z`)) / 86400000)) : null;
  return [['Serie', s.serie], ['IMEI', s.imei], ['IMEI 2', s.imei2], ['Categoría', s.categoria], ['Código', s.codigo], ['Variante / SKU', s.sku], ['Último movimiento', ultimo ? `${etiqueta(ultimo.tipo)} · ${fecha(ultimo.fecha)}` : null], [ventaActual.value ? 'Tiempo hasta venta vigente' : 'Tiempo desde la compra', dias !== null ? `${dias} días` : null], ['Notas', s.notas]];
});
const tiposEventos = computed(() => [...new Set(detalle.value?.eventos.map(e => e.tipo) || [])]);
const eventos = computed(() => detalle.value?.eventos.filter(e => !tipoEvento.value || e.tipo === tipoEvento.value) || []);
const metricas = computed(() => { const t = reporte.value.totales; return [{ nombre: 'Series distintas', valor: t.series }, { nombre: 'Operaciones', valor: t.operaciones }, { nombre: 'Venta registrada', valor: dinero(t.vendido) }, { nombre: 'Devoluciones', valor: dinero(t.devuelto) }, { nombre: 'Venta neta (sin canceladas)', valor: dinero(t.neto) }]; });
function fallo(e) { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'No fue posible completar la consulta. Intenta de nuevo.'; }
let solicitudBusqueda = 0, solicitudDetalle = 0;
async function buscar(page = 1) { const ticket = ++solicitudBusqueda; error.value = ''; buscando.value = true; resultados.value = null; detalle.value = null; ++solicitudDetalle; cargandoDetalle.value = false; try { const { data } = await http.get('/api/consulta-series/buscar', { params: { q: busqueda.value.trim(), page } }); if (ticket !== solicitudBusqueda) return; resultados.value = data; if (data.total === 1) await abrir(data.data[0].id); } catch (e) { if (ticket === solicitudBusqueda) fallo(e); } finally { if (ticket === solicitudBusqueda) buscando.value = false; } }
async function abrir(id) { const ticket = ++solicitudDetalle; error.value = ''; cargandoDetalle.value = true; detalle.value = null; tipoEvento.value = ''; try { const { data } = await http.get(`/api/consulta-series/${id}`); if (ticket === solicitudDetalle) detalle.value = data; } catch (e) { if (ticket === solicitudDetalle) fallo(e); } finally { if (ticket === solicitudDetalle) cargandoDetalle.value = false; } }
function abrirDesdeReporte(id) { tab.value = 'consulta'; resultados.value = null; abrir(id); }
function cambiarTab(id) { tab.value = id; error.value = ''; }
function parametros(f, page = 1) { const [campo, direccion] = orden.value.split(':'); return { ...f, page, per_page: porPagina.value, orden: campo, direccion }; }
async function consultar(page = 1, conservar = false) { if (cargandoReporte.value) return; cargandoReporte.value = true; error.value = ''; const f = JSON.parse(JSON.stringify(conservar && aplicados.value ? aplicados.value : filtros)); try { const { data } = await http.get('/api/consulta-series/reporte', { params: parametros(f, page) }); reporte.value = data; aplicados.value = f; } catch (e) { fallo(e); } finally { cargandoReporte.value = false; } }
function limpiar() { Object.assign(filtros, iniciales()); }
function valorCelda(r, c) { const v = r[c.key]; if (['costo_compra', 'costo', 'importe_venta', 'importe_devuelto', 'importe_neto'].includes(c.key)) return dinero(v); if (c.key.startsWith('fecha_')) return v ? fecha(v) : '—'; return v ?? '—'; }
async function exportar(formato) { exportando.value = formato; error.value = ''; try { const { data } = await http.get('/api/consulta-series/exportar', { params: { ...parametros(aplicados.value), formato }, responseType: 'blob' }); const url = URL.createObjectURL(data); const a = document.createElement('a'); a.href = url; a.download = `series_${aplicados.value.fecha_desde}_${aplicados.value.fecha_hasta}.${formato === 'pdf' ? 'pdf' : 'xlsx'}`; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); } catch (e) { if (e.response?.data instanceof Blob) { try { e.response.data = JSON.parse(await e.response.data.text()); } catch {} } fallo(e); } finally { exportando.value = ''; } }
function tieneDocumento(e) { return e.referencia_tipo === 'compra' && detalle.value.compra?.id === Number(e.referencia_id) || e.referencia_tipo === 'venta' && detalle.value.ventas.some(v => Number(v.venta_id) === Number(e.referencia_id)) || detalle.value.documentos?.some(d => d.tipo === e.referencia_tipo && Number(d.id) === Number(e.referencia_id)); }
function verDocumento(tipo, id) {
  if (tipo === 'compra') { const c = detalle.value.compra; if (!c) return; documento.value = { titulo: `Compra ${c.folio}`, campos: [['Fecha', fecha(c.fecha)], ['Proveedor', c.proveedor], ['Estado', etiqueta(c.estado)], ['Responsable', c.usuario], ['Notas', c.notas], ...(detalle.value.ver_costos ? [['Costo de la serie', dinero(detalle.value.serie.precio_costo)]] : [])] }; }
  else if (tipo === 'venta') { const v = detalle.value.ventas.find(v => Number(v.venta_id) === Number(id)); if (!v) return; documento.value = { titulo: `Venta ${v.folio_venta}`, campos: [['Fecha', fecha(v.fecha_venta)], ['Cliente', v.cliente], ['Sucursal', v.sucursal], ['Vendedor', v.vendedor], ['Estado', etiqueta(v.estado_operacion)], ['Importe después de descuentos', dinero(v.importe_venta)], ['Devuelto', dinero(v.importe_devuelto)], ['Venta neta', dinero(v.importe_neto)], ['Motivo de cancelación', v.motivo_cancelacion], ...(detalle.value.ver_costos ? [['Costo al vender', dinero(v.costo)]] : [])] }; }
  else { const d = detalle.value.documentos?.find(d => d.tipo === tipo && Number(d.id) === Number(id)); if (d) documento.value = { titulo: `${etiqueta(tipo)} ${d.folio || ''}`, campos: Object.entries(d.campos) }; }
}
onMounted(async () => { try { const { data } = await http.get('/api/consulta-series/catalogos'); catalogos.value = data; } catch (e) { fallo(e); } });
</script>

<style scoped>
@reference "../../../../css/app.css";
.input { @apply rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100; min-width: 0; }
.primary { @apply rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50; }
.secondary { @apply rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40; }
.card { @apply rounded-2xl border border-slate-200 bg-white p-5 shadow-sm; }
.field { @apply flex flex-col gap-1.5 text-xs font-semibold text-slate-600; }
.badge { @apply rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800; }
.folio { @apply font-semibold text-teal-700 underline decoration-teal-200 underline-offset-4 hover:text-teal-900; }
</style>
