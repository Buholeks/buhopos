<template>
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white px-4 py-4">
            <div class="flex w-full flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black text-slate-950">Etiquetas de precio</h1>
                    <p class="text-sm text-slate-500">Selecciona artículos, cantidades y precio antes de imprimir.</p>
                </div>
                <div class="flex gap-2">
                    <RouterLink to="/etiquetas/plantillas" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700">Diseñar plantillas</RouterLink>
                    <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="imprimiendo || totalEtiquetas === 0" :title="totalEtiquetas === 0 ? 'Selecciona al menos un artículo con etiquetas' : ''" @click="imprimir">{{ imprimiendo ? "Imprimiendo..." : `Imprimir ${totalEtiquetas} etiqueta${totalEtiquetas !== 1 ? "s" : ""}` }}</button>
                </div>
            </div>
        </header>

        <main class="grid w-full gap-5 p-4 lg:grid-cols-[1fr_360px]">
            <section class="space-y-4">
                <div ref="buscadorRoot" class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="text-xs font-bold uppercase text-slate-500">Agregar productos o variantes</label>
                        <div class="flex items-center gap-1">
                            <button
                                v-for="opt in filtroOpciones"
                                :key="opt.value"
                                type="button"
                                class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition"
                                :class="filtroStock === opt.value ? opt.activeClass : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300 hover:text-slate-600'"
                                @click="cambiarFiltroStock(opt.value)"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <div class="relative mt-2">
                        <div
                            class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
                            :class="{ 'border-emerald-500 ring-4 ring-emerald-100': dropdown }"
                        >
                            <Search class="h-4 w-4 shrink-0 text-slate-400" />
                            <input
                                ref="buscarInput"
                                v-model="buscar"
                                class="min-w-0 flex-1 py-2.5 text-sm outline-none placeholder:text-slate-400"
                                placeholder="Nombre, código, SKU o barras"
                                autocomplete="off"
                                @input="onBuscarInput"
                                @keydown.down.prevent="moverCursor(1)"
                                @keydown.up.prevent="moverCursor(-1)"
                                @keydown.enter.prevent="buscarOSeleccionar"
                                @keydown.escape.prevent="cerrarDropdown"
                            >
                            <Loader2 v-if="buscando" class="h-4 w-4 shrink-0 animate-spin text-emerald-500" />
                            <button
                                v-if="buscar && !buscando"
                                type="button"
                                class="text-slate-400 hover:text-slate-700"
                                aria-label="Limpiar búsqueda"
                                @click="limpiarBusqueda"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div
                            v-if="dropdown && resultados.length"
                            class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                        >
                            <div class="max-h-80 overflow-y-auto">
                                <template v-for="row in filasResultados" :key="row.key">
                                    <div v-if="row.tipo === 'grupo'" class="border-b border-slate-100 bg-slate-50 px-4 py-2">
                                        <p class="truncate text-xs font-black uppercase tracking-wide text-slate-500">{{ row.nombre }}</p>
                                        <p class="text-[11px] text-slate-400">{{ row.total }} variante{{ row.total !== 1 ? "s" : "" }} encontrada{{ row.total !== 1 ? "s" : "" }}</p>
                                    </div>
                                    <button
                                        v-else
                                        type="button"
                                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition"
                                        :class="cursor === row.index ? 'bg-emerald-50' : 'hover:bg-slate-50'"
                                        @mouseenter="cursor = row.index"
                                        @click="agregar(row.item)"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ row.item.datos.producto.nombre }}
                                                <span v-if="row.item.datos.variante.nombre" class="text-emerald-600">- {{ row.item.datos.variante.nombre }}</span>
                                            </p>
                                            <p class="mt-0.5 truncate font-mono text-xs text-slate-400">
                                                {{ row.item.datos.producto.codigo }}
                                                <span v-if="row.item.datos.variante.sku"> · {{ row.item.datos.variante.sku }}</span>
                                                <span v-if="row.item.datos.variante.codigo_barras"> · {{ row.item.datos.variante.codigo_barras }}</span>
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="font-mono text-xs text-emerald-600">{{ fmt(row.item.precio_impresion) }}</p>
                                            <p class="text-[11px]" :class="Number(row.item.existencia) > 0 ? 'text-slate-500' : 'text-red-500'">Exist. {{ fmtCantidad(row.item.existencia) }}</p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <div class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-400">
                                <span class="mr-3">↑↓ Navegar</span>
                                <span class="mr-3">↵ Seleccionar</span>
                                <span>Esc Cerrar</span>
                            </div>
                        </div>

                        <div
                            v-if="dropdown && !resultados.length && buscar.trim().length > 1 && !buscando"
                            class="absolute left-0 right-0 top-full z-50 mt-1 rounded-xl border border-slate-200 bg-white px-4 py-6 text-center shadow-xl"
                        >
                            <p class="text-sm text-slate-500">Sin resultados para <strong>{{ buscar }}</strong></p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                        <div class="flex gap-2">
                            <button class="text-sm font-bold text-emerald-700" @click="seleccionarTodo(true)">Seleccionar todo</button>
                            <button class="text-sm font-bold text-slate-500" @click="seleccionarTodo(false)">Deseleccionar</button>
                        </div>
                        <span class="text-sm text-slate-500">{{ items.length }} artículos · {{ totalEtiquetas }} etiquetas</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">Imprimir</th><th class="p-3">Producto</th><th class="p-3">Código</th><th class="p-3">Etiquetas</th><th class="p-3">Precio impreso</th><th class="p-3"></th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in items" :key="item.id" class="transition-colors duration-700" :class="{ 'bg-emerald-50': resaltadoId === item.id }">
                                    <td class="p-3"><input v-model="item.seleccionado" type="checkbox" class="h-5 w-5 accent-emerald-600"></td>
                                    <td class="p-3"><strong>{{ item.datos.producto.nombre }}</strong><div class="text-xs text-slate-500">{{ item.datos.variante.nombre }}</div></td>
                                    <td class="p-3 font-mono text-xs">{{ item.datos.calculados.codigo_preferido }}</td>
                                    <td class="p-3">
                                        <input v-model.number="item.cantidad" type="number" min="0" step="1" class="w-24 rounded-lg border px-2 py-1" :class="item.seleccionado && !cantidadValida(item) ? 'border-red-400 bg-red-50 text-red-700' : 'border-slate-300'">
                                        <p v-if="item.seleccionado && !cantidadValida(item)" class="mt-0.5 text-[11px] text-red-600">Entero de 1 o más</p>
                                    </td>
                                    <td class="p-3">
                                        <input v-model.number="item.precio_impresion" type="number" min="0" step=".01" class="w-28 rounded-lg border px-2 py-1" :class="item.seleccionado && !precioValido(item) ? 'border-red-400 bg-red-50 text-red-700' : 'border-slate-300'">
                                        <p v-if="item.seleccionado && !precioValido(item)" class="mt-0.5 text-[11px] text-red-600">Precio inválido</p>
                                    </td>
                                    <td class="p-3"><button class="text-red-600" @click="quitar(item)">Quitar</button></td>
                                </tr>
                                <tr v-if="!items.length"><td colspan="6" class="p-10 text-center text-slate-400">No hay artículos preparados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <label class="text-xs font-bold uppercase text-slate-500">Plantilla</label>
                    <select v-model="plantillaId" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2"><option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }} · {{ p.ancho_mm }}×{{ p.alto_mm }} mm</option></select>
                    <label class="mt-3 block text-xs font-bold uppercase text-slate-500">Perfil de impresión</label>
                    <select v-model="perfilId" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2"><option v-for="p in perfiles" :key="p.id" :value="p.id">{{ p.nombre }} · {{ p.material }}</option></select>

                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h2 class="mb-3 text-sm font-black text-slate-900">Vista previa</h2>
                    <div class="overflow-auto rounded-xl bg-slate-100 p-3">
                        <EtiquetaVista v-if="plantillaActual && itemPreview" :plantilla="plantillaActual" :datos="itemPreview.datos" :precio-impresion="itemPreview.precio_impresion" :escala="4" :rotacion="perfilActual?.rotacion || 0" />
                    </div>
                </div>
                <QzImpresoraSelector :perfil-id="perfilId" @cambiar="impresoraQz = $event" />
            </aside>
        </main>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRaw } from "vue";
