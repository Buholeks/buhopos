<template>
    <div ref="root" class="rounded-xl border border-slate-200 bg-white p-5">
        <label class="mb-2 block text-sm font-semibold text-slate-700">
            Buscar producto / variante
            <span class="ml-2 text-xs font-normal text-slate-400">
                Nombre, código, SKU o código de barras
            </span>
        </label>

        <div class="relative">
            <!-- Input -->
            <div
                class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
                :class="{
                    'border-emerald-500 ring-4 ring-emerald-100': dropdown,
                }"
            >
                <!-- Icono lupa (Lucide) -->
                <Search class="h-4 w-4 shrink-0 text-slate-400" />

                <input
                    ref="inputRef"
                    v-model="busqueda"
                    @input="onBusquedaInput"
                    @keydown.down.prevent="moverCursor(1)"
                    @keydown.up.prevent="moverCursor(-1)"
                    @keydown.enter.prevent="buscarOSeleccionar"
                    @keydown.escape.prevent="cerrarDropdown"
                    autocomplete="off"
                    placeholder="Escribe para buscar…"
                    class="flex-1 py-2.5 text-sm outline-none placeholder:text-slate-400"
                />

                <!-- Spinner (Lucide) -->
                <Loader2
                    v-if="buscando"
                    class="h-4 w-4 shrink-0 animate-spin text-emerald-500"
                />

                <!-- Limpiar (Lucide) -->
                <button
                    v-if="busqueda && !buscando"
                    type="button"
                    @click="limpiarBusqueda"
                    class="text-slate-400 hover:text-slate-700"
                    aria-label="Limpiar búsqueda"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Dropdown resultados -->
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 translate-y-1"
                leave-active-class="transition duration-75"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="dropdown && resultados.length > 0"
                    class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                >
                    <div class="max-h-72 overflow-y-auto">
                        <div
                            v-for="row in filasResultados"
                            :key="row.key"
                        >
                            <div
                                v-if="row.tipo === 'grupo'"
                                class="border-b border-slate-100 bg-slate-50 px-4 py-2"
                            >
                                <p class="truncate text-xs font-black uppercase tracking-wide text-slate-500">
                                    {{ row.nombre }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ row.total }} variante{{ row.total !== 1 ? "s" : "" }} encontrada{{ row.total !== 1 ? "s" : "" }}
                                </p>
                            </div>

                            <div
                                v-else
                                @click="seleccionarItem(row.item)"
                                @mouseenter="cursor = row.index"
                                class="flex cursor-pointer items-center gap-3 px-4 py-3 transition"
                                :class="
                                    cursor === row.index
                                        ? 'bg-emerald-50'
                                        : 'hover:bg-slate-50'
                                "
                            >
                            <!-- Imagen -->
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                            >
                                <img
                                    v-if="row.item.imagen_url"
                                    :src="row.item.imagen_url"
                                    class="h-full w-full object-contain"
                                    :alt="row.item.nombre"
                                />
                                <ImageOff
                                    v-else
                                    class="h-5 w-5 text-slate-300"
                                />
                            </div>

                            <!-- Texto -->
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-slate-900"
                                >
                                    {{ row.item.nombre }}
                                    <span
                                        v-if="row.item.nombre_variante"
                                        class="text-violet-600"
                                    >
                                        - {{ row.item.nombre_variante }}
                                    </span>
                                </p>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <span
                                        v-if="row.item.pedido_generico"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-700"
                                    >
                                        Pedido genérico
                                    </span>
                                    <span
                                        class="font-mono text-xs text-slate-400"
                                    >
                                        {{ row.item.codigo }}
                                    </span>
                                    <span
                                        v-if="row.item.sku"
                                        class="font-mono text-xs text-slate-400"
                                    >
                                        · {{ row.item.sku }}
                                    </span>
                                    <span
                                        v-if="row.item.codigo_barras"
                                        class="font-mono text-xs text-slate-400"
                                    >
                                        · {{ row.item.codigo_barras }}
                                    </span>
                                </div>
                            </div>

                            <!-- Precios -->
                            <div class="text-right">
                                <p class="font-mono text-xs text-slate-500">
                                    Costo: {{ formatPrecio(row.item.precio_compra) }}
                                </p>
                                <p class="font-mono text-xs text-emerald-600">
                                    Venta: {{ formatPrecio(row.item.precio_venta) }}
                                </p>
                            </div>

                            <!-- Enter hint -->
                            <div
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md"
                                :class="
                                    cursor === row.index
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-400'
                                "
                                title="Enter"
                            >
                                <CornerDownLeft class="h-4 w-4" />
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pie del dropdown -->
                    <div
                        class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-400"
                    >
                        <span class="mr-3">↑↓ Navegar</span>
                        <span class="mr-3">↵ Seleccionar</span>
                        <span>Esc Cerrar</span>
                    </div>
                </div>
            </Transition>

            <!-- Sin resultados -->
            <div
                v-if="
                    dropdown &&
                    resultados.length === 0 &&
                    busqueda.length > 1 &&
                    !buscando
                "
                class="absolute left-0 right-0 top-full z-50 mt-1 rounded-xl border border-slate-200 bg-white px-4 py-6 text-center shadow-xl"
            >
                <p class="text-sm text-slate-500">
                    Sin resultados para <strong>{{ busqueda }}</strong>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, nextTick } from "vue";
