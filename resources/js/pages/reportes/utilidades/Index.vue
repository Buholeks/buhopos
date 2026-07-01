<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Reporte de utilidades</h1>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Utilidad bruta con costo histórico y descuentos aplicados.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <Loader2 v-if="cargando" class="h-4 w-4 animate-spin text-emerald-600" />
                    {{ fmtFechaCorta(f.fecha_desde) }} — {{ fmtFechaCorta(f.fecha_hasta) }}
                    <button
                        type="button"
                        :disabled="exportando"
                        @click="exportar('excel')"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:opacity-50"
                    >
                        <Loader2 v-if="exportando === 'excel'" class="h-4 w-4 animate-spin" />
                        <FileSpreadsheet v-else class="h-4 w-4" />
                        Excel
                    </button>
                    <button
                        type="button"
                        :disabled="exportando"
                        @click="exportar('pdf')"
                        class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                    >
                        <Loader2 v-if="exportando === 'pdf'" class="h-4 w-4 animate-spin" />
                        <FileText v-else class="h-4 w-4" />
                        PDF
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] space-y-4 px-4 py-4 sm:px-6">
            <section class="border border-slate-200 bg-white">
                <div class="grid gap-3 p-3 md:grid-cols-3 xl:grid-cols-6">
                    <BaseInput v-model="f.fecha_desde" label="Desde" type="date" @change="buscar" />
                    <BaseInput v-model="f.fecha_hasta" label="Hasta" type="date" @change="buscar" />

                    <BaseSearchSelect
                        v-model="f.user_id"
                        label="Cajero"
                        placeholder="Todos"
                        :items="cajeros"
                        label-key="name"
                        value-key="id"
                        @change="buscar"
                    />

                    <BaseSearchSelect
                        v-model="f.forma_pago"
                        label="Forma de pago"
                        placeholder="Todas"
                        :items="formasPago"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />

                    <BaseSearchSelect
                        v-model="f.categoria_id"
                        label="Categoría"
                        placeholder="Todas"
                        :items="categorias"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />

                    <BaseInput v-model="f.producto" label="Producto" placeholder="Nombre o código" @input="debounce" />
                </div>
            </section>

            <section v-if="resumen" class="overflow-x-auto border border-slate-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-right font-medium">Ventas</th>
                            <th class="px-3 py-2 text-right font-medium">Unidades</th>
                            <th class="px-3 py-2 text-right font-medium">Ingreso neto</th>
                            <th class="px-3 py-2 text-right font-medium">Costo vendido</th>
                            <th class="px-3 py-2 text-right font-medium">Utilidad bruta</th>
                            <th class="px-3 py-2 text-right font-medium">Margen</th>
                            <th class="px-3 py-2 text-right font-medium">Venta promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="font-mono">
                            <td class="px-3 py-3 text-right">{{ resumen.ventas }}</td>
                            <td class="px-3 py-3 text-right">{{ num(resumen.unidades) }}</td>
                            <td class="px-3 py-3 text-right">{{ fmt(resumen.ingresos) }}</td>
                            <td class="px-3 py-3 text-right text-slate-600">{{ fmt(resumen.costo) }}</td>
                            <td class="px-3 py-3 text-right font-semibold" :class="colorUtilidad(resumen.utilidad)">
                                {{ fmt(resumen.utilidad) }}
                            </td>
                            <td class="px-3 py-3 text-right font-semibold" :class="colorMargen(resumen.margen)">
                                {{ num(resumen.margen) }}%
                            </td>
                            <td class="px-3 py-3 text-right">{{ fmt(resumen.venta_promedio) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div
                v-if="resumen?.partidas_sin_costo > 0"
                class="flex items-center gap-2 border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
            >
                <TriangleAlert class="h-4 w-4 shrink-0" />
                <span>
                    {{ resumen.partidas_sin_costo }} partidas sin costo histórico; la utilidad puede estar sobreestimada.
                </span>
            </div>

            <section class="overflow-hidden border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <div>
                        <h2 class="text-sm font-semibold">Utilidad por día</h2>
                        <p class="text-xs text-slate-500">Desglose del período seleccionado</p>
                    </div>
                    <span class="text-xs text-slate-400">{{ tendencia.length }} días</span>
                </div>
                <div class="max-h-72 overflow-auto">
                    <table class="min-w-full text-xs">
                        <thead class="sticky top-0 bg-white text-slate-500 shadow-sm">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Fecha</th>
                                <th class="px-3 py-2 text-right font-medium">Ventas</th>
                                <th class="px-3 py-2 text-right font-medium">Ingreso neto</th>
                                <th class="px-3 py-2 text-right font-medium">Costo</th>
                                <th class="px-3 py-2 text-right font-medium">Utilidad</th>
                                <th class="px-3 py-2 text-right font-medium">Margen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="dia in tendencia" :key="dia.fecha" class="font-mono hover:bg-slate-50">
                                <td class="px-3 py-2 text-left font-sans font-medium">{{ fmtFechaCorta(dia.fecha) }}</td>
                                <td class="px-3 py-2 text-right">{{ dia.ventas }}</td>
                                <td class="px-3 py-2 text-right">{{ fmt(dia.ingresos) }}</td>
                                <td class="px-3 py-2 text-right text-slate-500">{{ fmt(dia.costo) }}</td>
                                <td class="px-3 py-2 text-right font-semibold" :class="colorUtilidad(dia.utilidad)">{{ fmt(dia.utilidad) }}</td>
                                <td class="px-3 py-2 text-right font-semibold" :class="colorMargen(dia.margen)">{{ num(dia.margen) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!tendencia.length && !cargando" texto="No hay utilidad para los filtros seleccionados." />
            </section>

            <section class="overflow-hidden border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <div>
                        <h2 class="text-sm font-semibold">Utilidad por producto</h2>
                        <p class="text-xs text-slate-500">Ordenado de mayor a menor utilidad</p>
                    </div>
                    <span class="text-xs text-slate-400">Página {{ pag.current_page }} de {{ pag.last_page }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Código</th>
                                <th class="px-3 py-2 text-left font-medium">Producto</th>
                                <th class="px-3 py-2 text-left font-medium">Categoría</th>
                                <th class="px-3 py-2 text-right font-medium">Ventas</th>
                                <th class="px-3 py-2 text-right font-medium">Unidades</th>
                                <th class="px-3 py-2 text-right font-medium">Ingreso neto</th>
                                <th class="px-3 py-2 text-right font-medium">Costo</th>
                                <th class="px-3 py-2 text-right font-medium">Utilidad</th>
                                <th class="px-3 py-2 text-right font-medium">Margen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in productos" :key="item.producto_id" class="font-mono hover:bg-slate-50">
                                <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ item.codigo || "—" }}</td>
                                <td class="min-w-56 px-3 py-2 font-sans font-medium text-slate-900">{{ item.producto }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-sans text-slate-500">{{ item.categoria }}</td>
                                <td class="px-3 py-2 text-right">{{ item.ventas }}</td>
                                <td class="px-3 py-2 text-right">{{ num(item.unidades) }}</td>
                                <td class="px-3 py-2 text-right">{{ fmt(item.ingresos) }}</td>
                                <td class="px-3 py-2 text-right text-slate-500">{{ fmt(item.costo) }}</td>
                                <td class="px-3 py-2 text-right font-semibold" :class="colorUtilidad(item.utilidad)">{{ fmt(item.utilidad) }}</td>
                                <td class="px-3 py-2 text-right font-semibold" :class="colorMargen(item.margen)">{{ num(item.margen) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EstadoVacio v-if="!productos.length && !cargando" texto="No hay productos para los filtros seleccionados." />
                <div v-if="pag.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-3 py-2 text-xs">
                    <button class="border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40" :disabled="pag.current_page <= 1" @click="cambiarPagina(pag.current_page - 1)">Anterior</button>
                    <span class="text-slate-500">Página {{ pag.current_page }} de {{ pag.last_page }}</span>
                    <button class="border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40" :disabled="pag.current_page >= pag.last_page" @click="cambiarPagina(pag.current_page + 1)">Siguiente</button>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { defineComponent, h, onMounted, reactive, ref } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import { FileSpreadsheet, FileText, Loader2, TriangleAlert } from "lucide-vue-next";

const EstadoVacio = defineComponent({
    props: { texto: String },
    setup: (props) => () => h("div", { class: "border-t border-slate-100 px-4 py-8 text-center text-xs text-slate-400" }, props.texto),
});

const resumen = ref(null);
const tendencia = ref([]);
const productos = ref([]);
const cajeros = ref([]);
const categorias = ref([]);
const cargando = ref(false);
const exportando = ref(null);
const pag = ref({ current_page: 1, last_page: 1 });
let timer;

const formasPago = [
    { id: "efectivo", nombre: "Efectivo" },
    { id: "tarjeta", nombre: "Tarjeta" },
    { id: "transferencia", nombre: "Transferencia" },
    { id: "credito", nombre: "Crédito" },
];

const f = reactive({
    fecha_desde: inicioMes(),
    fecha_hasta: hoy(),
    user_id: "",
    forma_pago: "",
    categoria_id: "",
    producto: "",
    page: 1,
});

onMounted(cargar);

function buscar() {
    f.page = 1;
    cargar();
}

function debounce() {
    clearTimeout(timer);
    timer = setTimeout(buscar, 350);
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await axios.get("/api/reportes/utilidades", { params: { ...f, por_pagina: 30 } });
        resumen.value = data.resumen;
        tendencia.value = data.tendencia ?? [];
        productos.value = data.productos?.data ?? [];
        cajeros.value = data.cajeros ?? [];
        categorias.value = data.categorias ?? [];
        pag.value = {
            current_page: data.productos?.current_page ?? 1,
            last_page: data.productos?.last_page ?? 1,
        };
    } catch (error) {
        console.error("reporteUtilidades", error);
    } finally {
        cargando.value = false;
    }
}

function cambiarPagina(page) {
    f.page = page;
    cargar();
}

async function exportar(formato) {
    exportando.value = formato;
    try {
        const resp = await axios.get("/api/reportes/utilidades/exportar", {
            params: { ...f, formato },
            responseType: "blob",
        });
        const ext = formato === "pdf" ? "pdf" : "xlsx";
        const url = URL.createObjectURL(new Blob([resp.data]));
        const a = document.createElement("a");
        a.href = url;
        a.download = `utilidades_${f.fecha_desde}_${f.fecha_hasta}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error("exportarUtilidades", e);
    } finally {
        exportando.value = null;
    }
}

function colorUtilidad(valor) {
    return +valor < 0 ? "text-red-600" : "text-emerald-700";
}

function colorMargen(valor) {
    if (+valor < 0) return "text-red-600";
    if (+valor < 15) return "text-amber-600";
    return "text-emerald-700";
}

const moneda = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const numero = new Intl.NumberFormat("es-MX", { maximumFractionDigits: 2 });
function fmt(valor) { return moneda.format(+valor || 0); }
function num(valor) { return numero.format(+valor || 0); }
function fmtFechaCorta(fecha) {
    if (!fecha) return "";
    return new Date(`${String(fecha).slice(0, 10)}T12:00:00`).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
}
function hoy() {
    return fechaLocal(new Date());
}
function inicioMes() {
    const fecha = new Date();
    fecha.setDate(1);
    return fechaLocal(fecha);
}
function fechaLocal(fecha) {
    return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, "0")}-${String(fecha.getDate()).padStart(2, "0")}`;
}
</script>
