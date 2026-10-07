<template>
    <main class="mx-auto max-w-screen-2xl space-y-5 p-3 sm:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Reportes</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Comisiones</h1>
                <p class="mt-1 text-sm text-slate-500">Lo que ganó cada empleado en el periodo, ya con devoluciones y cancelaciones descontadas.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <RouterLink v-if="auth.can('comisiones.liquidar')" :to="{ name: 'comisiones-liquidaciones' }" class="boton-primario">
                    <BadgeDollarSign class="h-4 w-4" /> Pagar comisiones
                </RouterLink>
                <RouterLink v-if="auth.can('comisiones.configurar')" :to="{ name: 'comisiones-configuracion' }" class="boton-icono" title="Configurar comisiones" aria-label="Configurar comisiones">
                    <Settings2 class="h-4 w-4" />
                </RouterLink>
            </div>
        </header>

        <!-- Filtros -->
        <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <FiltroPeriodo @cambio="cambiarPeriodo" />
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <label class="flex items-center gap-2">
                    <span class="sr-only">Sucursal</span>
                    <Store class="h-4 w-4 text-slate-400" />
                    <select v-model="sucursalId" class="campo campo-auto campo-compacto min-w-[170px]" @change="cargar">
                        <option :value="null">Todas las sucursales</option>
                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                </label>
                <label class="relative min-w-[200px] flex-1 sm:max-w-xs">
                    <span class="sr-only">Buscar empleado</span>
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="busqueda" type="search" placeholder="Buscar empleado…" class="campo campo-compacto campo-busqueda" />
                </label>

                <div class="relative ml-auto">
                    <button
                        type="button"
                        class="boton-secundario"
                        :disabled="!!exportando || cargando || !!errorReporte || !periodo || !empleados.length"
                        :aria-expanded="menuExportar"
                        @click="menuExportar = !menuExportar"
                    >
                        <Loader2 v-if="exportando" class="h-4 w-4 animate-spin" />
                        <Download v-else class="h-4 w-4" />
                        Exportar
                        <ChevronDown class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                    <div v-if="menuExportar" class="fixed inset-0 z-10" @click="menuExportar = false"></div>
                    <div v-if="menuExportar" class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        <button v-for="f in FORMATOS" :key="f.valor" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" @click="menuExportar = false; exportar(f.valor)">
                            <FileSpreadsheet v-if="f.valor === 'excel'" class="h-4 w-4 text-emerald-600" />
                            <FileDown v-else class="h-4 w-4 text-red-500" />
                            {{ f.label }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Indicadores -->
        <div v-if="cargando" class="h-[66px] animate-pulse rounded-2xl border border-slate-200 bg-white"></div>
        <ResumenComision v-else-if="totales" :datos="totales" />

        <!-- Tabla por empleado -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div v-if="cargando" class="divide-y divide-slate-100" aria-busy="true">
                <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3 px-4 py-4">
                    <div class="h-9 w-9 rounded-full bg-slate-100"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-40 rounded bg-slate-100"></div>
                        <div class="h-1.5 w-56 rounded bg-slate-100"></div>
                    </div>
                    <div class="h-4 w-24 rounded bg-slate-100"></div>
                </div>
            </div>

            <div v-else-if="errorReporte" class="flex flex-col items-center gap-3 p-10 text-center">
                <CircleAlert class="h-9 w-9 text-red-400" />
                <p class="text-sm text-red-600">{{ errorReporte }}</p>
                <button type="button" class="boton-secundario" @click="cargar">
                    <RotateCw class="h-4 w-4" /> Reintentar
                </button>
            </div>

            <div v-else-if="!empleados.length" class="flex flex-col items-center gap-2 p-12 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-300">
                    <BadgePercent class="h-7 w-7" />
                </span>
                <p class="mt-1 text-sm font-semibold text-slate-700">No hay comisiones en este periodo</p>
                <p class="max-w-sm text-xs text-slate-500">Aparecen aquí cuando un vendedor o cajero con esquema asignado registra ventas.</p>
                <RouterLink v-if="auth.can('comisiones.configurar')" :to="{ name: 'comisiones-configuracion' }" class="mt-2 text-sm font-semibold text-emerald-700 hover:underline">
                    Revisar esquemas y asignaciones →
                </RouterLink>
            </div>

            <template v-else>
                <!-- Encabezado de la lista -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">
                        Por empleado
                        <span class="ml-1 font-normal text-slate-400">{{ empleadosVista.length }}<template v-if="busqueda"> de {{ empleados.length }}</template></span>
                    </h2>
                    <p class="text-xs text-slate-400">Haz clic en un empleado para ver su detalle.</p>
                </div>

                <!-- Escritorio -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th v-for="c in COLUMNAS" v-show="columnaVisible(c.valor)" :key="c.valor" class="px-4 py-2.5" :class="c.clase" :aria-sort="ariaOrden(c.valor)">
                                    <button type="button" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-slate-800" @click="ordenarPor(c.valor)">
                                        {{ c.label }}
                                        <ArrowDown v-if="orden.campo === c.valor && orden.desc" class="h-3 w-3" />
                                        <ArrowUp v-else-if="orden.campo === c.valor" class="h-3 w-3" />
                                        <ArrowUpDown v-else class="h-3 w-3 opacity-30" />
                                    </button>
                                </th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="e in empleadosVista"
                                :key="e.user_id"
                                class="group cursor-pointer transition hover:bg-emerald-50/40 focus:bg-emerald-50/40 focus:outline-none"
                                tabindex="0"
                                @click="abrirDetalle(e)"
                                @keydown.enter="abrirDetalle(e)"
                            >
                                <!-- Empleado -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{ iniciales(e.nombre) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-semibold text-slate-800">{{ e.nombre }}</p>
                                            <p class="text-[11px] text-slate-400">{{ e.tickets_vendedor + e.tickets_cajero }} ticket(s)</p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Comisiones -->
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <p class="text-slate-800">{{ dinero(e.comision_vendedor + e.comision_cajero) }}</p>
                                    <p v-if="e.comision_cajero" class="text-[11px] text-slate-400">
                                        Vendedor {{ dinero(e.comision_vendedor) }} · Cajero {{ dinero(e.comision_cajero) }}
                                    </p>
                                </td>
                                <!-- Descuentos -->
                                <td class="px-4 py-3 text-right tabular-nums" :class="e.descuentos ? 'text-red-600' : 'text-slate-300'">{{ dinero(e.descuentos) }}</td>
                                <!-- Ajustes -->
                                <td v-show="columnaVisible('ajustes')" class="px-4 py-3 text-right tabular-nums" :class="e.ajustes ? 'text-slate-700' : 'text-slate-300'">{{ dinero(e.ajustes) }}</td>
                                <!-- Total -->
                                <td class="px-4 py-3 text-right text-base font-bold tabular-nums text-slate-900">{{ dinero(e.total) }}</td>
                                <!-- Pago -->
                                <td class="px-4 py-3 text-right">
                                    <p class="font-bold tabular-nums" :class="e.pendiente > 0 ? 'text-emerald-700' : 'text-slate-400'">{{ dinero(e.pendiente) }}</p>
                                    <p class="text-[11px] tabular-nums text-slate-400">Pagado {{ dinero(e.pagado) }}</p>
                                </td>
                                <td class="pr-3 text-slate-300 transition group-hover:text-emerald-500"><ChevronRight class="h-4 w-4" /></td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 tabular-nums">
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-600">Total · {{ totales.empleados }} empleado(s)</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ dinero(totales.comision_vendedor + totales.comision_cajero) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">{{ dinero(totales.descuentos) }}</td>
                                <td v-show="columnaVisible('ajustes')" class="px-4 py-3 text-right font-semibold">{{ dinero(totales.ajustes) }}</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-slate-900">{{ dinero(totales.total) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-bold text-emerald-700">{{ dinero(totales.pendiente) }}</span>
                                    <span class="block text-[11px] font-normal text-slate-400">Pagado {{ dinero(totales.pagado) }}</span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Celular -->
                <ul class="divide-y divide-slate-100 md:hidden">
                    <li v-for="e in empleadosVista" :key="e.user_id">
                        <button type="button" class="w-full px-4 py-3 text-left" @click="abrirDetalle(e)">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{ iniciales(e.nombre) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-800">{{ e.nombre }}</span>
                                    <span class="block text-xs text-slate-500">{{ e.tickets_vendedor + e.tickets_cajero }} ticket(s)</span>
                                </span>
                                <span class="text-right">
                                    <span class="block text-sm font-bold tabular-nums text-slate-900">{{ dinero(e.total) }}</span>
                                    <span class="block text-[11px] tabular-nums" :class="e.pendiente > 0 ? 'text-emerald-700' : 'text-slate-400'">Pendiente {{ dinero(e.pendiente) }}</span>
                                </span>
                            </div>
                        </button>
                    </li>
                </ul>

                <p v-if="!empleadosVista.length" class="p-8 text-center text-sm text-slate-400">Ningún empleado coincide con "{{ busqueda }}".</p>
            </template>
        </section>

        <!-- Detalle del empleado -->
        <BaseModal :open="!!detalle" size="xl" @close="cerrarDetalle">
            <template #header>
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-800">
                        {{ iniciales(detalle?.empleado?.name ?? detalleNombre) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-base font-semibold text-slate-900">{{ detalle?.empleado?.name ?? detalleNombre }}</h2>
                        <p class="text-xs text-slate-500">{{ textoPeriodo }}<template v-if="sucursalNombre"> · {{ sucursalNombre }}</template></p>
                    </div>
                    <RouterLink
                        v-if="detalle?.resumen?.pendiente > 0 && auth.can('comisiones.liquidar')"
                        :to="{ name: 'comisiones-liquidaciones' }"
                        class="boton-primario py-1.5"
                    >
                        <BadgeDollarSign class="h-4 w-4" /> Pagar {{ dinero(detalle.resumen.pendiente) }}
                    </RouterLink>
                </div>
            </template>
            <div class="space-y-4">
                <ResumenComision v-if="detalle?.resumen" :datos="detalle.resumen" />
                <MovimientosComision
                    :pagina="detalle?.movimientos"
                    :cargando="cargandoDetalle"
                    @filtrar="filtrarDetalle"
                    @pagina="paginaDetalle"
                />
            </div>
        </BaseModal>
    </main>
</template>

<script setup>
import { computed, ref } from "vue";
import {
    ArrowDown, ArrowUp, ArrowUpDown, BadgeDollarSign, BadgePercent, ChevronDown, ChevronRight, CircleAlert,
    Download, FileDown, FileSpreadsheet, Loader2, RotateCw, Search, Settings2, Store,
} from "lucide-vue-next";
import BaseModal from "@/components/ui/BaseModal.vue";
import FiltroPeriodo from "@/components/comisiones/FiltroPeriodo.vue";
import MovimientosComision from "@/components/comisiones/MovimientosComision.vue";
import ResumenComision from "@/components/comisiones/ResumenComision.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import { dinero, iniciales } from "@/helpers/comisiones";

const auth = useAuthStore();

const periodo = ref(null);
const sucursalId = ref(null);
const sucursales = ref([]);
const empleados = ref([]);
const totales = ref(null);
const cargando = ref(true);
const exportando = ref(null); // "excel" | "pdf" mientras se descarga
const menuExportar = ref(false);
const FORMATOS = [
    { valor: "excel", label: "Excel (.xlsx)", extension: "xlsx",
        tipo: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" },
    { valor: "pdf", label: "PDF", extension: "pdf", tipo: "application/pdf" },
];
const errorReporte = ref("");

// Tabla: búsqueda, orden y columnas
const busqueda = ref("");
const orden = ref({ campo: "total", desc: true });
const COLUMNAS = [
    { valor: "nombre", label: "Empleado", clase: "text-left" },
    { valor: "comisiones", label: "Comisiones", clase: "text-right" },
    { valor: "descuentos", label: "Devoluciones / cancel.", clase: "text-right" },
    { valor: "ajustes", label: "Ajustes", clase: "text-right" },
    { valor: "total", label: "Total", clase: "text-right" },
    { valor: "pendiente", label: "Pendiente", clase: "text-right" },
];

const detalle = ref(null);
const detalleUserId = ref(null);
const detalleNombre = ref("");
const detalleFiltros = ref({});
const cargandoDetalle = ref(false);
let solicitud = 0;
let solicitudDetalle = 0;

const fmt = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric" });
const textoPeriodo = computed(() => {
    if (!periodo.value) return "";
    const f = (t) => { const [a, m, d] = t.split("-").map(Number); return fmt.format(new Date(a, m - 1, d)); };
    return `${f(periodo.value.desde)} – ${f(periodo.value.hasta)}`;
});

const sucursalNombre = computed(() => sucursales.value.find((s) => s.id === sucursalId.value)?.nombre ?? "");

/** Valor por el que se ordena cada columna. */
function valorOrden(e, campo) {
    if (campo === "nombre") return (e.nombre ?? "").toLocaleLowerCase("es");
    if (campo === "comisiones") return e.comision_vendedor + e.comision_cajero;
    return Number(e[campo] ?? 0);
}

const empleadosVista = computed(() => {
    const q = busqueda.value.trim().toLocaleLowerCase("es");
    const lista = q ? empleados.value.filter((e) => (e.nombre ?? "").toLocaleLowerCase("es").includes(q)) : [...empleados.value];
    const { campo, desc } = orden.value;
    return lista.sort((a, b) => {
        const va = valorOrden(a, campo);
        const vb = valorOrden(b, campo);
        const r = typeof va === "string" ? va.localeCompare(vb, "es") : va - vb;
        return desc ? -r : r;
    });
});

function columnaVisible(valor) {
    // Ajustes solo se muestra si alguien tiene ajustes en el periodo.
    if (valor === "ajustes") return empleados.value.some((e) => Number(e.ajustes) !== 0);
    return true;
}

function ordenarPor(campo) {
    orden.value = orden.value.campo === campo
        ? { campo, desc: !orden.value.desc }
        : { campo, desc: campo !== "nombre" };
}

function ariaOrden(campo) {
    if (orden.value.campo !== campo) return "none";
    return orden.value.desc ? "descending" : "ascending";
}

async function exportar(formato) {
    const f = FORMATOS.find((x) => x.valor === formato);
    exportando.value = formato;
    try {
        const resp = await http.get("/api/comisiones/reporte/exportar", { params: params({ formato }), responseType: "blob" });
        const url = URL.createObjectURL(new Blob([resp.data], { type: f.tipo }));
        const a = document.createElement("a");
        a.href = url;
        a.download = `comisiones_${periodo.value.desde}_${periodo.value.hasta}.${f.extension}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        toastError("No se pudo exportar el reporte.");
    } finally {
        exportando.value = null;
    }
}

function params(extra = {}) {
    return { ...periodo.value, sucursal_id: sucursalId.value ?? undefined, ...extra };
}

function cambiarPeriodo(p) {
    periodo.value = p;
    cargar();
}

async function cargar() {
    if (!periodo.value) return;
    const actual = ++solicitud;
    errorReporte.value = "";
    cerrarDetalle();
    empleados.value = [];
    totales.value = null;
    cargando.value = true;
    try {
        const { data } = await http.get("/api/comisiones/reporte", { params: params() });
        if (actual !== solicitud) return;
        empleados.value = data.empleados;
        totales.value = data.totales;
        sucursales.value = data.sucursales;
    } catch (e) {
        if (actual !== solicitud) return;
        errorReporte.value = e?.response?.data?.message || "No se pudo cargar el reporte. Intenta consultar de nuevo.";
        toastError(e?.response?.data?.message || "No se pudo cargar el reporte.");
    } finally {
        if (actual === solicitud) cargando.value = false;
    }
}

async function cargarDetalle(page = 1) {
    const actual = ++solicitudDetalle;
    cargandoDetalle.value = true;
    try {
        const { data } = await http.get(`/api/comisiones/reporte/${detalleUserId.value}`, {
            params: params({ ...detalleFiltros.value, page }),
        });
        if (actual !== solicitudDetalle) return;
        detalle.value = data;
    } catch (e) {
        if (actual !== solicitudDetalle) return;
        if (detalle.value) {
            detalle.value.movimientos = null;
            detalle.value.resumen = null;
        }
        toastError(e?.response?.data?.message || "No se pudo cargar el detalle.");
    } finally {
        if (actual === solicitudDetalle) cargandoDetalle.value = false;
    }
}

function abrirDetalle(e) {
    detalleUserId.value = e.user_id;
    detalleNombre.value = e.nombre;
    detalleFiltros.value = {};
    detalle.value = { empleado: { name: e.nombre }, resumen: e, movimientos: null };
    cargarDetalle();
}

function cerrarDetalle() {
    ++solicitudDetalle;
    cargandoDetalle.value = false;
    detalle.value = null;
}

function filtrarDetalle(f) {
    detalleFiltros.value = f;
    cargarDetalle(1);
}

function paginaDetalle(p) {
    cargarDetalle(p);
}
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
/* Deja espacio al ícono de lupa. */
.campo-busqueda {
    @apply pl-9;
}
.campo-compacto {
    @apply py-1.5;
}
.campo-auto {
    @apply w-auto;
}
.boton-primario {
    @apply flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700;
}
.boton-secundario {
    @apply flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50;
}
.boton-icono {
    @apply flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-800;
}
</style>