import { swal as Swal } from "@/lib/alert";
import http from "@/lib/http";
import EtiquetaVista from "@/components/etiquetas/EtiquetaVista.vue";
import QzImpresoraSelector from "@/components/etiquetas/QzImpresoraSelector.vue";
import { imprimirEtiquetas } from "@/helpers/etiquetas";
import { Loader2, Search, X } from "lucide-vue-next";

const items = ref([]);
const plantillas = ref([]);
const perfiles = ref([]);
const plantillaId = ref(null);
const perfilId = ref(null);
const buscar = ref("");
const resultados = ref([]);
const buscando = ref(false);
const dropdown = ref(false);
const cursor = ref(0);
const buscadorRoot = ref(null);
const buscarInput = ref(null);
const impresoraQz = ref(null);
const imprimiendo = ref(false);
const filtroStock = ref("con_existencia");
const filtroOpciones = [
    { value: "todos", label: "Todos", activeClass: "border-slate-400 bg-slate-100 text-slate-700" },
    { value: "con_existencia", label: "Con existencia", activeClass: "border-emerald-400 bg-emerald-50 text-emerald-700" },
    { value: "sin_existencia", label: "Sin existencia", activeClass: "border-red-300 bg-red-50 text-red-600" },
];
const resaltadoId = ref(null);
let buscarTimer = null;
let resaltadoTimer = null;
let requestSeq = 0;
const LIMITE_CONFIRMAR_ETIQUETAS = 100;

