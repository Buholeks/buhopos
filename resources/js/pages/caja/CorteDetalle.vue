<template>
    <div class="min-h-screen bg-slate-50">
        <!-- TOPBAR -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-3 sm:px-6 py-3 sm:py-4">
                <button
                    type="button"
                    @click="goBack"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                </button>

                <div>
                    <h1 class="text-lg font-semibold text-slate-900">
                        Detalle del corte
                    </h1>
                    <p class="text-xs text-slate-500">
                        {{ corte ? formatFecha(corte.fecha_apertura) : "…" }}
                        {{ corte?.user?.name ? `· ${corte.user.name}` : "" }}
                    </p>
                </div>

                <span
                    v-if="corte"
                    class="ml-auto inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1"
                    :class="
                        corte.estado === 'abierto'
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : 'bg-slate-100 text-slate-600 ring-slate-200'
                    "
                >
                    {{ corte.estado === "abierto" ? "Abierto" : "Cerrado" }}
                </span>
            </div>
        </div>

        <div
            v-if="cargando"
            class="flex items-center justify-center py-32 text-slate-400"
        >
            <Loader2 class="h-7 w-7 animate-spin" />
        </div>

        <div v-else-if="corte" class="mx-auto max-w-7xl space-y-6 px-3 sm:px-6 py-4 sm:py-6">
            <!-- RESUMEN TOTALES -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <TarjetaResumen
                    label="Efectivo"
                    :valor="corte.ventas_efectivo"
                    color="emerald"
                />
                <TarjetaResumen
                    label="Tarjeta"
                    :valor="corte.ventas_tarjeta"
                    color="blue"
                />
                <TarjetaResumen
                    label="Transferencia"
                    :valor="corte.ventas_transferencia"
                    color="violet"
                />
                <TarjetaResumen
                    label="Crédito"
                    :valor="corte.ventas_credito"
                    color="orange"
                />
            </div>

            <!-- DIFERENCIAS (solo si cerrado) -->
            <div
                v-if="corte.estado === 'cerrado'"
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >
                <h2 class="mb-4 text-sm font-semibold text-slate-900">
                    Arqueo final
                </h2>

                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div
                        v-for="item in arqueoDatos"
                        :key="item.label"
                        class="rounded-xl border border-slate-100 p-4"
                    >
                        <p class="text-xs text-slate-500">{{ item.label }}</p>
                        <p class="mt-1 font-semibold text-slate-900">
                            {{ fmt(item.esperado) }}
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Contado:
                            <span class="font-medium text-slate-700">{{
                                fmt(item.contado)
                            }}</span>
                        </p>

                        <p
                            class="mt-1 text-xs font-semibold"
                            :class="
                                item.dif >= 0
                                    ? 'text-emerald-600'
                                    : 'text-rose-600'
                            "
                        >
                            Diferencia: {{ fmt(item.dif) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- MOVIMIENTOS -->
            <div
                v-if="corte.movimientos?.length"
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >
                <h2 class="mb-4 text-sm font-semibold text-slate-900">
                    Movimientos de caja
                    <span class="ml-2 text-xs font-normal text-slate-400"
                        >({{ corte.movimientos.length }})</span
                    >
                </h2>

                <table class="w-full text-sm">
                    <thead
                        class="border-b border-slate-100 text-xs font-medium uppercase text-slate-500"
                    >
                        <tr>
                            <th class="pb-2 text-left">Tipo</th>
                            <th class="pb-2 text-left">Concepto</th>
                            <th class="pb-2 text-left">Forma pago</th>
                            <th class="pb-2 text-right">Monto</th>
                            <th class="pb-2 text-left">Usuario</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in corte.movimientos" :key="m.id">
                            <td class="py-2">
                                <span
                                    class="font-medium capitalize"
                                    :class="
                                        m.tipo === 'ingreso'
                                            ? 'text-emerald-600'
                                            : 'text-rose-600'
                                    "
                                >
                                    {{ m.tipo }}
                                </span>
                            </td>
                            <td class="py-2 text-slate-600">
                                {{ m.concepto }}
                            </td>
                            <td class="py-2 capitalize text-slate-500">
                                {{ m.forma_pago }}
                            </td>
                            <td
                                class="py-2 text-right font-medium"
                                :class="
                                    m.tipo === 'ingreso'
                                        ? 'text-emerald-700'
                                        : 'text-rose-700'
                                "
                            >
                                {{ m.tipo === "egreso" ? "-" : ""
                                }}{{ fmt(m.monto) }}
                            </td>
                            <td class="py-2 text-slate-500">
                                {{ m.user?.name ?? "—" }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- VENTAS -->
            <div class="rounded-2xl border border-slate-200 bg-white">
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                >
                    <h2 class="text-sm font-semibold text-slate-900">
                        Ventas del turno
                        <span class="ml-2 text-xs font-normal text-slate-400"
                            >({{ totalVentas }})</span
                        >
                    </h2>
                </div>

                <div
                    v-if="cargandoVentas"
                    class="flex justify-center py-10 text-slate-400"
                >
                    <Loader2 class="h-5 w-5 animate-spin" />
                </div>

                <div
                    v-else-if="ventas.length === 0"
                    class="py-10 text-center text-sm text-slate-400"
                >
                    Sin ventas en este turno
                </div>

                <!-- ACORDEÓN DE VENTAS -->
                <div v-else class="divide-y divide-slate-100">
                    <div v-for="v in ventas" :key="v.id">
                        <!-- CABECERA VENTA -->
                        <button
                            type="button"
                            @click="toggleVenta(v.id)"
                            class="flex w-full items-center justify-between px-5 py-3 text-left transition-colors hover:bg-slate-50"
                        >
                            <div class="flex items-center gap-4">
                                <span
                                    class="rounded bg-cyan-50 px-2 py-0.5 text-xs font-mono font-semibold text-cyan-700"
                                >
                                    {{ v.folio }}
                                </span>
                                <span class="text-sm text-slate-700">{{
                                    formatFecha(v.fecha)
                                }}</span>
                                <span
                                    class="rounded bg-slate-100 px-2 py-0.5 text-xs capitalize text-slate-500"
                                >
                                    {{ v.forma_pago }}
                                </span>
                                <span class="text-xs text-slate-400">{{
                                    v.user?.name
                                }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <span
                                    class="text-sm font-semibold text-slate-900"
                                    >{{ fmt(v.total) }}</span
                                >
                                <ChevronDown
                                    class="h-4 w-4 text-slate-400 transition-transform"
                                    :class="
                                        abiertos.has(v.id) ? 'rotate-180' : ''
                                    "
                                />
                            </div>
                        </button>

                        <!-- DETALLES VENTA -->
                        <div
                            v-if="abiertos.has(v.id)"
                            class="border-t border-slate-100 bg-slate-50 px-5 py-3"
                        >
                            <table class="w-full text-sm">
                                <thead
                                    class="text-xs font-medium text-slate-500"
                                >
                                    <tr>
                                        <th class="pb-2 text-left">Producto</th>
                                        <th class="pb-2 text-left">Variante</th>
                                        <th class="pb-2 text-right">
                                            Cantidad
                                        </th>
                                        <th class="pb-2 text-right">Precio</th>
                                        <th class="pb-2 text-right">
                                            Subtotal
                                        </th>
                                        <th class="pb-2 text-left">
                                            Motivo precio
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="d in v.detalles" :key="d.id">
                                        <td
                                            class="py-1.5 font-medium text-slate-800"
                                        >
                                            {{ d.producto?.nombre ?? "—" }}
                                        </td>
                                        <td class="py-1.5 text-slate-500">
                                            {{ d.variante?.sku ?? "—" }}
                                        </td>
                                        <td
                                            class="py-1.5 text-right text-slate-700"
                                        >
                                            {{ d.cantidad }}
                                        </td>
                                        <td
                                            class="py-1.5 text-right text-slate-700"
                                        >
                                            {{ fmt(d.precio_venta) }}
                                        </td>
                                        <td
                                            class="py-1.5 text-right font-medium text-slate-900"
                                        >
                                            {{ fmt(d.subtotal) }}
                                        </td>
                                        <td
                                            class="py-1.5 text-xs italic text-slate-400"
                                        >
                                            {{ d.motivo_precio ?? "—" }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="mt-3 flex justify-end gap-6 text-sm">
                                <span
                                    v-if="v.descuento > 0"
                                    class="text-slate-500"
                                >
                                    Descuento:
                                    <span class="font-medium text-rose-600"
                                        >-{{ fmt(v.descuento) }}</span
                                    >
                                </span>
                                <span class="font-semibold text-slate-900"
                                    >Total: {{ fmt(v.total) }}</span
                                >
                            </div>

                            <p
                                v-if="v.notas"
                                class="mt-2 text-xs italic text-slate-400"
                            >
                                Nota: {{ v.notas }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- PAGINACIÓN VENTAS -->
                <div
                    v-if="metaVentas.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-600"
                >
                    <span
                        >Página {{ metaVentas.current_page }} de
                        {{ metaVentas.last_page }}</span
                    >

                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="metaVentas.current_page === 1"
                            @click="prevVentas"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Anterior
                        </button>

                        <button
                            type="button"
                            :disabled="
                                metaVentas.current_page === metaVentas.last_page
                            "
                            @click="nextVentas"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40"
                        >
                            Siguiente
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="mx-auto max-w-7xl px-3 sm:px-6 py-10 text-center text-sm text-slate-500"
        >
            No se encontró el corte.
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import TarjetaResumen from "@/components/caja/TarjetaResumen.vue";
import {
    ArrowLeft,
    Loader2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
} from "lucide-vue-next";

// ✅ router para back (en <script setup> NO existe $router)
const router = useRouter();
const goBack = () => router.back();


const route = useRoute();
const corteId = Number(route.params.id);

const cargando = ref(true);
const cargandoVentas = ref(true);
const corte = ref(null);

const ventas = ref([]);
const paginaVentas = ref(1);
const metaVentas = ref({ current_page: 1, last_page: 1, total: 0 });

const abiertos = ref(new Set());

const totalVentas = computed(
    () => metaVentas.value.total ?? ventas.value.length,
);

const arqueoDatos = computed(() => {
    if (!corte.value) return [];
    return [
        {
            label: "Efectivo",
            esperado: corte.value.esperado_efectivo,
            contado: corte.value.contado_efectivo,
            dif: corte.value.dif_efectivo,
        },
        {
            label: "Tarjeta",
            esperado: corte.value.esperado_tarjeta,
            contado: corte.value.contado_tarjeta,
            dif: corte.value.dif_tarjeta,
        },
        {
            label: "Transferencia",
            esperado: corte.value.esperado_transferencia,
            contado: corte.value.contado_transferencia,
            dif: corte.value.dif_transferencia,
        },
    ];
});

onMounted(async () => {
    await Promise.all([cargarCorte(), cargarVentas()]);
});

async function cargarCorte() {
    try {
        const { data } = await axios.get(`/api/cortes-caja/${corteId}`);
        corte.value = data;
    } finally {
        cargando.value = false;
    }
}

async function cargarVentas() {
    cargandoVentas.value = true;
    try {
        const { data } = await axios.get(`/api/cortes-caja/${corteId}/ventas`, {
            params: { page: paginaVentas.value, por_pagina: 30 },
        });
        ventas.value = data.data ?? [];
        metaVentas.value = {
            current_page: data.current_page ?? 1,
            last_page: data.last_page ?? 1,
            total: data.total ?? data.data?.length ?? 0,
        };
    } finally {
        cargandoVentas.value = false;
    }
}

function prevVentas() {
    if (metaVentas.value.current_page === 1) return;
    paginaVentas.value--;
    cargarVentas();
}

function nextVentas() {
    if (metaVentas.value.current_page === metaVentas.value.last_page) return;
    paginaVentas.value++;
    cargarVentas();
}

function toggleVenta(id) {
    if (abiertos.value.has(id)) abiertos.value.delete(id);
    else abiertos.value.add(id);
    abiertos.value = new Set(abiertos.value); // forzar reactividad
}

const fmt = (v) =>
    new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(v ?? 0);

const formatFecha = (f) =>
    f
        ? new Date(f).toLocaleString("es-MX", {
              dateStyle: "short",
              timeStyle: "short",
          })
        : "—";
</script>
