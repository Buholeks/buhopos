<template>
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white px-4 py-4">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black text-slate-950">Etiquetas de precio</h1>
                    <p class="text-sm text-slate-500">Selecciona artículos, cantidades y precio antes de imprimir.</p>
                </div>
                <div class="flex gap-2">
                    <RouterLink to="/etiquetas/plantillas" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700">Diseñar plantillas</RouterLink>
                    <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="imprimiendo" @click="imprimir">{{ imprimiendo ? "Imprimiendo..." : `Imprimir ${totalEtiquetas} etiquetas` }}</button>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-5 p-4 lg:grid-cols-[1fr_360px]">
            <section class="space-y-4">
                <div ref="buscadorRoot" class="rounded-2xl border border-slate-200 bg-white p-4">
                    <label class="text-xs font-bold uppercase text-slate-500">Agregar productos o variantes</label>
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
                                                <span v-if="row.item.datos.variante.nombre" class="text-violet-600">- {{ row.item.datos.variante.nombre }}</span>
                                            </p>
                                            <p class="mt-0.5 truncate font-mono text-xs text-slate-400">
                                                {{ row.item.datos.producto.codigo }}
                                                <span v-if="row.item.datos.variante.sku"> · {{ row.item.datos.variante.sku }}</span>
                                                <span v-if="row.item.datos.variante.codigo_barras"> · {{ row.item.datos.variante.codigo_barras }}</span>
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="font-mono text-xs text-emerald-600">{{ fmt(row.item.precio_impresion) }}</p>
                                            <p class="text-[11px] text-slate-400">Enter</p>
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
                                <tr v-for="item in items" :key="item.id">
                                    <td class="p-3"><input v-model="item.seleccionado" type="checkbox" class="h-5 w-5 accent-emerald-600"></td>
                                    <td class="p-3"><strong>{{ item.datos.producto.nombre }}</strong><div class="text-xs text-slate-500">{{ item.datos.variante.nombre }}</div></td>
                                    <td class="p-3 font-mono text-xs">{{ item.datos.calculados.codigo_preferido }}</td>
                                    <td class="p-3"><input v-model.number="item.cantidad" type="number" min="0" step="1" class="w-24 rounded-lg border border-slate-300 px-2 py-1"></td>
                                    <td class="p-3"><input v-model.number="item.precio_impresion" type="number" min="0" step=".01" class="w-28 rounded-lg border border-slate-300 px-2 py-1"></td>
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
import Swal from "sweetalert2";
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
let buscarTimer = null;
let requestSeq = 0;

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
            params: { q, exacta: exacta ? 1 : undefined },
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
    if (!items.value.some((i) => i.id === item.id)) items.value.push(structuredClone(toRaw(item)));
    resultados.value = [];
    dropdown.value = false;
    cursor.value = 0;
    enfocarBusqueda();
}
function quitar(item) { items.value = items.value.filter((i) => i !== item); }
function seleccionarTodo(valor) { items.value.forEach((i) => { i.seleccionado = valor; }); }

function fmt(v) {
    return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(v ?? 0));
}

async function imprimir() {
    if (imprimiendo.value) return;
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
