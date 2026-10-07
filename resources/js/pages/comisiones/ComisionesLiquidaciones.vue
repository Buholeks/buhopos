<template>
    <main class="mx-auto max-w-screen-2xl space-y-5 p-3 sm:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Comisiones</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Liquidaciones</h1>
                <p class="mt-1 text-sm text-slate-500">Paga las comisiones pendientes y lleva el historial de pagos.</p>
            </div>
            <RouterLink v-if="auth.can('comisiones.ver')" :to="{ name: 'comisiones-reporte' }" class="boton-secundario">
                <BarChart3 class="h-4 w-4" /> Reporte de comisiones
            </RouterLink>
        </header>

        <nav class="flex gap-1 border-b border-slate-200" aria-label="Secciones de liquidaciones">
            <button
                v-for="t in TABS"
                :key="t.valor"
                type="button"
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="tab === t.valor ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                :aria-current="tab === t.valor ? 'page' : undefined"
                @click="tab = t.valor"
            >
                <component :is="t.icono" class="h-4 w-4" />
                {{ t.label }}
            </button>
        </nav>

        <!-- ══ Por pagar ═════════════════════════════════════════════════════ -->
        <template v-if="tab === 'pendientes'">
            <section class="flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                <div>
                    <label for="liq-hasta" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Pagar comisiones hasta el</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input id="liq-hasta" v-model="hasta" type="date" class="campo campo-auto" @change="cargarPendientes" />
                        <button v-for="c in CORTES" :key="c.label" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" @click="usarCorte(c.fecha())">
                            {{ c.label }}
                        </button>
                    </div>
                </div>
                <p class="max-w-md text-xs text-slate-500">
                    Incluye todo lo pendiente hasta esa fecha, también descuentos de periodos ya pagados.
                </p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div v-if="cargandoPendientes" class="flex items-center justify-center gap-2 p-12 text-sm text-slate-500">
                    <Loader2 class="h-4 w-4 animate-spin" /> Calculando…
                </div>
                <div v-else-if="!pendientes.length" class="flex flex-col items-center gap-2 p-12 text-center">
                    <CircleCheck class="h-10 w-10 text-emerald-300" />
                    <p class="text-sm font-semibold text-slate-600">No hay comisiones pendientes hasta esa fecha</p>
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="p in pendientes" :key="p.user_id" class="flex flex-wrap items-center gap-4 px-4 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{ iniciales(p.nombre) }}</span>
                        <div class="min-w-[160px] flex-1">
                            <p class="text-sm font-semibold text-slate-800">{{ p.nombre }}</p>
                            <p class="text-xs text-slate-500">{{ p.movimientos }} movimiento(s) desde el {{ fecha(p.desde) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p>Comisiones {{ dinero(p.comisiones) }}</p>
                            <p v-if="p.descuentos" class="text-red-600">Descuentos {{ dinero(p.descuentos) }}</p>
                        </div>
                        <p class="w-32 text-right text-lg font-bold tabular-nums" :class="p.total > 0 ? 'text-slate-900' : 'text-red-600'">{{ dinero(p.total) }}</p>
                        <button
                            v-if="p.total > 0"
                            type="button"
                            class="boton-primario"
                            :disabled="generando === p.user_id"
                            @click="generar(p)"
                        >
                            <Loader2 v-if="generando === p.user_id" class="h-4 w-4 animate-spin" />
                            <ReceiptText v-else class="h-4 w-4" />
                            Liquidar
                        </button>
                        <span v-else class="w-[118px] text-right text-xs text-slate-400" title="Se descontará en la siguiente liquidación">Saldo en contra</span>
                    </li>
                </ul>
            </section>
        </template>

        <!-- ══ Historial ═════════════════════════════════════════════════════ -->
        <section v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Estado">
                    <button
                        v-for="e in ESTADOS"
                        :key="e.valor"
                        type="button"
                        role="radio"
                        :aria-checked="estado === e.valor"
                        class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                        :class="estado === e.valor ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                        @click="cambiarEstado(e.valor)"
                    >
                        {{ e.label }}
                    </button>
                </div>
            </div>

            <div v-if="cargandoHistorial" class="flex items-center justify-center gap-2 p-12 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" /> Cargando…
            </div>
            <p v-else-if="!historial?.data?.length" class="p-12 text-center text-sm text-slate-400">No hay liquidaciones.</p>

            <template v-else>
                <ul class="divide-y divide-slate-100">
                    <li v-for="l in historial.data" :key="l.id">
                        <button type="button" class="flex w-full flex-wrap items-center gap-4 px-4 py-3 text-left transition hover:bg-slate-50" @click="abrir(l.id)">
                            <span class="w-14 font-mono text-xs text-slate-400">#{{ l.id }}</span>
                            <div class="min-w-[160px] flex-1">
                                <p class="text-sm font-semibold text-slate-800">{{ l.empleado?.name }}</p>
                                <p class="text-xs text-slate-500">{{ fecha(l.desde) }} – {{ fecha(l.hasta) }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="CHIP_ESTADO[l.estado]">{{ ETIQUETA_ESTADO[l.estado] }}</span>
                            <span class="hidden w-36 text-xs text-slate-500 sm:block">
                                <template v-if="l.estado === 'pagada'">{{ l.salio_de_caja ? 'Salió de caja' : 'Sin caja' }} · {{ fecha(l.pagado_en) }}</template>
                            </span>
                            <span class="w-32 text-right font-bold tabular-nums" :class="l.estado === 'cancelada' ? 'text-slate-400 line-through' : 'text-slate-900'">{{ dinero(l.total) }}</span>
                            <ChevronRight class="h-4 w-4 text-slate-300" />
                        </button>
                    </li>
                </ul>
                <div v-if="historial.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5 text-sm">
                    <span class="text-slate-500">{{ historial.from }}–{{ historial.to }} de {{ historial.total }}</span>
                    <div class="flex gap-1">
                        <button type="button" class="boton-pagina" :disabled="historial.current_page <= 1" aria-label="Página anterior" @click="cargarHistorial(historial.current_page - 1)">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <button type="button" class="boton-pagina" :disabled="historial.current_page >= historial.last_page" aria-label="Página siguiente" @click="cargarHistorial(historial.current_page + 1)">
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </template>
        </section>

        <LiquidacionModal
            :open="!!abiertaId"
            :liquidacion-id="abiertaId"
            :inicial="recienGenerada"
            @close="cerrar"
            @cambio="huboCambio"
        />
    </main>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { BarChart3, ChevronLeft, ChevronRight, CircleCheck, History, Loader2, ReceiptText, Wallet } from "lucide-vue-next";
import LiquidacionModal from "@/components/comisiones/LiquidacionModal.vue";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import { dinero, iniciales } from "@/helpers/comisiones";

const auth = useAuthStore();

const TABS = [
    { valor: "pendientes", label: "Por pagar", icono: Wallet },
    { valor: "historial", label: "Historial", icono: History },
];
const ESTADOS = [
    { valor: "", label: "Todas" },
    { valor: "borrador", label: "Por pagar" },
    { valor: "pagada", label: "Pagadas" },
    { valor: "cancelada", label: "Canceladas" },
];
const ETIQUETA_ESTADO = { borrador: "Por pagar", pagada: "Pagada", cancelada: "Cancelada" };
const CHIP_ESTADO = {
    borrador: "bg-amber-100 text-amber-800",
    pagada: "bg-emerald-100 text-emerald-800",
    cancelada: "bg-slate-200 text-slate-600",
};

function iso(d) {
    const p = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

/** Último día de la quincena anterior a hoy: 15 del mes o último día del mes pasado. */
function finQuincenaAnterior() {
    const hoy = new Date();
    return hoy.getDate() > 15 ? new Date(hoy.getFullYear(), hoy.getMonth(), 15) : new Date(hoy.getFullYear(), hoy.getMonth(), 0);
}

const CORTES = [
    { label: "Quincena anterior", fecha: finQuincenaAnterior },
    { label: "Hoy", fecha: () => new Date() },
];

const tab = ref("pendientes");
const hasta = ref(iso(finQuincenaAnterior()));
const pendientes = ref([]);
const cargandoPendientes = ref(true);
const generando = ref(null);

const estado = ref("");
const historial = ref(null);
const cargandoHistorial = ref(false);

const abiertaId = ref(null);
const recienGenerada = ref(null);
let solicitudPendientes = 0;
let solicitudHistorial = 0;

const fmt = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric" });
function fecha(texto) {
    if (!texto) return "—";
    const [a, m, d] = texto.slice(0, 10).split("-").map(Number);
    return fmt.format(new Date(a, m - 1, d));
}

function usarCorte(d) {
    hasta.value = iso(d);
    cargarPendientes();
}

async function cargarPendientes() {
    if (!hasta.value) return;
    const actual = ++solicitudPendientes;
    cargandoPendientes.value = true;
    try {
        const { data } = await http.get("/api/comisiones/liquidaciones/pendientes", { params: { hasta: hasta.value } });
        if (actual === solicitudPendientes) pendientes.value = data.empleados;
    } catch (e) {
        if (actual !== solicitudPendientes) return;
        pendientes.value = [];
        toastError(e?.response?.data?.message || "No se pudieron cargar las comisiones pendientes.");
    } finally {
        if (actual === solicitudPendientes) cargandoPendientes.value = false;
    }
}

async function cargarHistorial(page = 1) {
    const actual = ++solicitudHistorial;
    cargandoHistorial.value = true;
    try {
        const { data } = await http.get("/api/comisiones/liquidaciones", { params: { estado: estado.value || undefined, page } });
        if (actual === solicitudHistorial) historial.value = data;
    } catch (e) {
        if (actual !== solicitudHistorial) return;
        historial.value = null;
        toastError(e?.response?.data?.message || "No se pudo cargar el historial.");
    } finally {
        if (actual === solicitudHistorial) cargandoHistorial.value = false;
    }
}

function cambiarEstado(valor) {
    estado.value = valor;
    cargarHistorial(1);
}

async function generar(p) {
    generando.value = p.user_id;
    try {
        const { data } = await http.post("/api/comisiones/liquidaciones", { user_id: p.user_id, hasta: hasta.value });
        toastSuccess(`Liquidación de ${p.nombre} generada.`);
        recienGenerada.value = data;
        abiertaId.value = data.id;
        await cargarPendientes();
    } catch (e) {
        const errores = e?.response?.data?.errors ?? {};
        toastError(Object.values(errores)[0]?.[0] || e?.response?.data?.message || "No se pudo generar la liquidación.");
        await cargarPendientes();
    } finally {
        generando.value = null;
    }
}

function abrir(id) {
    recienGenerada.value = null;
    abiertaId.value = id;
}

function cerrar() {
    abiertaId.value = null;
    recienGenerada.value = null;
}

function huboCambio() {
    cargarPendientes();
    if (historial.value) cargarHistorial(historial.value.current_page ?? 1);
}

watch(tab, (t) => {
    if (t === "historial" && !historial.value) cargarHistorial(1);
});

onMounted(cargarPendientes);
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
.campo-auto {
    @apply w-auto;
}
.boton-primario {
    @apply flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50;
}
.boton-secundario {
    @apply flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50;
}
.boton-pagina {
    @apply flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-40;
}
</style>
