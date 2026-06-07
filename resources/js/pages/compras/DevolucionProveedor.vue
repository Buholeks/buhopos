<template>
    <main class="mx-auto max-w-6xl space-y-4 p-3 sm:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-lg font-semibold">Devolución a proveedor</h1>
            <p class="mt-1 text-xs text-slate-500">Retira mercancía disponible y registra el importe que debe compensar el proveedor.</p>
        </section>

        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2">
            <BaseSearchSelect v-model="form.compra_id" label="Compra" placeholder="Buscar folio o proveedor" :fetcher="buscarCompras" :selected-item="compra" :label-key="labelCompra" value-key="id" @selected="seleccionarCompra" required />
            <BaseInput v-model="form.fecha" label="Fecha de devolución" type="date" required />
            <BaseInput v-model="form.referencia" label="Referencia" placeholder="Nota de crédito, guía o folio" />
            <BaseInput v-model="form.motivo" label="Motivo" placeholder="Producto sin rotación, defecto, acuerdo comercial..." required />
            <BaseSearchSelect v-if="reembolsoPendiente > 0" v-model="form.destino_excedente" label="Destino del dinero" placeholder="Selecciona dónde queda" :items="destinosDinero" label-key="nombre" value-key="id" required />
            <BaseSearchSelect v-if="reembolsoPendiente > 0 && form.destino_excedente === 'caja'" v-model="form.forma_reembolso" label="Forma de ingreso a caja" placeholder="Selecciona la forma" :items="formasReembolso" label-key="nombre" value-key="id" required />
        </section>

        <section v-if="compra" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold">Compra {{ compra.folio || `#${compra.id}` }}</h2>
                    <p class="text-xs text-slate-500">{{ compra.proveedor?.nombre_comercial || "Sin proveedor" }} · Estado {{ labelEstado(compra.estado) }} · Saldo actual {{ fmt(compra.saldo) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500">Total devolución</p>
                    <p class="font-mono text-lg font-semibold text-red-600">{{ fmt(total) }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-3 text-left">Producto</th><th class="px-4 py-3 text-right">Comprado</th><th class="px-4 py-3 text-right">Devuelto</th><th class="px-4 py-3 text-right">Costo</th><th class="px-4 py-3 text-left">Devolver</th><th class="px-4 py-3 text-right">Importe</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="linea in lineas" :key="linea.compra_detalle_id">
                            <td class="px-4 py-3"><p class="font-medium">{{ linea.producto }}</p><p class="text-xs text-slate-400">{{ linea.codigo }} {{ linea.sku ? `· ${linea.sku}` : "" }}</p></td>
                            <td class="px-4 py-3 text-right">{{ num(linea.cantidad_comprada) }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ num(linea.cantidad_devuelta) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ fmt(linea.precio_compra) }}</td>
                            <td class="min-w-64 px-4 py-3">
                                <div v-if="linea.tiene_series" class="max-h-28 space-y-1 overflow-y-auto">
                                    <label v-for="serie in linea.series" :key="serie.id" class="flex items-center gap-2 text-xs"><input v-model="linea.serie_ids" type="checkbox" :value="serie.id" />{{ identificador(serie) }}</label>
                                    <span v-if="!linea.series.length" class="text-xs text-slate-400">Sin series disponibles de esta compra</span>
                                </div>
                                <BaseInput v-else v-model="linea.cantidad" type="number" min="0" :max="linea.cantidad_devolvible" step="0.001" />
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">{{ fmt(subtotal(linea)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="grid gap-3 border-t border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-3">
                <div><span class="text-slate-500">Reduce saldo:</span> <strong>{{ fmt(aplicadoSaldo) }}</strong></div>
                <div><span class="text-slate-500">Reembolso pendiente:</span> <strong class="text-amber-700">{{ fmt(reembolsoPendiente) }}</strong></div>
                <button class="rounded-xl bg-red-600 px-4 py-2 font-semibold text-white disabled:opacity-50 sm:justify-self-end" :disabled="guardando || total <= 0 || !form.motivo.trim() || (reembolsoPendiente > 0 && !form.destino_excedente) || (reembolsoPendiente > 0 && form.destino_excedente === 'caja' && !form.forma_reembolso)" @click="guardar"><Loader2 v-if="guardando" class="mr-2 inline h-4 w-4 animate-spin" />{{ esCancelacion ? "Cancelar compra" : "Confirmar devolución" }}</button>
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import { toastError, toastSuccess } from "@/lib/alert";
import { Loader2 } from "lucide-vue-next";
import { useRoute } from "vue-router";

const route = useRoute();
const compra = ref(null);
const lineas = ref([]);
const guardando = ref(false);
const form = reactive({ compra_id: null, fecha: hoy(), referencia: "", motivo: "", destino_excedente: null, forma_reembolso: null });
const esCancelacion = computed(() => route.query.modo === "cancelar");
const destinosDinero = [{ id: "saldo_favor", nombre: "Saldo a favor con proveedor" }, { id: "caja", nombre: "Ingreso a caja" }];
const formasReembolso = [{ id: "efectivo", nombre: "Efectivo" }, { id: "transferencia", nombre: "Transferencia" }];
const total = computed(() => lineas.value.reduce((s, l) => s + subtotal(l), 0));
const aplicadoSaldo = computed(() => Math.min(+compra.value?.saldo || 0, total.value));
const reembolsoPendiente = computed(() => Math.max(0, total.value - aplicadoSaldo.value));

async function buscarCompras(q) { const { data } = await axios.get("/api/devoluciones-proveedor/compras", { params: { q } }); return data; }
function labelCompra(c) { return `${c.folio || `#${c.id}`} · ${c.proveedor?.nombre_comercial || "Sin proveedor"} · ${labelEstado(c.estado)} · ${fmt(c.total)}`; }
function labelEstado(estado) { return ({ confirmada: "Confirmada", devuelta_parcial: "Devuelta parcial", devuelta: "Devuelta", cancelada: "Cancelada" })[estado] || estado; }
async function seleccionarCompra(item) {
    if (!item) { compra.value = null; lineas.value = []; return; }
    try {
        const { data } = await axios.get(`/api/devoluciones-proveedor/compras/${item.id}`);
        compra.value = data;
        lineas.value = data.detalles.map(d => ({ compra_detalle_id: d.id, producto: d.producto.nombre, codigo: d.producto.codigo, sku: d.variante?.sku, precio_compra: +d.precio_compra, cantidad_comprada: +d.cantidad, cantidad_devuelta: +d.cantidad_devuelta, cantidad_devolvible: +d.cantidad_devolvible, tiene_series: !!d.producto.tiene_series, series: d.series_disponibles || [], serie_ids: [], cantidad: 0 }));
    } catch (e) {
        compra.value = null;
        lineas.value = [];
        toastError(e.response?.data?.message || "No se pudo cargar la compra");
    }
}
function subtotal(l) { return (l.tiene_series ? l.serie_ids.length : (+l.cantidad || 0)) * l.precio_compra; }
async function guardar() {
    guardando.value = true;
    try {
        const payload = { ...form, detalles: lineas.value.map(l => ({ compra_detalle_id: l.compra_detalle_id, cantidad: +l.cantidad || 0, serie_ids: l.serie_ids })) };
        if (esCancelacion.value) await axios.post(`/api/devoluciones-proveedor/compras/${form.compra_id}/cancelar`, payload);
        else await axios.post("/api/devoluciones-proveedor", payload);
        toastSuccess(esCancelacion.value ? "Compra cancelada" : "Devolución registrada");
        await seleccionarCompra({ id: form.compra_id });
        form.motivo = ""; form.referencia = "";
    } catch (e) { toastError(e.response?.data?.message || "No se pudo registrar la devolución"); }
    finally { guardando.value = false; }
}
function identificador(s) { return s.imei || s.serie || s.imei2 || `Serie #${s.id}`; }
const moneda = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const numero = new Intl.NumberFormat("es-MX", { maximumFractionDigits: 3 });
function fmt(v) { return moneda.format(+v || 0); } function num(v) { return numero.format(+v || 0); }
function hoy() { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,"0")}-${String(d.getDate()).padStart(2,"0")}`; }
onMounted(async () => {
    if (route.query.compra_id) {
        form.compra_id = +route.query.compra_id;
        await seleccionarCompra({ id: form.compra_id });
        if (esCancelacion.value) {
            form.motivo = "Cancelación total de compra";
            lineas.value.forEach(l => {
                if (l.tiene_series) l.serie_ids = l.series.map(s => s.id);
                else l.cantidad = l.cantidad_devolvible;
            });
        }
    }
});
</script>