import http from "@/lib/http";
import Swal from "sweetalert2";
import { toastError } from "@/lib/alert";
// ✅ Lucide icons
import { Search, X, Loader2, ImageOff, CornerDownLeft } from "lucide-vue-next";

// const Toast = Swal.mixin({
//     toast: true,
//     position: "top-end",
//     showConfirmButton: false,
//     timer: 3000,
//     timerProgressBar: true,
// });

const props = defineProps({
    formatPrecio: { type: Function, required: true },
    escaneoRapido: { type: Boolean, default: true },
});

const emit = defineEmits(["seleccionar"]);

// ── Estado interno ──────────────────────────────────────────────────────────
const root = ref(null);
const inputRef = ref(null);
const busqueda = ref("");
const resultados = ref([]);
const buscando = ref(false);
const dropdown = ref(false);
const cursor = ref(0);
let buscarTimer = null;
let requestSeq = 0;

const filasResultados = computed(() => {
    const grupos = new Map();

    resultados.value.forEach((item) => {
        const key = item.grupo_producto_id ?? item.producto_id ?? item.nombre;
        if (!grupos.has(key)) {
            grupos.set(key, {
                nombre: item.grupo_producto || item.nombre,
                total: 0,
            });
        }
        grupos.get(key).total += item.tiene_variantes ? 1 : 0;
    });

    const filas = [];
    let grupoActual = null;

    resultados.value.forEach((item, index) => {
        const grupoKey = item.tiene_variantes
            ? (item.grupo_producto_id ?? item.producto_id ?? item.nombre)
            : null;
        const grupo = grupoKey ? grupos.get(grupoKey) : null;

        if (grupo && grupo.total > 1 && grupoActual !== grupoKey) {
            filas.push({
                tipo: "grupo",
                key: `g:${grupoKey}`,
                nombre: grupo.nombre,
                total: grupo.total,
            });
            grupoActual = grupoKey;
        }

        filas.push({
            tipo: "item",
            key: `i:${item.id ?? "p"}:${item.producto_id}:${index}`,
            item,
            index,
        });
    });

    return filas;
});

// ── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(() => {
    enfocarYSeleccionar();
    document.addEventListener("click", onDocClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", onDocClick);
});

function onDocClick(e) {
    if (!dropdown.value) return;
    if (!root.value) return;
    if (!root.value.contains(e.target)) cerrarDropdown();
}

// ── Búsqueda ────────────────────────────────────────────────────────────────
function onBusquedaInput() {
    clearTimeout(buscarTimer);
    cursor.value = 0;

    const q = busqueda.value.trim();
    if (q.length < 1) {
        resultados.value = [];
        dropdown.value = false;
        return;
    }

    buscando.value = true;
    buscarTimer = setTimeout(() => buscarProductos(q), 180);
}

async function buscarOSeleccionar() {
    if (dropdown.value && resultados.value.length > 0) {
        seleccionarCursor();
        return;
    }

    const q = busqueda.value.trim();
    if (q.length < 1) return;

    clearTimeout(buscarTimer);
    await buscarProductos(q, true);
}

async function buscarProductos(q, exacta = false) {
    const seq = ++requestSeq;
    buscando.value = true;

    try {
        const { data } = await http.get("/api/compras/buscar-variantes", {
            params: { q, exacta: exacta ? 1 : undefined },
        });

        if (seq !== requestSeq) return;

        resultados.value = Array.isArray(data) ? data : [];

        // Selección automática por código exacto
        if (resultados.value.length === 1) {
            const qq = q.toLowerCase();
            const r = resultados.value[0];
            const exacto =
                r.codigo_barras?.toLowerCase() === qq ||
                r.sku?.toLowerCase() === qq ||
                r.codigo?.toLowerCase() === qq;

            if (exacto) {
                seleccionarItem(r, exacto);
                return;
            }
        }

        dropdown.value = resultados.value.length > 0 || q.length > 1;
    } catch {
        toastError("Error en la búsqueda");
    } finally {
        if (seq === requestSeq) buscando.value = false;
    }
}

function moverCursor(dir) {
    if (!dropdown.value || resultados.value.length === 0) return;
    cursor.value = Math.max(
        0,
        Math.min(resultados.value.length - 1, cursor.value + dir),
    );
}

function seleccionarCursor() {
    if (!dropdown.value || resultados.value.length === 0) return;
    seleccionarItem(resultados.value[cursor.value], false);
}

function cerrarDropdown() {
    dropdown.value = false;
    resultados.value = [];
}

function limpiarBusqueda() {
    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;
    enfocarYSeleccionar();
}

function seleccionarItem(r, exacto = false) {
    emit("seleccionar", r, {
        escaneoRapido: props.escaneoRapido,
        exacto,
    });
    resultados.value = [];
    dropdown.value = false;
    cursor.value = 0;
    enfocarYSeleccionar();
}

function enfocarYSeleccionar() {
    nextTick(() => {
        inputRef.value?.focus();
        inputRef.value?.select?.();
    });
}

// Expuesto para que el padre pueda enfocar el input si necesita
defineExpose({ focus: enfocarYSeleccionar });
</script>
