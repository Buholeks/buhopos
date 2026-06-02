<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <SearchCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold tracking-tight sm:text-xl">
                            Lista de precios
                        </h1>
                        <p class="text-sm text-slate-500">
                            Consulta rápida por marca, modelo, talla, color y existencia.
                        </p>
                    </div>
                </div>

                <div class="hidden rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 sm:block">
                    {{ pag.total }} resultados
                </div>
            </div>
        </div>

        <main class="mx-auto max-w-7xl space-y-5 px-4 py-5 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <label class="block xl:col-span-2">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Buscar</span>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="f.buscar"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                placeholder="Producto, SKU o código..."
                                @input="debounceBuscar"
                            />
                        </div>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Marca</span>
                        <select v-model="f.marca_id" class="control" @change="cambiarMarca">
                            <option value="">Todas</option>
                            <option v-for="m in filtros.marcas" :key="m.id" :value="m.id">
                                {{ m.nombre }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Modelo</span>
                        <select v-model="f.modelo_id" class="control" @change="buscar">
                            <option value="">Todos</option>
                            <option v-for="m in modelosFiltrados" :key="m.id" :value="m.id">
                                {{ m.nombre }}
                            </option>
                        </select>
                    </label>

                    <label class="flex items-end">
                        <span class="flex h-10 w-full items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700">
                            <input
                                v-model="f.con_stock"
                                type="checkbox"
                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                @change="buscar"
                            />
                            Sólo con existencia
                        </span>
                    </label>
                </div>

                <div v-if="filtros.atributos.length" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    <label v-for="tipo in filtros.atributos" :key="tipo.id" class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">{{ tipo.nombre }}</span>
                        <select
                            :value="atributoSeleccionado(tipo.id)"
                            class="control"
                            @change="seleccionarAtributo(tipo.id, $event.target.value)"
                        >
                            <option value="">Todos</option>
                            <option v-for="a in tipo.atributos" :key="a.id" :value="a.id">
                                {{ a.valor }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <div class="flex flex-wrap gap-2">
                        <span v-if="hayFiltros" class="text-xs font-medium text-slate-500">
                            Filtros activos
                        </span>
                        <span
                            v-for="chip in chips"
                            :key="chip.key"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                        >
                            {{ chip.label }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                        @click="limpiar"
                    >
                        <RotateCcw class="h-4 w-4" />
                        Limpiar
                    </button>
                </div>
            </section>

            <div v-if="cargando" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="n in 8" :key="n" class="h-64 animate-pulse rounded-xl bg-slate-100" />
            </div>

            <section v-else-if="items.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <button
                    v-for="item in items"
                    :key="`${item.producto_id}-${item.variante_id ?? 'base'}`"
                    type="button"
                    class="group overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md"
                    @click="seleccionado = item"
                >
                    <div class="aspect-[4/3] bg-slate-100">
                        <img
                            v-if="item.imagen_url"
                            :src="item.imagen_url"
                            :alt="item.nombre"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full items-center justify-center text-slate-300">
                            <PackageSearch class="h-12 w-12" />
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="line-clamp-2 text-sm font-semibold text-slate-900">
                                    {{ item.nombre }}
                                </h2>
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="Number(item.stock) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ fmtStock(item.stock) }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ [item.marca, item.modelo].filter(Boolean).join(" · ") || "Sin marca/modelo" }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="a in item.atributos"
                                :key="`${a.tipo}-${a.valor}`"
                                class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600"
                            >
                                {{ a.valor }}
                            </span>
                        </div>

                        <div class="flex items-end justify-between gap-3 border-t border-slate-100 pt-3">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                                    Mínimo
                                </p>
                                <p class="text-lg font-semibold text-emerald-700">
                                    {{ fmt(item.precio_minimo) }}
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-slate-400 group-hover:text-emerald-600">
                                Ver precios
                            </span>
                        </div>
                    </div>
                </button>
            </section>

            <section v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <PackageSearch class="mx-auto h-10 w-10 text-slate-300" />
                <h2 class="mt-3 text-base font-semibold text-slate-900">
                    Sin productos para estos filtros
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Ajusta talla, color, marca o búsqueda para ampliar resultados.
                </p>
            </section>

            <div v-if="pag.last_page > 1" class="flex items-center justify-center gap-3">
                <button class="pager" :disabled="pag.current_page <= 1" @click="cambiarPagina(pag.current_page - 1)">
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <span class="text-sm text-slate-500">
                    Página {{ pag.current_page }} de {{ pag.last_page }}
                </span>
                <button class="pager" :disabled="pag.current_page >= pag.last_page" @click="cambiarPagina(pag.current_page + 1)">
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </main>

        <div
            v-if="seleccionado"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
            @click.self="seleccionado = null"
        >
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            {{ seleccionado.nombre }}
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ seleccionado.variante || "Producto base" }}
                        </p>
                    </div>
                    <button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="seleccionado = null">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-5">
                    <div class="grid grid-cols-2 gap-3">
                        <Info label="Marca" :value="seleccionado.marca || '-'" />
                        <Info label="Modelo" :value="seleccionado.modelo || '-'" />
                        <Info label="SKU" :value="seleccionado.sku || seleccionado.codigo || '-'" />
                        <Info label="Existencia" :value="fmtStock(seleccionado.stock)" />
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            Precio mínimo registrado
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-800">
                            {{ fmt(seleccionado.precio_minimo) }}
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Precio</th>
                                    <th class="px-4 py-3 text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="p in seleccionado.precios" :key="p.key">
                                    <td class="px-4 py-3 font-medium text-slate-700">
                                        {{ p.label }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                        {{ fmt(p.valor) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from "vue";
import axios from "axios";
import {
    ChevronLeft,
    ChevronRight,
    PackageSearch,
    RotateCcw,
    Search,
    SearchCheck,
    X,
} from "lucide-vue-next";

const Info = defineComponent({
    props: {
        label: { type: String, required: true },
        value: { type: [String, Number], default: "-" },
    },
    setup(props) {
        return () =>
            h("div", { class: "rounded-xl bg-slate-50 px-4 py-3" }, [
                h("p", { class: "text-xs font-medium text-slate-500" }, props.label),
                h("p", { class: "mt-1 truncate text-sm font-semibold text-slate-800" }, props.value),
            ]);
    },
});

const cargando = ref(false);
const items = ref([]);
const seleccionado = ref(null);
const timer = ref(null);
const pag = ref({ current_page: 1, last_page: 1, total: 0 });

const filtros = reactive({
    marcas: [],
    modelos: [],
    atributos: [],
});

const f = reactive({
    buscar: "",
    marca_id: "",
    modelo_id: "",
    atributo_ids: {},
    con_stock: true,
    page: 1,
});

const modelosFiltrados = computed(() => {
    if (!f.marca_id) return filtros.modelos;
    return filtros.modelos.filter((m) => Number(m.marca_id) === Number(f.marca_id));
});

const chips = computed(() => {
    const out = [];
    const marca = filtros.marcas.find((m) => Number(m.id) === Number(f.marca_id));
    const modelo = filtros.modelos.find((m) => Number(m.id) === Number(f.modelo_id));

    if (f.buscar) out.push({ key: "buscar", label: f.buscar });
    if (marca) out.push({ key: "marca", label: marca.nombre });
    if (modelo) out.push({ key: "modelo", label: modelo.nombre });

    Object.values(f.atributo_ids)
        .filter(Boolean)
        .forEach((id) => {
            for (const tipo of filtros.atributos) {
                const attr = tipo.atributos?.find((a) => Number(a.id) === Number(id));
                if (attr) out.push({ key: `attr-${id}`, label: `${tipo.nombre}: ${attr.valor}` });
            }
        });

    return out;
});

const hayFiltros = computed(() => chips.value.length > 0);

function atributoSeleccionado(tipoId) {
    return f.atributo_ids[tipoId] ?? "";
}

function seleccionarAtributo(tipoId, atributoId) {
    if (atributoId) {
        f.atributo_ids[tipoId] = atributoId;
    } else {
        delete f.atributo_ids[tipoId];
    }

    buscar();
}

function cambiarMarca() {
    f.modelo_id = "";
    buscar();
}

function params() {
    return {
        buscar: f.buscar || undefined,
        marca_id: f.marca_id || undefined,
        modelo_id: f.modelo_id || undefined,
        atributo_ids: Object.values(f.atributo_ids).filter(Boolean),
        con_stock: f.con_stock ? 1 : 0,
        page: f.page,
        per_page: 24,
    };
}

async function cargar() {
    cargando.value = true;

    try {
        const { data } = await axios.get("/api/productos/catalogo-precios", {
            params: params(),
        });

        items.value = data.catalogo?.data ?? [];
        pag.value = {
            current_page: data.catalogo?.current_page ?? 1,
            last_page: data.catalogo?.last_page ?? 1,
            total: data.catalogo?.total ?? 0,
        };
        filtros.marcas = data.filtros?.marcas ?? [];
        filtros.modelos = data.filtros?.modelos ?? [];
        filtros.atributos = data.filtros?.atributos ?? [];
    } finally {
        cargando.value = false;
    }
}

function buscar() {
    f.page = 1;
    cargar();
}

function debounceBuscar() {
    clearTimeout(timer.value);
    timer.value = setTimeout(buscar, 350);
}

function cambiarPagina(page) {
    if (page < 1 || page > pag.value.last_page) return;
    f.page = page;
    cargar();
}

function limpiar() {
    f.buscar = "";
    f.marca_id = "";
    f.modelo_id = "";
    f.atributo_ids = {};
    f.con_stock = true;
    buscar();
}

function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}

function fmtStock(v) {
    const n = Number(v ?? 0);
    return `${Number.isInteger(n) ? n : n.toFixed(3)} disp.`;
}

onMounted(cargar);
</script>

<style scoped>
.control {
    height: 2.5rem;
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    outline: none;
    transition:
        border-color 150ms ease,
        box-shadow 150ms ease;
}

.control:focus {
    border-color: rgb(16 185 129);
    box-shadow: 0 0 0 4px rgb(209 250 229);
}

.pager {
    display: inline-flex;
    height: 2.25rem;
    width: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    color: rgb(71 85 105);
    transition: background-color 150ms ease;
}

.pager:hover:not(:disabled) {
    background: rgb(248 250 252);
}

.pager:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}
</style>
