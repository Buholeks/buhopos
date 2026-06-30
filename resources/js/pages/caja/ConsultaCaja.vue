<template>
    <div class="min-h-screen bg-slate-50">
        <!-- TOPBAR -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-3 sm:px-6 py-3 sm:py-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700"
                    >
                        <ClipboardList class="h-5 w-5" />
                    </div>
                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900"
                        >
                            Consulta de caja
                        </h1>
                        <p class="text-xs text-slate-500">
                            Movimientos y ventas registrados por fecha
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 py-4 sm:py-6 space-y-4">
            <!-- FILTROS -->
            <div
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7"
                >
                    <BaseInput
                        v-model="filtros.desde"
                        type="date"
                        label="Desde"
                    />

                    <BaseInput
                        v-model="filtros.hasta"
                        type="date"
                        label="Hasta"
                    />

                    <BaseSearchSelect
                        v-model="filtros.origen"
                        label="Origen"
                        :items="opcionesOrigen"
                        label-key="nombre"
                        value-key="id"
                        placeholder="Todos"
                        @change="(v) => (filtros.origen = v ?? '')"
                    />

                    <BaseSearchSelect
                        v-model="filtros.tipo"
                        label="Tipo"
                        :items="opcionesTipo"
                        label-key="nombre"
                        value-key="id"
                        placeholder="Todos"
                        @change="(v) => (filtros.tipo = v ?? '')"
                    />

                    <BaseSearchSelect
                        v-model="filtros.forma_pago"
                        label="Forma de pago"
                        :items="opcionesFormaPago"
                        label-key="nombre"
                        value-key="id"
                        placeholder="Todas"
                        @change="(v) => (filtros.forma_pago = v ?? '')"
                    />

                    <BaseInput
                        v-model="filtros.concepto"
                        label="Concepto / Folio"
                        placeholder="Buscar…"
                        @keydown.enter="buscar()"
                    />

                    <BaseSearchSelect
                        v-model="filtros.user_id"
                        label="Usuario"
                        :items="opcionesUsuario"
                        label-key="nombre"
                        value-key="id"
                        placeholder="Todos"
                        @change="(v) => (filtros.user_id = v ?? '')"
                    />
                </div>

                <!-- Botones -->
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="buscar()"
                        :disabled="cargando"
                        class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-100 disabled:opacity-50"
                    >
                        <Loader2 v-if="cargando" class="h-4 w-4 animate-spin" />
                        <Search v-else class="h-4 w-4" />
                        Buscar
                    </button>
                    <button
                        type="button"
                        @click="limpiar"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                    >
                        <X class="h-4 w-4" />
                        Limpiar
                    </button>
                </div>
            </div>

            <!-- RESUMEN -->
            <div
                v-if="resumen && !cargando"
                class="grid grid-cols-2 gap-3 sm:grid-cols-4"
            >
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-medium text-slate-500">
                        Total registros
                    </p>
                    <p class="mt-1 text-xl font-bold text-slate-900">
                        {{ paginacion.total ?? 0 }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm"
                >
                    <p class="text-xs font-medium text-emerald-600">
                        Ingresos
                    </p>
                    <p class="mt-1 text-xl font-bold text-emerald-700">
                        {{ fmt(resumen.ingresos) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-rose-100 bg-rose-50 p-4 shadow-sm"
                >
                    <p class="text-xs font-medium text-rose-600">
                        Egresos
                    </p>
                    <p class="mt-1 text-xl font-bold text-rose-700">
                        {{ fmt(resumen.egresos) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-medium text-slate-500">
                        Saldo neto
                    </p>
                    <p
                        class="mt-1 text-xl font-bold"
                        :class="
                            resumen.neto >= 0
                                ? 'text-emerald-700'
                                : 'text-rose-700'
                        "
                    >
                        {{ fmt(resumen.neto) }}
                    </p>
                </div>
            </div>

            <!-- TABLA -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    v-if="cargando"
                    class="flex items-center justify-center py-20 text-slate-400"
                >
                    <Loader2 class="h-6 w-6 animate-spin" />
                </div>

                <div
                    v-else-if="movimientos.length === 0"
                    class="flex flex-col items-center justify-center py-20 text-slate-400"
                >
                    <ClipboardList class="h-10 w-10 mb-3 opacity-40" />
                    <p class="text-sm font-medium">
                        Sin registros para los filtros seleccionados
                    </p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    Fecha / Hora
                                </th>
                                <th class="px-4 py-3 text-left">Terminal</th>
                                <th class="px-4 py-3 text-left">Usuario</th>
                                <th class="px-4 py-3 text-left">Origen</th>
                                <th class="px-4 py-3 text-left">Tipo</th>
                                <th class="px-4 py-3 text-left">
                                    Forma de pago
                                </th>
                                <th class="px-4 py-3 text-left">
                                    Concepto / Folio
                                </th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="mov in movimientos"
                                :key="`${mov.origen}-${mov.id}`"
                                class="transition hover:bg-slate-50"
                                :class="
                                    mov.tipo === 'ingreso'
                                        ? 'bg-emerald-50/20'
                                        : 'bg-rose-50/20'
                                "
                            >
                                <td
                                    class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600"
                                >
                                    {{ fmtFecha(mov.fecha_hora) }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-slate-700"
                                >
                                    {{ mov.terminal ?? "—" }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-slate-700"
                                >
                                    {{ mov.usuario ?? "—" }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            mov.origen === 'venta'
                                                ? 'bg-cyan-100 text-cyan-700'
                                                : 'bg-slate-100 text-slate-600'
                                        "
                                    >
                                        <ShoppingCart
                                            v-if="mov.origen === 'venta'"
                                            class="h-3 w-3"
                                        />
                                        <SlidersHorizontal
                                            v-else
                                            class="h-3 w-3"
                                        />
                                        {{
                                            mov.origen === "venta"
                                                ? "Venta"
                                                : "Manual"
                                        }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            mov.tipo === 'ingreso'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-rose-100 text-rose-700'
                                        "
                                    >
                                        <TrendingUp
                                            v-if="mov.tipo === 'ingreso'"
                                            class="h-3 w-3"
                                        />
                                        <TrendingDown v-else class="h-3 w-3" />
                                        {{
                                            mov.tipo === "ingreso"
                                                ? "Ingreso"
                                                : "Egreso"
                                        }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-slate-100 text-slate-700':
                                                mov.forma_pago === 'efectivo',
                                            'bg-blue-50 text-blue-700':
                                                mov.forma_pago === 'tarjeta',
                                            'bg-violet-50 text-violet-700':
                                                mov.forma_pago ===
                                                'transferencia',
                                            'bg-amber-50 text-amber-700':
                                                mov.forma_pago === 'credito',
                                        }"
                                    >
                                        <Banknote
                                            v-if="mov.forma_pago === 'efectivo'"
                                            class="h-3 w-3"
                                        />
                                        <CreditCard
                                            v-else-if="
                                                mov.forma_pago === 'tarjeta'
                                            "
                                            class="h-3 w-3"
                                        />
                                        <ArrowLeftRight
                                            v-else-if="
                                                mov.forma_pago ===
                                                'transferencia'
                                            "
                                            class="h-3 w-3"
                                        />
                                        <Wallet v-else class="h-3 w-3" />
                                        {{ etiquetaFormaPago(mov.forma_pago) }}
                                    </span>
                                </td>
                                <td
                                    class="max-w-xs truncate px-4 py-3 text-slate-700"
                                >
                                    {{ mov.concepto }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold"
                                    :class="
                                        mov.tipo === 'ingreso'
                                            ? 'text-emerald-700'
                                            : 'text-rose-700'
                                    "
                                >
                                    {{ mov.tipo === "ingreso" ? "+" : "-"
                                    }}{{ fmt(mov.monto) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACIÓN -->
                <div
                    v-if="!cargando && paginacion.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3"
                >
                    <p class="text-xs text-slate-500">
                        Mostrando {{ paginacion.from }}–{{ paginacion.to }} de
                        {{ paginacion.total }} registros
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            :disabled="paginacion.current_page === 1"
                            @click="irPagina(paginacion.current_page - 1)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <template v-for="p in paginasVisibles" :key="p">
                            <span
                                v-if="p === '…'"
                                class="px-2 text-xs text-slate-400"
                                >…</span
                            >
                            <button
                                v-else
                                type="button"
                                @click="irPagina(p)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-semibold transition"
                                :class="
                                    p === paginacion.current_page
                                        ? 'border-cyan-500 bg-cyan-600 text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                "
                            >
                                {{ p }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :disabled="
                                paginacion.current_page === paginacion.last_page
                            "
                            @click="irPagina(paginacion.current_page + 1)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import http from "@/lib/http";
import Swal from "sweetalert2";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import {
    ClipboardList,
    Search,
    X,
    Loader2,
    TrendingUp,
    TrendingDown,
    Banknote,
    CreditCard,
    ArrowLeftRight,
    Wallet,
    ChevronLeft,
    ChevronRight,
    ShoppingCart,
    SlidersHorizontal,
} from "lucide-vue-next";

const hoy = fechaLocal();

const opcionesOrigen = [
    { id: "", nombre: "Todos" },
    { id: "venta", nombre: "Ventas" },
    { id: "movimiento", nombre: "Movimientos manuales" },
];

const opcionesTipo = [
    { id: "", nombre: "Todos" },
    { id: "ingreso", nombre: "Ingreso" },
    { id: "egreso", nombre: "Egreso" },
];

const opcionesFormaPago = [
    { id: "", nombre: "Todas" },
    { id: "efectivo", nombre: "Efectivo" },
    { id: "tarjeta", nombre: "Tarjeta" },
    { id: "transferencia", nombre: "Transferencia" },
    { id: "credito", nombre: "Crédito" },
];

const filtros = ref({
    desde: hoy,
    hasta: hoy,
    origen: "",
    tipo: "",
    forma_pago: "",
    concepto: "",
    user_id: "",
});

const movimientos = ref([]);
const paginacion = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});
const resumen = ref(null);
const cargando = ref(false);
const opcionesUsuario = ref([{ id: "", nombre: "Todos" }]);

onMounted(async () => {
    await cargarUsuarios();
    await buscar();
});

async function cargarUsuarios() {
    try {
        const { data } = await http.get("/api/movimientos-caja/usuarios");
        const lista = data.data ?? data;
        opcionesUsuario.value = [
            { id: "", nombre: "Todos" },
            ...lista.map((u) => ({ id: u.id, nombre: u.name })),
        ];
    } catch {
        // silencioso
    }
}

async function buscar(pagina = 1) {
    pagina = Number.isInteger(pagina) ? pagina : 1;
    cargando.value = true;
    resumen.value = null;
    try {
        const { data } = await http.get("/api/movimientos-caja", {
            params: { ...filtros.value, page: pagina, por_pagina: 25 },
        });

        movimientos.value = data.data ?? [];
        paginacion.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
            from: data.from ?? 0,
            to: data.to ?? 0,
        };

        resumen.value = data.summary ?? { ingresos: 0, egresos: 0, neto: 0 };
    } catch (e) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text:
                e.response?.data?.message ??
                "No se pudieron cargar los registros.",
            confirmButtonColor: "#0891b2",
        });
    } finally {
        cargando.value = false;
    }
}

function limpiar() {
    filtros.value = {
        desde: hoy,
        hasta: hoy,
        origen: "",
        tipo: "",
        forma_pago: "",
        concepto: "",
        user_id: "",
    };
    buscar();
}

function irPagina(p) {
    if (p < 1 || p > paginacion.value.last_page) return;
    buscar(p);
}

const paginasVisibles = computed(() => {
    const { current_page: cur, last_page: last } = paginacion.value;
    const pages = [];
    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= cur - 1 && i <= cur + 1))
            pages.push(i);
        else if (pages[pages.length - 1] !== "…") pages.push("…");
    }
    return pages;
});

function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}

function fmtFecha(f) {
    if (!f) return "—";
    const str = f.includes("T") ? f : f.replace(" ", "T");
    return new Date(str).toLocaleString("es-MX", {
        dateStyle: "short",
        timeStyle: "short",
    });
}

function etiquetaFormaPago(f) {
    return (
        {
            efectivo: "Efectivo",
            tarjeta: "Tarjeta",
            transferencia: "Transferencia",
            credito: "Crédito",
        }[f] ?? f
    );
}

function fechaLocal(date = new Date()) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}
</script>
