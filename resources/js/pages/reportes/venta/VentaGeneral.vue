<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white px-4 py-5 sm:px-6">
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4">
                <div><h1 class="text-xl font-semibold">Venta general</h1><p class="mt-1 text-sm text-slate-500">Montos de ventas de todas las sucursales de tu empresa.</p></div>
                <div class="flex gap-2">
                    <button v-for="tipo in ['excel', 'pdf']" :key="tipo" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium uppercase disabled:opacity-40" :disabled="!resultado || cargando || !!exportando || pendiente" @click="exportar(tipo)">{{ exportando === tipo ? 'Exportando…' : tipo }}</button>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-[1600px] space-y-5 p-4 sm:p-6">
            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="buscar">
                <div class="flex flex-wrap items-end gap-4">
                    <label class="text-sm">Desde<input v-model="f.fecha_desde" required type="date" class="mt-1 block rounded-lg border-slate-300" /></label>
                    <label class="text-sm">Hasta<input v-model="f.fecha_hasta" required type="date" :min="f.fecha_desde" class="mt-1 block rounded-lg border-slate-300" /></label>
                    <button :disabled="cargando" class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ cargando ? 'Consultando…' : 'Consultar' }}</button>
                    <div class="flex flex-wrap gap-2"><button v-for="p in periodos" :key="p" type="button" class="rounded-full bg-slate-100 px-3 py-2 text-xs hover:bg-slate-200" :disabled="cargando" @click="periodo(p)">{{ p }}</button></div>
                </div>
                <fieldset><legend class="mb-2 text-sm font-medium">Sucursales <span class="font-normal text-slate-500">· sin selección se incluyen todas</span></legend>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="text-sm text-emerald-700 underline" @click="f.sucursal_ids = []">Todas</button>
                        <label v-for="s in sucursales" :key="s.id" class="flex items-center gap-2 text-sm"><input v-model="f.sucursal_ids" type="checkbox" :value="s.id" class="rounded border-slate-300 text-emerald-700" />{{ s.nombre }}</label>
                    </div>
                </fieldset>
            </form>
            <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <p v-if="pendiente" class="text-sm text-amber-700">Hay filtros sin aplicar. Pulsa Consultar para actualizar los resultados.</p>
            <p v-if="cargando" role="status" class="text-sm text-slate-500">Consultando montos por sucursal…</p>
            <template v-if="resultado">
                <p class="text-sm text-slate-500">Periodo consultado: {{ resultado.filtros.fecha_desde }} al {{ resultado.filtros.fecha_hasta }} · {{ resultado.datos.length }} sucursales</p>
                <section class="grid gap-4 sm:grid-cols-3">
                    <div v-for="c in tarjetas" :key="c.key" class="rounded-xl border p-5" :class="c.key === 'neta' ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white'">
                        <p class="text-sm">{{ c.label }}</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ dinero(resultado.totales[c.key]) }}</p>
                    </div>
                </section>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <h2 class="border-b px-4 py-3 font-semibold">Resumen por sucursal</h2>
                    <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-sm">
                        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-3 text-left">Sucursal</th><th v-for="c in columnas" :key="c.key" class="p-3 text-right">{{ c.label }}</th><th class="p-3 text-right">Participación</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><tr v-for="r in resultado.datos" :key="r.id"><td class="p-3 font-medium">{{ r.sucursal }}</td><td v-for="c in columnas" :key="c.key" class="p-3 text-right tabular-nums" :class="c.key === 'neta' ? 'font-semibold' : ''">{{ dinero(r[c.key]) }}</td><td class="p-3 text-right">{{ r.participacion === null ? '—' : r.participacion.toFixed(2) + '%' }}</td></tr>
                            <tr v-if="!resultado.datos.length"><td colspan="7" class="p-6 text-center text-slate-500">No hay sucursales para mostrar.</td></tr>
                        </tbody>
                        <tfoot class="border-t bg-slate-100 font-semibold"><tr><td class="p-3">Total general</td><td v-for="c in columnas" :key="c.key" class="p-3 text-right tabular-nums">{{ dinero(resultado.totales[c.key]) }}</td><td class="p-3 text-right">{{ participacionValida ? '100%' : '—' }}</td></tr></tfoot>
                    </table></div>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold">Venta neta por sucursal</h2><p class="mb-5 mt-1 text-xs text-slate-500">Longitud proporcional al importe absoluto. Rojo indica un monto negativo.</p>
                    <div class="space-y-4"><div v-for="r in resultado.datos" :key="r.id"><div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ r.sucursal }}</span><span class="font-medium tabular-nums">{{ dinero(r.neta) }}</span></div><div class="h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="r.neta < 0 ? 'bg-red-500' : 'bg-emerald-600'" :style="{ width: (Math.abs(r.neta) / maximo * 100) + '%' }"></div></div></div></div>
                </section>
                <p class="text-xs leading-relaxed text-slate-500">Ventas: confirmadas después de descuentos. Los descuentos ya están incluidos y no se restan nuevamente. Venta neta = ventas − devoluciones registradas en el periodo, incluso de ventas anteriores. Canceladas: importe excluido, según la fecha original de venta. No se suman anticipos ni abonos como ventas adicionales. La participación no se calcula si el total es cero o hay montos netos negativos.</p>
            </template>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const fecha = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const hoy = new Date();
