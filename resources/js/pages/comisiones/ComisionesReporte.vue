<template>
    <main class="mx-auto max-w-screen-2xl space-y-5 p-3 sm:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Reportes</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Comisiones</h1>
                <p class="mt-1 text-sm text-slate-500">Lo que ganó cada empleado en el periodo, ya con devoluciones y cancelaciones descontadas.</p>
            </div>
            <RouterLink v-if="auth.can('comisiones.configurar')" :to="{ name: 'comisiones-configuracion' }" class="boton-secundario">
                <Settings2 class="h-4 w-4" /> Configurar comisiones
            </RouterLink>
        </header>

        <!-- Filtros -->
        <section class="flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
            <FiltroPeriodo @cambio="cambiarPeriodo" />
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Sucursal</span>
                <select v-model="sucursalId" class="campo min-w-[180px]" @change="cargar">
                    <option :value="null">Todas</option>
                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                </select>
            </label>
        </section>

        <ResumenComision v-if="totales" :datos="totales" />

        <!-- Tabla por empleado -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div v-if="cargando" class="flex items-center justify-center gap-2 p-12 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" /> Calculando…
            </div>

            <div v-else-if="!empleados.length" class="flex flex-col items-center gap-2 p-12 text-center">
                <BadgePercent class="h-10 w-10 text-slate-300" />
                <p class="text-sm font-semibold text-slate-600">No hay comisiones en este periodo</p>
                <p class="max-w-sm text-xs text-slate-400">Las comisiones aparecen aquí cuando un vendedor o cajero con esquema asignado registra ventas.</p>
            </div>

            <template v-else>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Empleado</th>
                                <th class="px-4 py-2.5 text-right">Como vendedor</th>
                                <th class="px-4 py-2.5 text-right">Como cajero</th>
                                <th class="px-4 py-2.5 text-right">Devoluciones</th>
                                <th class="px-4 py-2.5 text-right">Total</th>
                                <th class="px-4 py-2.5 text-right">Pagado</th>
                                <th class="px-4 py-2.5 text-right">Pendiente</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="e in empleados"
                                :key="e.user_id"
                                class="cursor-pointer transition hover:bg-emerald-50/40"
                                tabindex="0"
                                @click="abrirDetalle(e)"
                                @keydown.enter="abrirDetalle(e)"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{ iniciales(e.nombre) }}</span>
                                        <span class="font-semibold text-slate-800">{{ e.nombre }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span class="text-slate-800">{{ dinero(e.comision_vendedor) }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ e.tickets_vendedor }} ticket(s)</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span class="text-slate-800">{{ dinero(e.comision_cajero) }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ e.tickets_cajero }} ticket(s)</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums" :class="e.descuentos ? 'text-red-600' : 'text-slate-400'">{{ dinero(e.descuentos) }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">{{ dinero(e.total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ dinero(e.pagado) }}</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums" :class="e.pendiente > 0 ? 'text-emerald-700' : 'text-slate-400'">{{ dinero(e.pendiente) }}</td>
                                <td class="pr-3 text-slate-300"><ChevronRight class="h-4 w-4" /></td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold tabular-nums">
                            <tr>
                                <td class="px-4 py-2.5 text-slate-600">{{ totales.empleados }} empleado(s)</td>
                                <td class="px-4 py-2.5 text-right">{{ dinero(totales.comision_vendedor) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ dinero(totales.comision_cajero) }}</td>
                                <td class="px-4 py-2.5 text-right text-red-600">{{ dinero(totales.descuentos) }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-900">{{ dinero(totales.total) }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-500">{{ dinero(totales.pagado) }}</td>
                                <td class="px-4 py-2.5 text-right text-emerald-700">{{ dinero(totales.pendiente) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <ul class="divide-y divide-slate-100 md:hidden">
                    <li v-for="e in empleados" :key="e.user_id">
                        <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left" @click="abrirDetalle(e)">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{ iniciales(e.nombre) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-800">{{ e.nombre }}</span>
                                <span class="block text-xs text-slate-500">Total {{ dinero(e.total) }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block text-sm font-bold tabular-nums text-emerald-700">{{ dinero(e.pendiente) }}</span>
                                <span class="block text-[11px] text-slate-400">pendiente</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </template>
        </section>

        <!-- Detalle del empleado -->
        <BaseModal :open="!!detalle" :title="detalle?.empleado?.name ?? detalleNombre" :subtitle="textoPeriodo" size="xl" @close="cerrarDetalle">
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
import { BadgePercent, ChevronRight, Loader2, Settings2 } from "lucide-vue-next";
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

const detalle = ref(null);
const detalleUserId = ref(null);
const detalleNombre = ref("");
const detalleFiltros = ref({});
const cargandoDetalle = ref(false);

const fmt = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric" });
const textoPeriodo = computed(() => {
    if (!periodo.value) return "";
    const f = (t) => { const [a, m, d] = t.split("-").map(Number); return fmt.format(new Date(a, m - 1, d)); };
    return `${f(periodo.value.desde)} – ${f(periodo.value.hasta)}`;
});

function params(extra = {}) {
    return { ...periodo.value, sucursal_id: sucursalId.value ?? undefined, ...extra };
}

function cambiarPeriodo(p) {
    periodo.value = p;
    cargar();
}

async function cargar() {
    if (!periodo.value) return;
    cargando.value = true;
    try {
        const { data } = await http.get("/api/comisiones/reporte", { params: params() });
        empleados.value = data.empleados;
        totales.value = data.totales;
        sucursales.value = data.sucursales;
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo cargar el reporte.");
    } finally {
        cargando.value = false;
    }
}

async function cargarDetalle(page = 1) {
    cargandoDetalle.value = true;
    try {
        const { data } = await http.get(`/api/comisiones/reporte/${detalleUserId.value}`, {
            params: params({ ...detalleFiltros.value, page }),
        });
        detalle.value = data;
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo cargar el detalle.");
    } finally {
        cargandoDetalle.value = false;
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
.boton-secundario {
    @apply flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50;
}
</style>