const plantillaActual = computed(() => plantillas.value.find((p) => p.id === Number(plantillaId.value)));
const perfilActual = computed(() => perfiles.value.find((p) => p.id === Number(perfilId.value)));
const itemPreview = computed(() => items.value.find((i) => i.seleccionado) || items.value[0]);
const totalEtiquetas = computed(() => items.value.filter((i) => i.seleccionado).reduce((t, i) => t + Math.max(0, Number(i.cantidad) || 0), 0));
const filasResultados = computed(() => {
    const grupos = new Map();

    resultados.value.forEach((item) => {
        const key = item.producto_id ?? item.datos.producto.nombre;
        if (!grupos.has(key)) grupos.set(key, { nombre: item.datos.producto.nombre, total: 0 });
        if (item.variante_id) grupos.get(key).total += 1;
    });

    const filas = [];
    let grupoActual = null;

    resultados.value.forEach((item, index) => {
        const grupoKey = item.variante_id ? item.producto_id : null;
        const grupo = grupoKey ? grupos.get(grupoKey) : null;

        if (grupo && grupo.total > 1 && grupoActual !== grupoKey) {
            filas.push({ tipo: "grupo", key: `g:${grupoKey}`, nombre: grupo.nombre, total: grupo.total });
            grupoActual = grupoKey;
        }

        filas.push({ tipo: "item", key: `i:${item.id}:${index}`, item, index });
    });

    return filas;
});

onMounted(async () => {
    try {
        const { data } = await http.get("/api/etiquetas/configuracion");
        const plantillasP = data.plantillas.filter((p) => p.tipo === "precio");
        plantillas.value = plantillasP;
        perfiles.value = data.perfiles;
        plantillaId.value = (plantillasP.find((p) => p.predeterminada) || plantillasP[0])?.id;
        perfilId.value = (data.perfiles.find((p) => p.predeterminado) || data.perfiles[0])?.id;
    } catch (e) {
        Swal.fire("Error al cargar", e.response?.data?.message || e.message, "error");
    }

    enfocarBusqueda();
    document.addEventListener("click", onDocClick);
});