const f = reactive({ fecha_desde: fecha(hoy), fecha_hasta: fecha(hoy), sucursal_ids: [] });
const resultado = ref(null), sucursales = ref([]), cargando = ref(false), exportando = ref(''), error = ref('');
const periodos = ['Hoy', 'Ayer', 'Esta semana', 'Este mes'];
const tarjetas = [{ key: 'ventas', label: 'Ventas confirmadas' }, { key: 'devoluciones', label: 'Devoluciones del periodo' }, { key: 'neta', label: 'Venta neta total' }];
const columnas = [{ key: 'ventas', label: 'Ventas' }, { key: 'descuentos', label: 'Descuentos incluidos' }, { key: 'devoluciones', label: 'Devoluciones' }, { key: 'neta', label: 'Venta neta' }, { key: 'canceladas', label: 'Canceladas (excluidas)' }];
const dinero = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n ?? 0);
const firma = (v) => JSON.stringify([v.fecha_desde, v.fecha_hasta, [...(v.sucursal_ids || [])].map(Number).sort((a, b) => a - b)]);
const pendiente = computed(() => resultado.value && firma(f) !== firma(resultado.value.filtros));
const maximo = computed(() => Math.max(1, ...(resultado.value?.datos || []).map(r => Math.abs(r.neta))));
const participacionValida = computed(() => resultado.value?.datos.some(r => r.participacion !== null));
async function buscar() {
    if (cargando.value) return;
    error.value = '';
    if (!f.fecha_desde || !f.fecha_hasta || f.fecha_desde > f.fecha_hasta) { error.value = 'Selecciona un rango de fechas válido.'; return; }
    cargando.value = true;
    try {
        const { data } = await axios.get('/api/reportes/venta-general', { params: { ...f, sucursal_ids: [...f.sucursal_ids] } });
        resultado.value = data; sucursales.value = data.sucursales;
    } catch (e) { resultado.value = null; error.value = e.response?.data?.message || 'No se pudo consultar el reporte. Intenta nuevamente.'; }
    finally { cargando.value = false; }
}
function periodo(p) {
    const fin = new Date(), inicio = new Date();
    if (p === 'Ayer') { inicio.setDate(inicio.getDate() - 1); fin.setDate(fin.getDate() - 1); }
    if (p === 'Esta semana') inicio.setDate(inicio.getDate() - (inicio.getDay() + 6) % 7);
    if (p === 'Este mes') inicio.setDate(1);
    f.fecha_desde = fecha(inicio); f.fecha_hasta = fecha(fin); buscar();
}
async function exportar(formato) {
    exportando.value = formato; error.value = '';
    try {
        const filtros = resultado.value.filtros;
        const { data } = await axios.get('/api/reportes/venta-general/exportar', { params: { ...filtros, formato }, responseType: 'blob' });
        const url = URL.createObjectURL(data), a = document.createElement('a');
        a.href = url; a.download = `venta_general_${filtros.fecha_desde}_${filtros.fecha_hasta}.${formato === 'excel' ? 'xlsx' : 'pdf'}`;
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch { error.value = 'No se pudo exportar el reporte. Intenta nuevamente.'; }
    finally { exportando.value = ''; }
}
onMounted(buscar);
</script>
