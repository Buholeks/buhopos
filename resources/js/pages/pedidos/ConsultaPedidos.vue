<template>
    <main class="space-y-4">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-wide text-emerald-700">
                    Pedidos
                </p>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">
                    Consulta de pedidos
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Encargos y pedidos vinculados al catálogo.
                </p>
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-black text-slate-900">
                            Seguimiento
                        </h2>
                        <p class="text-sm text-slate-500">
                            Filtra por estado, fecha o búsqueda rápida.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-600 transition hover:bg-slate-50"
                        @click="limpiarFiltros"
                    >
                        Limpiar filtros
                    </button>
                </div>

                <div class="grid gap-2 md:grid-cols-[180px_150px_150px_minmax(0,1fr)]">
                    <select
                        v-model="filtroEstado"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        @change="cargarPedidos"
                    >
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_proceso">En proceso</option>
                        <option value="disponible">Disponible</option>
                        <option value="parcial">Parcial</option>
                        <option value="entregado">Entregado</option>
                        <option value="devuelto">Devuelto</option>
                        <option value="cancelado">Cancelado</option>
                        <option value="vencido">Vencido</option>
                    </select>

                    <input
                        v-model="filtroFechaDesde"
                        type="date"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        @change="cargarPedidos"
                    />

                    <input
                        v-model="filtroFechaHasta"
                        type="date"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        @change="cargarPedidos"
                    />

                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            v-model="buscar"
                            class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            placeholder="Folio, cliente o artículo"
                            @keyup.enter="cargarPedidos"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <button
                type="button"
                class="flex w-full flex-col gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-left transition hover:bg-slate-100 sm:flex-row sm:items-center sm:justify-between"
                @click="mostrarPendientesCompra = !mostrarPendientesCompra"
            >
                <div class="flex items-start gap-3">
                    <div class="rounded-2xl bg-amber-100 p-2 text-amber-700">
                        <ShoppingBag class="h-5 w-5" />
                    </div>

                    <div>
                        <h3 class="font-black text-slate-900">
                            Pendientes por comprar
                        </h3>
                        <p class="text-xs text-slate-500">
                            Artículos encargados que aún requieren compra.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">
                        {{ pendientesCompra.length }} pendientes
                    </span>

                    <span
                        v-if="seleccionPendientes.length"
                        class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700"
                    >
                        {{ seleccionPendientes.length }} seleccionados
                    </span>

                    <ChevronDown
                        class="h-5 w-5 text-slate-500 transition"
                        :class="mostrarPendientesCompra ? 'rotate-180' : ''"
                    />
                </div>
            </button>

            <div v-if="mostrarPendientesCompra">
                <div
                    v-if="cargandoPendientes"
                    class="p-6 text-center text-sm text-slate-500"
                >
                    Cargando pendientes...
                </div>

                <div
                    v-else-if="pendientesCompra.length === 0"
                    class="p-6 text-center text-sm text-slate-500"
                >
                    No hay encargos genéricos pendientes de compra.
                </div>

                <div v-else>
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm font-bold text-slate-700">
                            Selecciona los artículos para generar una compra.
                        </p>

                        <button
                            type="button"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            :disabled="seleccionPendientes.length === 0"
                            @click="crearCompraSeleccionados"
                        >
                            Crear compra con seleccionados
                        </button>
                    </div>

                    <div class="max-h-80 overflow-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="sticky top-0 z-10 bg-white text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-10 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            :checked="todosPendientesSeleccionados"
                                            @change="seleccionarTodosPendientes"
                                        />
                                    </th>
                                    <th class="px-3 py-3">Pedido / cliente</th>
                                    <th class="px-3 py-3">Artículo</th>
                                    <th class="px-3 py-3 text-right">Cant.</th>
                                    <th class="px-4 py-3 text-right">Venta acordada</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="detalle in pendientesCompra"
                                    :key="detalle.id"
                                    class="transition hover:bg-emerald-50/40"
                                >
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="seleccionPendientes"
                                            type="checkbox"
                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            :value="detalle.id"
                                        />
                                    </td>

                                    <td class="px-3 py-3">
                                        <p class="font-black text-slate-800">
                                            {{ detalle.folio }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ detalle.cliente }}
                                        </p>
                                    </td>

                                    <td class="px-3 py-3">
                                        <p class="font-bold text-slate-800">
                                            {{ detalle.descripcion }}
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            {{ detalle.producto }}
                                        </p>
                                    </td>

                                    <td class="px-3 py-3 text-right font-bold text-slate-700">
                                        {{ detalle.cantidad }}
                                    </td>

                                    <td class="px-4 py-3 text-right font-black text-slate-900">
                                        {{ money(detalle.precio_acordado) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <EncargosTable
            :pedidos="pedidos"
            :cargando="cargando"
            :abonos="abonos"
            @detalle="abrirDetalle"
            @cancelar="abrirCancelar"
            @abonar="registrarAbono"
        />

        <EncargoDetalleModal
            :visible="modalDetalle.visible"
            :cargando="modalDetalle.cargando"
            :pedido="modalDetalle.pedido"
            :data="modalDetalle.data"
            @close="cerrarDetalle"
        />

        <EncargoCancelarModal
            :visible="modalCancelar.visible"
            :procesando="modalCancelar.procesando"
            :pedido="modalCancelar.pedido"
            @close="cerrarCancelar"
            @confirm="ejecutarCancelacion"
        />
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { ChevronDown, Search, ShoppingBag } from "lucide-vue-next";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { guardarCompraDesdePedidos } from "@/helpers/compraDesdePedidos";
import EncargosTable from "@/components/encargos/EncargosTable.vue";
import EncargoDetalleModal from "@/components/encargos/EncargoDetalleModal.vue";
import EncargoCancelarModal from "@/components/encargos/EncargoCancelarModal.vue";
import { useEncargos } from "@/stores/useEncargos";

const router = useRouter();

const pendientesCompra = ref([]);
const seleccionPendientes = ref([]);
const cargandoPendientes = ref(false);
const mostrarPendientesCompra = ref(false);

let timer = null;

const modalDetalle = reactive({
    visible: false,
    cargando: false,
    pedido: null,
    data: null,
});

const modalCancelar = reactive({
    visible: false,
    procesando: false,
    pedido: null,
});

const {
    pedidos,
    cargando,
    buscar,
    filtroEstado,
    filtroFechaDesde,
    filtroFechaHasta,
    abonos,
    money,
    cargarPedidos,
    registrarAbono,
    cancelarPedido,
} = useEncargos({ tipo: "pedido" });

const todosPendientesSeleccionados = computed(() => {
    return (
        pendientesCompra.value.length > 0 &&
        pendientesCompra.value.every((detalle) =>
            seleccionPendientes.value.includes(detalle.id),
        )
    );
});

watch(buscar, () => {
    window.clearTimeout(timer);

    timer = window.setTimeout(() => {
        cargarPedidos();
        cargarPendientesCompra();
    }, 350);
});

onMounted(() => {
    cargarPedidos();
    cargarPendientesCompra();
});

async function cargarPendientesCompra() {
    cargandoPendientes.value = true;

    try {
        const { data } = await http.get("/api/pedidos/pendientes-compra", {
            params: {
                buscar: buscar.value || undefined,
            },
        });

        pendientesCompra.value = Array.isArray(data) ? data : [];

        seleccionPendientes.value = seleccionPendientes.value.filter((id) =>
            pendientesCompra.value.some((detalle) => detalle.id === id),
        );
    } catch {
        pendientesCompra.value = [];
        toastError("No se pudieron cargar los pendientes por comprar");
    } finally {
        cargandoPendientes.value = false;
    }
}

function seleccionarTodosPendientes(event) {
    seleccionPendientes.value = event.target.checked
        ? pendientesCompra.value.map((detalle) => detalle.id)
        : [];
}

function limpiarFiltros() {
    filtroEstado.value = "";
    filtroFechaDesde.value = "";
    filtroFechaHasta.value = "";
    buscar.value = "";

    cargarPedidos();
    cargarPendientesCompra();
}

function crearCompraSeleccionados() {
    const seleccionados = pendientesCompra.value.filter((detalle) =>
        seleccionPendientes.value.includes(detalle.id),
    );

    if (!seleccionados.length) return;

    guardarCompraDesdePedidos(seleccionados);
    seleccionPendientes.value = [];

    router.push({ name: "compras" });
}

async function abrirDetalle(pedido) {
    modalDetalle.visible = true;
    modalDetalle.cargando = true;
    modalDetalle.pedido = pedido;
    modalDetalle.data = null;

    try {
        const { data } = await http.get(`/api/pedidos/${pedido.id}`);
        modalDetalle.data = data;
    } catch {
        toastError("No se pudo cargar el detalle");
        cerrarDetalle();
    } finally {
        modalDetalle.cargando = false;
    }
}

function cerrarDetalle() {
    modalDetalle.visible = false;
    modalDetalle.pedido = null;
    modalDetalle.data = null;
}

function abrirCancelar(pedido) {
    modalCancelar.visible = true;
    modalCancelar.pedido = pedido;
}

function cerrarCancelar() {
    modalCancelar.visible = false;
    modalCancelar.pedido = null;
    modalCancelar.procesando = false;
}

async function ejecutarCancelacion() {
    if (!modalCancelar.pedido) return;

    modalCancelar.procesando = true;

    try {
        await cancelarPedido(modalCancelar.pedido);
        cerrarCancelar();
    } finally {
        modalCancelar.procesando = false;
    }
}
</script>