onBeforeUnmount(() => {
    clearTimeout(buscarTimer);
    clearTimeout(resaltadoTimer);
    document.removeEventListener("click", onDocClick);
});

function onDocClick(e) {
    if (!dropdown.value || !buscadorRoot.value) return;
    if (!buscadorRoot.value.contains(e.target)) cerrarDropdown();
}

function onBuscarInput() {
    clearTimeout(buscarTimer);
    cursor.value = 0;

    const q = buscar.value.trim();
    if (q.length < 1) {
        resultados.value = [];
        dropdown.value = false;
        return;
    }

    buscando.value = true;
    buscarTimer = setTimeout(() => buscarProductos(q), 180);
}

async function buscarOSeleccionar() {
    if (dropdown.value && resultados.value.length) {
        seleccionarCursor();
        return;
    }

    const q = buscar.value.trim();
    if (!q) return;

    clearTimeout(buscarTimer);
    await buscarProductos(q, true);
}

async function buscarProductos(q, exacta = false) {
    const seq = ++requestSeq;
    buscando.value = true;

    try {
        const { data } = await http.get("/api/etiquetas/catalogo", {
            params: { q, exacta: exacta ? 1 : undefined, filtro_stock: filtroStock.value },
        });

        if (seq !== requestSeq) return;

        resultados.value = Array.isArray(data) ? data : [];

        if (resultados.value.length === 1 && esCoincidenciaExacta(resultados.value[0], q)) {
            agregar(resultados.value[0]);
            return;
        }

        dropdown.value = resultados.value.length > 0 || q.length > 1;
    } catch (e) {
        Swal.fire("Error en la búsqueda", e.response?.data?.message || e.message, "error");
    } finally {
        if (seq === requestSeq) buscando.value = false;
    }
}

function cambiarFiltroStock(valor) {
    if (filtroStock.value === valor) return;
    filtroStock.value = valor;

    const q = buscar.value.trim();
    if (q) {
        clearTimeout(buscarTimer);
        cursor.value = 0;
        buscarProductos(q);
    }
    enfocarBusqueda();
}

function esCoincidenciaExacta(item, q) {
    const texto = q.toLowerCase();
    return [
        item.datos?.producto?.codigo,
        item.datos?.variante?.sku,
        item.datos?.variante?.codigo_barras,
    ].some((valor) => String(valor || "").toLowerCase() === texto);
}

function moverCursor(dir) {
    if (!dropdown.value || !resultados.value.length) return;
    cursor.value = Math.max(0, Math.min(resultados.value.length - 1, cursor.value + dir));
}

function seleccionarCursor() {
    if (!dropdown.value || !resultados.value.length) return;
    agregar(resultados.value[cursor.value]);
}

function cerrarDropdown() {
    dropdown.value = false;
    resultados.value = [];
}

function limpiarBusqueda() {
    buscar.value = "";
    resultados.value = [];
    dropdown.value = false;
    enfocarBusqueda();
}

function enfocarBusqueda() {
    nextTick(() => {
        buscarInput.value?.focus();
        buscarInput.value?.select?.();
    });
}
function agregar(item) {
    // Escanear o elegir de nuevo un artículo ya preparado suma una etiqueta más.
    const existente = items.value.find((i) => i.id === item.id);
    if (existente) {
        existente.cantidad = Math.max(0, Math.floor(Number(existente.cantidad) || 0)) + 1;
        existente.seleccionado = true;
    } else {
        items.value.push(structuredClone(toRaw(item)));
    }
    resaltar(item.id);
    resultados.value = [];
    dropdown.value = false;
    cursor.value = 0;
    enfocarBusqueda();
}
function resaltar(id) {
    clearTimeout(resaltadoTimer);
    resaltadoId.value = id;
    resaltadoTimer = setTimeout(() => { resaltadoId.value = null; }, 900);
}
function quitar(item) { items.value = items.value.filter((i) => i !== item); }
function cantidadValida(item) {
    const n = Number(item.cantidad);
    return item.cantidad !== "" && Number.isInteger(n) && n >= 1;
}
function precioValido(item) {
    const n = Number(item.precio_impresion);
    return item.precio_impresion !== "" && item.precio_impresion !== null && Number.isFinite(n) && n >= 0;
}
function nombreItem(item) {
    return [item.datos.producto.nombre, item.datos.variante.nombre].filter(Boolean).join(" - ");
}

