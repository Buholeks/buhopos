<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- TOPBAR -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div
                class="flex w-full flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-sm"
                    >
                        <Wallet class="h-5 w-5" />
                    </div>

                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900 sm:text-xl"
                        >
                            Reportes de caja
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Control de turnos ·
                            <span class="font-medium text-slate-700">
                                {{ sucursalNombre }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- FILTROS -->
            <section
                class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Filtros
                        </h2>
                        <p class="text-sm text-slate-500">
                            Ajusta el rango, cajero y estado del corte.
                        </p>
                    </div>

                </div>

                <div
                    class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-5"
                >
                    <BaseInput
                        v-model="filtros.fecha_desde"
                        label="Desde"
                        type="date"
                        @change="reiniciarYCargar"
                    />

                    <BaseInput
                        v-model="filtros.fecha_hasta"
                        label="Hasta"
                        type="date"
                        @change="reiniciarYCargar"
                    />

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Cajero
                        </label>
                        <div class="relative">
                            <Users
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="filtros.user_id"
                                @change="reiniciarYCargar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todos</option>
                                <option
                                    v-for="u in cajeros"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Estado
                        </label>
                        <div class="relative">
                            <ShieldCheck
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="filtros.estado"
                                @change="reiniciarYCargar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todos</option>
                                <option value="abierto">Abierto</option>
                                <option value="cerrado">Cerrado</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            type="button"
                            @click="limpiarFiltros"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Limpiar
                        </button>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-5 py-4"
                >
                    <button
                        type="button"
                        @click="exportar('excel')"
                        :disabled="exportando"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="exportando === 'excel'"
                            class="h-4 w-4 animate-spin"
                        />
                        <FileSpreadsheet v-else class="h-4 w-4" />
                        Excel
                    </button>
                    <button
                        type="button"
                        @click="exportar('pdf')"
                        :disabled="exportando"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100 focus:outline-none focus:ring-4 focus:ring-red-100 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="exportando === 'pdf'"
                            class="h-4 w-4 animate-spin"
                        />
                        <FileText v-else class="h-4 w-4" />
                        PDF
                    </button>
                </div>
            </section>

            <!-- VISTA COMPARATIVO -->
            <section class="space-y-4">
                <!-- LISTADO DE CORTES -->
                <div
                    v-if="cortesData.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                    >
                        <h2 class="text-sm font-semibold text-slate-900">
                            Listado de cortes
                        </h2>

                        <div
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600"
                        >
                            <Rows3 class="h-4 w-4" />
                            {{ cortesData.length }} registros
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-slate-500">
                                    <th class="px-4 py-3 font-medium">#</th>
                                    <th class="px-4 py-3 font-medium">
                                        Cajero
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Apertura
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Cierre
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Esperado ef.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        &Delta; Ef.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Contado ef.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Cobrado caja
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Total vendido
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Acci&oacute;n
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="c in cortesData"
                                    :key="c.id"
                                    class="cursor-pointer transition hover:bg-slate-50"
                                    @click="abrirDetalle(c.id)"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-slate-500"
                                    >
                                        {{ c.id }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ c.user?.name || "Sin cajero" }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-medium text-slate-700">
                                            {{ fmtDate(fechaAperturaCorte(c)) }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ fmtTime(fechaAperturaCorte(c)) }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <template v-if="c.fecha_cierre">
                                            <div class="font-medium text-slate-700">
                                                {{ fmtDate(c.fecha_cierre) }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{ fmtTime(c.fecha_cierre) }}
                                            </div>
                                        </template>
                                        <span v-else class="text-slate-400">
                                            Sin cierre
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                            :class="badgeEstadoClass(c.estado)"
                                        >
                                            {{ c.estado }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                    >
                                        {{ fmt(c.esperado_efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="inline-flex min-w-[104px] justify-end rounded-full px-2.5 py-1 font-mono text-xs font-semibold"
                                            :class="diferenciaClass(c.dif_efectivo)"
                                        >
                                            {{ fmtDif(c.dif_efectivo) }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                    >
                                        {{ fmt(c.contado_efectivo) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-emerald-700"
                                    >
                                        {{ fmt(c.total_cobrado_caja ?? c.total_ventas) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-slate-900"
                                    >
                                        {{ fmt(c.total_vendido ?? c.total_ventas) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                            @click.stop="abrirDetalle(c.id)"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            Ver
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot
                                v-if="cortesData.length"
                                class="border-t-2 border-slate-300 bg-slate-100 font-semibold text-slate-900"
                            >
                                <tr>
                                    <td class="px-4 py-3" colspan="5">
                                        Totales del listado filtrado
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totalesListado.esperado_efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="inline-flex min-w-[104px] justify-end rounded-full px-2.5 py-1 font-mono text-xs font-semibold"
                                            :class="diferenciaClass(totalesListado.dif_efectivo)"
                                        >
                                            {{ fmtDif(totalesListado.dif_efectivo) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totalesListado.contado_efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-700">
                                        {{ fmt(totalesListado.total_cobrado_caja) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-900">
                                        {{ fmt(totalesListado.total_vendido) }}
                                    </td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div
                        v-if="paginacion"
                        class="flex items-center justify-center gap-3 border-t border-slate-100 px-5 py-4"
                    >
                        <button
                            type="button"
                            :disabled="paginacion.current_page <= 1"
                            @click="cambiarPagina(paginacion.current_page - 1)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <span class="text-sm text-slate-600">
                            {{ paginacion.current_page }} /
                            {{ paginacion.last_page }}
                        </span>

                        <button
                            type="button"
                            :disabled="
                                paginacion.current_page >= paginacion.last_page
                            "
                            @click="cambiarPagina(paginacion.current_page + 1)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm"
                >
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                    >
                        <Inbox class="h-6 w-6" />
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Sin datos para mostrar
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Prueba ajustando los filtros del reporte.
                    </p>
                </div>
            </section>
        </div>

        <!-- LOADING -->
        <Transition name="fade">
            <div
                v-if="cargando"
                class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/25 backdrop-blur-[2px]"
            >
                <div
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-xl"
                >
                    <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                    <span class="text-sm font-medium text-slate-700">
                        Cargando reporte...
                    </span>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    FileSpreadsheet,
    FileText,
    Inbox,
    Loader2,
    RotateCcw,
    Rows3,
    ShieldCheck,
    Users,
    Wallet,
} from "lucide-vue-next";

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
    sucursalNombre: { type: String, default: "Principal" },
    apiBase: { type: String, default: "/api/reportes/caja" },
    cajeros: { type: Array, default: () => [] },
});

// ── Estado ─────────────────────────────────────────────────────────────────
const router = useRouter();

const cargando = ref(false);
const cortesData = ref([]);
const paginacion = ref(null);
const exportando = ref(null);

// Los filtros se guardan en el historial del navegador (no en la URL): al volver
// de corte-detalle con "atrás" se conservan; al entrar desde el menú inician en hoy.
const CLAVE_FILTROS = "filtrosReporteCaja";
const guardados = window.history.state?.[CLAVE_FILTROS] ?? {};
const filtros = reactive({
    fecha_desde: guardados.fecha_desde ?? hoy(),
    fecha_hasta: guardados.fecha_hasta ?? hoy(),
    user_id: guardados.user_id ?? "",
    estado: guardados.estado ?? "",
    pagina: guardados.pagina ?? 1,
});

// ── Ciclo de vida ──────────────────────────────────────────────────────────
onMounted(async () => {
    await cargarCortes();
});

// ── Métodos de carga ───────────────────────────────────────────────────────
async function cargarCortes() {
    cargando.value = true;

    try {
        const params = {
            fecha_desde: filtros.fecha_desde || undefined,
            fecha_hasta: filtros.fecha_hasta || undefined,
            user_id: filtros.user_id || undefined,
            estado: filtros.estado || undefined,
            page: filtros.pagina,
            por_pagina: 25,
        };

        // Se guardan en la entrada actual del historial, sin tocar la URL.
        window.history.replaceState(
            { ...window.history.state, [CLAVE_FILTROS]: { ...filtros } },
            "",
        );

        const { data } = await axios.get(props.apiBase, { params });

        cortesData.value = data.data ?? [];
        paginacion.value = {
            current_page: data.current_page,
            last_page: data.last_page,
        };
    } catch (e) {
        console.error(e);
    } finally {
        cargando.value = false;
    }
}

// El detalle de un corte es una sola vista para todo el sistema: corte-detalle.
function abrirDetalle(id) {
    router.push({ name: "corte-detalle", params: { id } });
}

function cambiarPagina(p) {
    filtros.pagina = p;
    cargarCortes();
}

function reiniciarYCargar() {
    filtros.pagina = 1;
    cargarCortes();
}

function limpiarFiltros() {
    filtros.fecha_desde = hoy();
    filtros.fecha_hasta = hoy();
    filtros.user_id = "";
    filtros.estado = "";
    filtros.pagina = 1;
    cargarCortes();
}

async function exportar(formato) {
    exportando.value = formato;
    try {
        const resp = await axios.get(`${props.apiBase}/exportar`, {
            params: {
                fecha_desde: filtros.fecha_desde,
                fecha_hasta: filtros.fecha_hasta,
                user_id: filtros.user_id,
                estado: filtros.estado,
                formato,
            },
            responseType: "blob",
        });
        const ext = formato === "excel" ? "xlsx" : "pdf";
        const url = URL.createObjectURL(new Blob([resp.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = `reporte_caja_${new Date().toISOString().slice(0, 10)}.${ext}`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error(e);
        alert("No se pudo generar el archivo.");
    } finally {
        exportando.value = null;
    }
}

// ── Computed ───────────────────────────────────────────────────────────────
const totalesListado = computed(() => {
    const base = {
        ventas_efectivo: 0,
        ventas_tarjeta: 0,
        ventas_transferencia: 0,
        ventas_saldo_favor: 0,
        total_cobrado_caja: 0,
        total_vendido: 0,
        esperado_efectivo: 0,
        dif_efectivo: 0,
        contado_efectivo: 0,
    };

    return cortesData.value.reduce((totales, corte) => {
        const cobradoCaja = Number(corte.total_cobrado_caja ?? corte.total_ventas ?? 0);
        const saldoFavor = Number(corte.ventas_saldo_favor || 0);

        totales.ventas_efectivo += Number(corte.ventas_efectivo || 0);
        totales.ventas_tarjeta += Number(corte.ventas_tarjeta || 0);
        totales.ventas_transferencia += Number(corte.ventas_transferencia || 0);
        totales.ventas_saldo_favor += saldoFavor;
        totales.total_cobrado_caja += cobradoCaja;
        totales.total_vendido += Number(corte.total_vendido ?? (cobradoCaja + saldoFavor) ?? 0);
        totales.esperado_efectivo += Number(corte.esperado_efectivo || 0);
        totales.dif_efectivo += Number(corte.dif_efectivo || 0);
        totales.contado_efectivo += Number(corte.contado_efectivo || 0);

        return totales;
    }, { ...base });
});

// ── Helpers visuales ───────────────────────────────────────────────────────
function badgeEstadoClass(estado) {
    if (estado === "abierto") return "bg-emerald-50 text-emerald-700";
    if (estado === "cerrado") return "bg-slate-100 text-slate-700";
    if (estado === "anulado") return "bg-red-50 text-red-700";
    return "bg-slate-100 text-slate-600";
}

function diferenciaClass(valor) {
    const n = Number(valor || 0);

    if (n < 0) return "bg-red-50 text-red-700";
    if (n > 0) return "bg-emerald-50 text-emerald-700";
    return "bg-slate-100 text-slate-600";
}

// ── Formatters ─────────────────────────────────────────────────────────────
function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(+v || 0);
}

function fmtDif(v) {
    const n = +v || 0;
    return (
        (n >= 0 ? "+" : "") +
        new Intl.NumberFormat("es-MX", {
            style: "currency",
            currency: "MXN",
        }).format(n)
    );
}

function fmtFecha(str) {
    return new Date(str + "T12:00:00").toLocaleDateString("es-MX", {
        weekday: "short",
        day: "2-digit",
        month: "short",
    });
}

function fechaAperturaCorte(corte) {
    if (
        corte?.fecha_apertura &&
        corte?.fecha_cierre &&
        corte?.created_at &&
        new Date(corte.fecha_apertura).getTime() ===
            new Date(corte.fecha_cierre).getTime() &&
        new Date(corte.created_at).getTime() !==
            new Date(corte.fecha_cierre).getTime()
    ) {
        return corte.created_at;
    }

    return corte?.fecha_apertura;
}

function fmtDate(str) {
    if (!str) return "Sin fecha";

    return new Date(str).toLocaleDateString("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function fmtTime(str) {
    if (!str) return "";

    return new Date(str).toLocaleTimeString("es-MX", {
        hour: "2-digit",
        minute: "2-digit",
    });
}

function hoy() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, "0");
    const d = String(now.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
