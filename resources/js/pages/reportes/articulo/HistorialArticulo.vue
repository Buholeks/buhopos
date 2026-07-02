<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <History class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">Consulta de articulo</h1>
                        <p class="text-xs text-slate-500">Historial de altas, compras, ventas, ajustes y traspasos</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    :disabled="cargando || !seleccionado"
                    @click="consultar"
                >
                    <Loader2 v-if="cargando" class="h-4 w-4 animate-spin" />
                    <RefreshCw v-else class="h-4 w-4" />
                    Recargar
                </button>
            </div>
        </div>

        <main class="mx-auto max-w-7xl space-y-5 px-4 py-5 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto]">
                    <BaseSearchSelect
                        v-model="selectorId"
                        label="Articulo"
                        placeholder="Buscar por nombre, codigo, SKU o IMEI"
                        :fetcher="buscarProductos"
                        :selected-item="seleccionado"
                        label-key="label"
                        sub-label-key="sub_label"
                        value-key="selector_id"
                        :min-chars="2"
                        :limit="20"
                        required
                        @selected="seleccionarProducto"
                    />

                    <BaseInput v-model="filtros.fecha_inicio" type="date" label="Fecha inicial" />
                    <BaseInput v-model="filtros.fecha_hasta" type="date" label="Fecha final" required />

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Movimiento</label>
                        <select
                            v-model="filtros.tipo"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition hover:border-emerald-500 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        >
                            <option value="">Todos</option>
                            <option v-for="tipo in tiposDisponibles" :key="tipo.value" :value="tipo.value">
                                {{ tipo.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button
                            type="button"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 lg:w-auto"
                            :disabled="cargando || !seleccionado"
                            @click="consultar"
                        >
                            <Search class="h-4 w-4" />
                            Buscar
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="producto" class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))]">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase text-slate-400">Articulo</p>
                    <h2 class="mt-1 text-base font-semibold text-slate-900">{{ producto.nombre }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ producto.codigo || "-" }}
                        <span v-if="producto.sku"> | {{ producto.sku }}</span>
                    </p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ [producto.categoria, producto.marca, producto.modelo, producto.unidad].filter(Boolean).join(" | ") || "Sin clasificacion" }}
                    </p>
                </div>

                <ResumenItem label="Existencia actual" :value="fmtCantidad(resumen?.existencia_actual)" tone="emerald" />
                <ResumenItem label="Saldo al corte" :value="fmtCantidad(resumen?.saldo_al_hasta)" />
                <ResumenItem label="Entradas" :value="fmtCantidad(resumen?.total_entradas)" tone="green" />
                <ResumenItem label="Salidas" :value="fmtCantidad(resumen?.total_salidas)" tone="red" />
                <ResumenItem label="Diferencia" :value="fmtCantidad(resumen?.diferencia)" tone="indigo" />
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Historial de movimientos</h2>
                        <p class="text-xs text-slate-500">{{ resumen?.movimientos ?? 0 }} registros encontrados</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Entrada</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span>Salida</span>
                    </div>
                </div>

                <div v-if="cargando" class="flex items-center justify-center gap-2 py-14 text-sm text-slate-500">
                    <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                    Cargando historial...
                </div>

                <div v-else-if="!producto" class="py-14 text-center">
                    <PackageSearch class="mx-auto h-10 w-10 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-slate-700">Busca un articulo para ver su historial.</p>
                </div>

                <div v-else-if="!movimientos.length" class="py-14 text-center">
                    <Inbox class="mx-auto h-10 w-10 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-slate-700">Sin movimientos para los filtros seleccionados.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-left">Tipo</th>
                                <th class="px-4 py-3 text-right">Antes</th>
                                <th class="px-4 py-3 text-right">Despues</th>
                                <th class="px-4 py-3 text-right">Entradas</th>
                                <th class="px-4 py-3 text-right">Salidas</th>
                                <th class="px-4 py-3 text-left">Usuario</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3 text-left">Referencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ fmtFechaHora(mov) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="tipoClass(mov.tipo)">{{ mov.tipo_label }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600">{{ fmtCantidad(mov.antes) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600">{{ fmtCantidad(mov.despues) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">
                                    {{ mov.entrada ? fmtCantidad(mov.entrada) : "-" }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-red-700">
                                    {{ mov.salida ? fmtCantidad(mov.salida) : "-" }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ mov.usuario || "-" }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-slate-900">{{ fmtCantidad(mov.saldo) }}</td>
                                <td class="px-4 py-3">
                                    <div class="max-w-64">
                                        <p class="truncate font-medium text-slate-700">{{ mov.referencia || "-" }}</p>
                                        <p v-if="mov.nota" class="truncate text-xs text-slate-400">{{ mov.nota }}</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="producto" class="grid grid-cols-1 gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-3">
                    <p class="font-semibold text-emerald-700">Total entradas: {{ fmtCantidad(resumen?.total_entradas) }}</p>
                    <p class="font-semibold text-red-700">Total salidas: {{ fmtCantidad(resumen?.total_salidas) }}</p>
                    <p class="font-semibold text-indigo-700">Diferencia: {{ fmtCantidad(resumen?.diferencia) }}</p>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { defineComponent, h, reactive, ref } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import { toastError, toastWarning } from "@/lib/alert";
import {
    History,
    Inbox,
    Loader2,
    PackageSearch,
    RefreshCw,
    Search,
} from "lucide-vue-next";

const ResumenItem = defineComponent({
    props: {
        label: { type: String, required: true },
        value: { type: String, default: "0.000" },
        tone: { type: String, default: "slate" },
    },
    setup(props) {
        return () =>
            h("div", { class: "rounded-xl border border-slate-200 bg-white p-4 shadow-sm" }, [
                h("p", { class: "text-xs font-semibold uppercase text-slate-400" }, props.label),
                h("p", { class: ["mt-2 text-xl font-semibold", toneText(props.tone)] }, props.value),
            ]);
    },
});

const selectorId = ref(null);
const seleccionado = ref(null);
const producto = ref(null);
const resumen = ref(null);
const movimientos = ref([]);
const cargando = ref(false);

const filtros = reactive({
    fecha_inicio: "",
    fecha_hasta: hoy(),
    tipo: "",
});

const tiposDisponibles = [
    { value: "alta_producto", label: "Alta de articulo" },
    { value: "alta_variante", label: "Alta de variante" },
    { value: "alta_serie", label: "Alta de serie" },
    { value: "saldo_inicial", label: "Saldo inicial" },
    { value: "compra", label: "Compra" },
    { value: "venta", label: "Venta" },
    { value: "cancelacion_compra", label: "Cancelacion compra" },
    { value: "cancelacion_venta", label: "Cancelacion venta" },
    { value: "devolucion_cliente", label: "Devolucion cliente" },
    { value: "devolucion_proveedor", label: "Devolucion proveedor" },
    { value: "anulacion_devolucion_proveedor", label: "Anulacion devolucion proveedor" },
    { value: "ajuste_positivo", label: "Ajuste positivo" },
    { value: "ajuste_negativo", label: "Ajuste negativo" },
    { value: "traspaso_entrada", label: "Traspaso entrada" },
    { value: "traspaso_salida", label: "Traspaso salida" },
    { value: "rechazo_traspaso", label: "Rechazo de traspaso" },
    { value: "cancelacion_traspaso", label: "Cancelacion de traspaso" },
];

async function buscarProductos(q) {
    const { data } = await axios.get("/api/reportes/articulo/buscar-productos", { params: { q } });
    return data;
}

function seleccionarProducto(item) {
    seleccionado.value = item;
    producto.value = null;
    resumen.value = null;
    movimientos.value = [];
}

async function consultar() {
    if (!seleccionado.value) {
        toastWarning("Selecciona un articulo.");
        return;
    }

    cargando.value = true;
    try {
        const { data } = await axios.get("/api/reportes/articulo/historial", {
            params: {
                producto_id: seleccionado.value.producto_id,
                variante_id: seleccionado.value.variante_id || undefined,
                fecha_inicio: filtros.fecha_inicio || undefined,
                fecha_hasta: filtros.fecha_hasta || undefined,
                tipo: filtros.tipo || undefined,
            },
        });

        producto.value = data.producto;
        resumen.value = data.resumen;
        movimientos.value = data.movimientos ?? [];
    } catch (e) {
        console.error(e);
        toastError(e.response?.data?.message ?? "No se pudo cargar el historial.");
    } finally {
        cargando.value = false;
    }
}

function fmtCantidad(value) {
    return new Intl.NumberFormat("es-MX", {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Number(value ?? 0));
}

function fmtFechaHora(mov) {
    const value = mov?.fecha;
    if (!value) return "-";

    if (mov?.fecha_utc) {
        const d = new Date(value);
        if (!Number.isNaN(d.getTime())) {
            return d.toLocaleString("es-MX", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
            });
        }
    }

    const texto = String(value).replace("T", " ").slice(0, 19);
    const [fecha, hora = ""] = texto.split(" ");
    const [y, m, d] = fecha.split("-");
    const [hh = "00", mm = "00"] = hora.split(":");

    if (!y || !m || !d) return texto;

    return `${d}/${m}/${y}, ${hh}:${mm}`;
}

function hoy() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}

function toneText(tone) {
    return {
        emerald: "text-emerald-700",
        green: "text-green-700",
        red: "text-red-700",
        indigo: "text-indigo-700",
        slate: "text-slate-900",
    }[tone] ?? "text-slate-900";
}

function tipoClass(tipo) {
    const base = "inline-flex rounded-full px-2.5 py-1 text-xs font-semibold";
    if (["compra", "devolucion_cliente", "ajuste_positivo", "traspaso_entrada", "saldo_inicial", "alta_serie", "anulacion_devolucion_proveedor", "rechazo_traspaso", "cancelacion_traspaso"].includes(tipo)) {
        return `${base} bg-emerald-50 text-emerald-700`;
    }
    if (["venta", "devolucion_proveedor", "ajuste_negativo", "traspaso_salida", "cancelacion_compra"].includes(tipo)) {
        return `${base} bg-red-50 text-red-700`;
    }
    return `${base} bg-slate-100 text-slate-700`;
}
</script>