async function validarAntesDeImprimir() {
    if (!plantillaActual.value) {
        Swal.fire("Falta plantilla", "Selecciona una plantilla de precio antes de imprimir.", "warning");
        return false;
    }
    if (!perfilActual.value) {
        Swal.fire("Falta perfil", "Selecciona un perfil de impresión antes de imprimir.", "warning");
        return false;
    }

    const seleccionados = items.value.filter((i) => i.seleccionado);
    if (!seleccionados.length) {
        Swal.fire("Sin artículos", "Selecciona al menos un artículo para imprimir.", "warning");
        return false;
    }

    const invalidos = seleccionados.filter((i) => !cantidadValida(i) || !precioValido(i));
    if (invalidos.length) {
        const lista = invalidos.slice(0, 8).map((i) => `<li>${escaparHtml(nombreItem(i))}</li>`).join("");
        const resto = invalidos.length > 8 ? `<li>y ${invalidos.length - 8} más…</li>` : "";
        Swal.fire({
            icon: "warning",
            title: "Revisa cantidades y precios",
            html: `<p class="mb-2">Estos artículos seleccionados tienen una cantidad o un precio inválido (marcados en rojo):</p><ul class="text-left list-disc pl-6">${lista}${resto}</ul>`,
        });
        return false;
    }

    if (totalEtiquetas.value > LIMITE_CONFIRMAR_ETIQUETAS) {
        const res = await Swal.fire({
            icon: "question",
            title: `¿Imprimir ${totalEtiquetas.value} etiquetas?`,
            text: "Es una cantidad grande. Confirma que las cantidades son correctas.",
            showCancelButton: true,
            confirmButtonText: "Imprimir",
            cancelButtonText: "Revisar",
            reverseButtons: true,
        });
        if (!res.isConfirmed) return false;
    }

    return true;
}

function escaparHtml(texto) {
    return String(texto ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}
function seleccionarTodo(valor) { items.value.forEach((i) => { i.seleccionado = valor; }); }

function fmt(v) {
    return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(v ?? 0));
}

function fmtCantidad(v) {
    return new Intl.NumberFormat("es-MX", { maximumFractionDigits: 3 }).format(Number(v ?? 0));
}

async function imprimir() {
    if (imprimiendo.value) return;
    if (!(await validarAntesDeImprimir())) return;
    imprimiendo.value = true;
    try {
        await imprimirEtiquetas({ plantilla: plantillaActual.value, perfil: perfilActual.value, items: items.value, impresoraQz: impresoraQz.value });
        await preguntarLimpiarEtiquetas();
    } catch (e) {
        Swal.fire("No se pudo imprimir", e.response?.data?.message || e.message, "error");
    } finally {
        imprimiendo.value = false;
    }
}

async function preguntarLimpiarEtiquetas() {
    const res = await Swal.fire({
        title: "Limpiar etiqueta",
        text: "La impresión fue enviada. ¿Quieres limpiar los artículos preparados?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Limpiar",
        cancelButtonText: "Conservar",
        reverseButtons: true,
    });

    if (res.isConfirmed) {
        items.value = [];
        limpiarBusqueda();
        return;
    }

    enfocarBusqueda();
}
</script>
