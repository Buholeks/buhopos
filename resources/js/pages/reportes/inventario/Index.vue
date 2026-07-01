<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Inversion en mercancia</h1>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Valuacion del inventario de la sucursal activa con costo actual.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <Loader2 v-if="cargando" class="h-4 w-4 animate-spin text-emerald-600" />
                    {{ agrupacionActual }}
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
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <ResumenDato titulo="Invertido" :valor="fmt(resumen.invertido)" destacado />
                <ResumenDato titulo="Existencias" :valor="num(resumen.unidades)" />
                <ResumenDato titulo="Valor venta" :valor="fmt(resumen.valor_venta)" />
                <ResumenDato titulo="Margen potencial" :valor="`${num(resumen.margen_potencial)}%`" />
                <ResumenDato titulo="Sin costo" :valor="num(resumen.sin_costo)" alerta />
            </section>

            <section class="border border-slate-200 bg-white">
                <div class="grid gap-3 p-3 lg:grid-cols-[minmax(220px,1fr)_auto_auto]">
                    <BaseInput v-model="f.q" label="Buscar" placeholder="Producto, codigo, SKU, categoria o proveedor" @input="debounce" />

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Agrupar</label>
                        <div class="flex rounded border border-slate-200 bg-slate-50 p-0.5">
                            <button
                                v-for="opcion in agrupaciones"
                                :key="opcion.id"
                                type="button"
                                class="px-3 py-1.5 text-xs font-medium transition"
                                :class="f.agrupar === opcion.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                @click="cambiarAgrupacion(opcion.id)"
                            >
                                {{ opcion.nombre }}
                            </button>
                        </div>
                    </div>

                    <BaseSearchSelect
                        v-model="f.filtro"
                        label="Filtro"
                        :items="filtros"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />
                </div>
            </section>

            <div
                v-if="resumen.sin_costo > 0"
                class="flex items-center gap-2 border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
            >
                <TriangleAlert class="h-4 w-4 shrink-0" />
                <span>{{ resumen.sin_costo }} articulos no tienen costo capturado; el total invertido puede estar incompleto.</span>
            </div>

            <section class="overflow-hidden border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <div>
                        <h2 class="text-sm font-semibold">{{ tituloTabla }}</h2>
                        <p class="text-xs text-slate-500">Sucursal activa, ordenado por mayor inversion</p>
                    </div>
                    <span class="text-xs text-slate-400">Pagina {{ pag.current_page }} de {{ pag.last_page }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white text-slate-500">
                            <tr v-if="f.agrupar === 'producto'">
                                <th class="px-3 py-2 text-left font-medium">Clave</th>
                                <th class="px-3 py-2 text-left font-medium">Producto</th>
                                <th class="px-3 py-2 text-left font-medium">Categoria</th>
                                <th class="px-3 py-2 text-left font-medium">Proveedor</th>
                                <th class="px-3 py-2 text-right font-medium">Existencia</th>
                                <th class="px-3 py-2 text-right font-medium">Variantes</th>
                                <th class="px-3 py-2 text-right font-medium">Costo prom.</th>
                                <th class="px-3 py-2 text-right font-medium">Invertido</th>
                                <th class="px-3 py-2 text-right font-medium">Valor venta</th>
                            </tr>
                            <tr v-else>
                                <th class="px-3 py-2 text-left font-medium">{{ f.agrupar === "categoria" ? "Categoria" : "Proveedor" }}</th>
                                <th class="px-3 py-2 text-right font-medium">Articulos</th>
                                <th class="px-3 py-2 text-right font-medium">Existencia</th>
                                <th class="px-3 py-2 text-right font-medium">Invertido</th>
                                <th class="px-3 py-2 text-right font-medium">Valor venta</th>
                                <th class="px-3 py-2 text-right font-medium">Margen</th>
                                <th class="px-3 py-2 text-right font-medium">Alertas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in items" :key="itemKey(item)" class="font-mono hover:bg-slate-50">
                                <template v-if="f.agrupar === 'producto'">
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ item.clave || "-" }}</td>
                                    <td class="min-w-64 px-3 py-2 font-sans">
                                        <div class="font-medium text-slate-900">{{ item.producto }}</div>
                                        <div v-if="item.sin_costo || item.bajo_minimo" class="mt-1 flex flex-wrap gap-1">
                                            <span v-if="item.sin_costo" class="border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">Sin costo</span>
                                            <span v-if="item.bajo_minimo" class="border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700">Bajo minimo</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 font-sans text-slate-500">{{ item.categoria }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 font-sans text-slate-500">{{ item.proveedor }}</td>
                                    <td class="px-3 py-2 text-right">{{ num(item.stock) }}</td>
                                    <td class="px-3 py-2 text-right">{{ num(item.variantes) }}</td>
                                    <td class="px-3 py-2 text-right">{{ fmt(item.costo) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ fmt(item.invertido) }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ fmt(item.valor_venta) }}</td>
                                </template>

                                <template v-else>
                                    <td class="min-w-64 px-3 py-2 font-sans font-medium text-slate-900">{{ item.nombre }}</td>
                                    <td class="px-3 py-2 text-right">{{ num(item.articulos) }}</td>
                                    <td class="px-3 py-2 text-right">{{ num(item.unidades) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ fmt(item.invertido) }}</td>
                                    <td class="px-3 py-2 text-right text-slate-600">{{ fmt(item.valor_venta) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold" :class="colorMargen(item.margen)">{{ num(item.margen) }}%</td>
                                    <td class="px-3 py-2 text-right text-slate-500">
                                        {{ item.sin_costo }} sin costo / {{ item.bajo_minimo }} bajo minimo
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EstadoVacio v-if="!items.length && !cargando" texto="No hay inventario para los filtros seleccionados." />

                <div v-if="pag.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-3 py-2 text-xs">
                    <button class="border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40" :disabled="pag.current_page <= 1" @click="cambiarPagina(pag.current_page - 1)">Anterior</button>
                    <span class="text-slate-500">Pagina {{ pag.current_page }} de {{ pag.last_page }}</span>
                    <button class="border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40" :disabled="pag.current_page >= pag.last_page" @click="cambiarPagina(pag.current_page + 1)">Siguiente</button>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import { FileSpreadsheet, FileText, Loader2, TriangleAlert } from "lucide-vue-next";

const EstadoVacio = defineComponent({
    props: { texto: String },
    setup: (props) => () => h("div", { class: "border-t border-slate-100 px-4 py-8 text-center text-xs text-slate-400" }, props.texto),
});

const ResumenDato = defineComponent({
    props: {
        titulo: String,
        valor: String,
        destacado: Boolean,
        alerta: Boolean,
    },
    setup: (props) => () => h("div", {
        class: [
            "border bg-white px-3 py-3",
            props.destacado ? "border-emerald-200" : props.alerta ? "border-amber-200" : "border-slate-200",
        ],
    }, [
        h("div", { class: "text-xs font-medium text-slate-500" }, props.titulo),
        h("div", {
            class: [
                "mt-1 truncate font-mono text-xl font-semibold",
                props.destacado ? "text-emerald-700" : props.alerta ? "text-amber-700" : "text-slate-900",
            ],
            title: props.valor,
        }, props.valor),
    ]),
});

const resumen = ref({
    articulos: 0,
    unidades: 0,
    invertido: 0,
    valor_venta: 0,
    margen_potencial: 0,
    sin_costo: 0,
    bajo_minimo: 0,
});
const items = ref([]);
const cargando = ref(false);
const exportando = ref(null);
const pag = ref({ current_page: 1, last_page: 1 });
let timer;

const agrupaciones = [
    { id: "producto", nombre: "Producto" },
    { id: "categoria", nombre: "Categoria" },
    { id: "proveedor", nombre: "Proveedor" },
];

const filtros = [
    { id: "todos", nombre: "Todos" },
    { id: "con_existencia", nombre: "Con existencia" },
    { id: "sin_costo", nombre: "Sin costo" },
    { id: "bajo_minimo", nombre: "Bajo minimo" },
];

const f = reactive({
    q: "",
    agrupar: "producto",
    filtro: "con_existencia",
    page: 1,
});

const agrupacionActual = computed(() => agrupaciones.find((a) => a.id === f.agrupar)?.nombre ?? "Producto");
const tituloTabla = computed(() => f.agrupar === "producto" ? "Inventario por producto" : `Inventario por ${agrupacionActual.value.toLowerCase()}`);

onMounted(cargar);

function buscar() {
    f.page = 1;
    cargar();
}

function debounce() {
    clearTimeout(timer);
    timer = setTimeout(buscar, 350);
}

function cambiarAgrupacion(valor) {
    f.agrupar = valor;
    buscar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await axios.get("/api/reportes/inventario", { params: { ...f, por_pagina: 30 } });
        resumen.value = data.resumen ?? resumen.value;
        items.value = data.items?.data ?? [];
        pag.value = {
            current_page: data.items?.current_page ?? 1,
            last_page: data.items?.last_page ?? 1,
        };
    } catch (error) {
        console.error("reporteInventario", error);
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
        const resp = await axios.get("/api/reportes/inventario/exportar", {
            params: { ...f, formato },
            responseType: "blob",
        });
        const ext = formato === "pdf" ? "pdf" : "xlsx";
        const url = URL.createObjectURL(new Blob([resp.data]));
        const a = document.createElement("a");
        a.href = url;
        a.download = `inventario_${f.agrupar}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error("exportarInventario", e);
    } finally {
        exportando.value = null;
    }
}

function itemKey(item) {
    return `${f.agrupar}-${item.id ?? item.nombre}`;
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
</script>
