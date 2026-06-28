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
                        <Wallet class="h-5 w-5" />
                    </div>
                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900"
                        >
                            Corte de caja
                        </h1>
                        <p class="text-xs text-slate-500">
                            Arqueo, movimientos y cierre de turno
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                    <!-- ✅ NUEVO: Ir a historial de cortes -->
                    <button
                        type="button"
                        @click="irACortes"
                        class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg border border-slate-200 bg-white px-2.5 sm:px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                    >
                        <History class="h-4 w-4" />
                        <span class="hidden sm:inline">Cortes de caja</span>
                    </button>

                    <div
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm"
                    >
                        <span class="text-slate-400">Terminal</span>
                        <select
                            v-model="terminalSeleccionada"
                            class="bg-transparent font-semibold text-slate-800 outline-none"
                            @change="seleccionarTerminal(terminalSeleccionada)"
                        >
                                <option :value="terminalActual">
                                {{ terminalActual }}
                            </option>
                            <option
                                v-for="c in cajasAbiertas.filter(c => c.terminal !== terminalActual)"
                                :key="c.id"
                                :value="c.terminal"
                            >
                                {{ c.terminal }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="cargando"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm"
                    >
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Cargando…
                    </div>

                    <template v-else>
                        <div
                            v-if="corte?.estado === 'abierto'"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200"
                        >
                            <CircleDot class="h-4 w-4" />
                            Caja abierta
                        </div>

                        <div
                            v-else
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"
                        >
                            <CircleCheck class="h-4 w-4" />
                            Sin caja abierta
                        </div>

                        <button
                            v-if="!corte?.id && auth.can('caja.abrir')"
                            type="button"
                            @click="abrirCaja"
                            :disabled="abriendo"
                            class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-100 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="abriendo"
                                class="h-4 w-4 animate-spin"
                            />
                            <Plus v-else class="h-4 w-4" />
                            Abrir caja
                        </button>

                        <button
                            v-if="corte?.id && auth.can('caja.cerrar')"
                            type="button"
                            @click="modalCerrar = true"
                            :disabled="cerrandoCaja"
                            class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="cerrandoCaja"
                                class="h-4 w-4 animate-spin"
                            />
                            <Lock v-else class="h-4 w-4" />
                            Cerrar caja
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="mx-auto max-w-7xl px-3 sm:px-6 py-4 sm:py-6">
            <!-- SIN CAJA -->
            <div
                v-if="!cargando && !corte?.id"
                class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm"
            >
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                >
                    <WalletCards class="h-8 w-8" />
                </div>
                <p class="text-sm font-semibold text-slate-700">
                    No tienes una caja abierta
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    Abre caja para comenzar a registrar ventas y movimientos
                </p>

                <div
                    v-if="cajasAbiertas.length"
                    class="mx-auto mt-6 max-w-2xl rounded-xl border border-cyan-100 bg-cyan-50 p-4 text-left"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                Cajas abiertas en esta sucursal
                            </p>
                            <p class="text-xs text-slate-500">
                                Selecciona una para usarla desde esta PC.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-cyan-200 bg-white px-3 py-1.5 text-xs font-semibold text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                            :disabled="cargandoCajas"
                            @click="cargarCajasAbiertas"
                        >
                            Actualizar
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="c in cajasAbiertas"
                            :key="c.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-cyan-100 bg-white px-3 py-2"
                        >
                            <div>
                                <p class="text-sm font-bold text-slate-900">
                                    {{ c.terminal }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Abierta por {{ c.user?.name || "Usuario" }}
                                    · {{ formatFechaHora(c.fecha_apertura) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs font-semibold text-slate-700">
                                    {{ formatPrecio(c.esperado_efectivo) }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700"
                                    @click="seleccionarTerminal(c.terminal)"
                                >
                                    Usar esta caja
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    v-if="auth.can('caja.abrir')"
                    type="button"
                    @click="abrirCaja"
                    :disabled="abriendo"
                    class="mt-5 inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-100 disabled:opacity-50"
                >
                    <Loader2 v-if="abriendo" class="h-4 w-4 animate-spin" />
                    <Plus v-else class="h-4 w-4" />
                    Abrir caja {{ terminalActual }}
                </button>
            </div>

            <!-- CAJA ABIERTA -->
            <div v-else-if="corte?.id" class="space-y-6">
                <ResumenCards :corte="corte" />

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- MOVIMIENTOS -->
                    <MovimientosList
                        class="lg:col-span-1"
                        :movimientos="corte?.movimientos ?? []"
                        @nuevo="modalMov = true"
                    />
                    <!-- DESGLOSE -->
                    <div class="space-y-6 lg:col-span-2">
                        <DesgloseTable :corte="corte" />

                        <!-- ACCIONES RÁPIDAS -->
                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Acciones rápidas
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Recalcula desde servidor para ver cifras
                                        al momento.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="cargarActual(true)"
                                    :disabled="refrescando"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100 disabled:opacity-50"
                                >
                                    <RotateCw
                                        class="h-4 w-4"
                                        :class="
                                            refrescando ? 'animate-spin' : ''
                                        "
                                    />
                                    {{
                                        refrescando
                                            ? "Actualizando…"
                                            : "Actualizar"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PIE -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="text-sm text-slate-600">
                        Apertura:
                        <span class="ml-1 font-semibold text-slate-900">{{
                            formatFechaHora(corte.fecha_apertura)
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- ERRORES -->
            <div
                v-if="errorMsg"
                class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700"
            >
                {{ errorMsg }}
            </div>
        </div>

        <!-- MODALES -->
        <NuevoMovimientoModal
            v-if="modalMov"
            :open="modalMov"
            @close="modalMov = false"
            @submit="guardarMovimiento"
            :loading="guardandoMov"
        />

        <CerrarCajaModal
            v-if="modalCerrar"
            :open="modalCerrar"
            :corte="corte"
            @close="modalCerrar = false"
            @submit="cerrarCaja"
            @update-corte="(nuevo) => (corte.value = nuevo)"
            :loading="cerrandoCaja"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import http from "@/lib/http";
import Swal from "sweetalert2";
import { toastSuccess } from "@/lib/alert";

import ResumenCards from "@/components/caja/ResumenCards.vue";
import MovimientosList from "@/components/caja/MovimientosList.vue";
import DesgloseTable from "@/components/caja/DesgloseTable.vue";
import NuevoMovimientoModal from "@/components/caja/NuevoMovimientoModal.vue";
import CerrarCajaModal from "@/components/caja/CerrarCajaModal.vue";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();

import {
    Wallet,
    WalletCards,
    Plus,
    Lock,
    RotateCw,
    Loader2,
    CircleDot,
    CircleCheck,
    History, // ✅ NUEVO
} from "lucide-vue-next";

const router = useRouter();

// ✅ Ruta a historial/listado de cortes
function irACortes() {
    // Opción A: por name (recomendado)
    router.push({ name: "cortes-caja" });

    // Opción B: por path (si no tienes name)
    // router.push("/cortes-caja");
}

const cargando = ref(true);
const refrescando = ref(false);
const errorMsg = ref("");

const corte = ref(null);
const cajasAbiertas = ref([]);
const cargandoCajas = ref(false);
const terminalActual = ref(obtenerTerminal());
const terminalSeleccionada = ref(terminalActual.value);

const abriendo = ref(false);
const modalMov = ref(false);
const modalCerrar = ref(false);
const guardandoMov = ref(false);
const cerrandoCaja = ref(false);

onMounted(async () => {
    await cargarCajasAbiertas();
    await cargarActual(false);
});

function obtenerTerminal() {
    return localStorage.getItem("terminal") || "POS-01";
}

function guardarTerminal(terminal) {
    const normalizada = String(terminal || "POS-01").trim() || "POS-01";
    localStorage.setItem("terminal", normalizada);
    terminalActual.value = normalizada;
    terminalSeleccionada.value = normalizada;

    if (window.axios) {
        window.axios.defaults.headers.common["X-Terminal"] = normalizada;
    }
}

function formatFechaHora(f) {
    if (!f) return "—";
    const d = new Date(f);
    return d.toLocaleString("es-MX", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

function formatPrecio(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}

async function cargarCajasAbiertas() {
    cargandoCajas.value = true;

    try {
        const { data } = await http.get("/api/cortes-caja/abiertas");
        cajasAbiertas.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        console.error(e);
        cajasAbiertas.value = [];
    } finally {
        cargandoCajas.value = false;
    }
}

async function seleccionarTerminal(terminal) {
    if (!terminal) return;

    guardarTerminal(terminal);
    await cargarActual(false);
    await cargarCajasAbiertas();
    toastSuccess(`Terminal activa: ${terminalActual.value}`);
}

async function cargarActual(silent) {
    if (!silent) cargando.value = true;
    refrescando.value = !!silent;
    errorMsg.value = "";

    try {
        const { data } = await http.get("/api/cortes-caja/actual");
        corte.value = data && data.id ? data : null;
    } catch (e) {
        console.error(e);
        errorMsg.value = "No se pudo cargar el corte actual.";
        corte.value = null;
    } finally {
        cargando.value = false;
        refrescando.value = false;
    }
}

async function abrirCaja() {
    abriendo.value = true;
    errorMsg.value = "";

    try {
        await http.post("/api/cortes-caja/abrir");
        toastSuccess("Caja abierta");
        await cargarCajasAbiertas();
        await cargarActual(false);
    } catch (e) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: e.response?.data?.message ?? "Error al abrir caja",
        });
    } finally {
        abriendo.value = false;
    }
}

async function guardarMovimiento(payload) {
    if (!corte.value?.id) return;

    guardandoMov.value = true;
    errorMsg.value = "";

    try {
        await http.post(
            `/api/cortes-caja/${corte.value.id}/movimiento`,
            payload,
        );
        toastSuccess("Movimiento registrado");
        modalMov.value = false;
        await cargarActual(true);
    } catch (e) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: e.response?.data?.message ?? "Error al guardar movimiento",
        });
    } finally {
        guardandoMov.value = false;
    }
}

async function cerrarCaja(formCierre) {
    if (!corte.value?.id) return;

    const r = await Swal.fire({
        title: "¿Cerrar caja?",
        html: `<p style="font-size:14px;color:#475569;">
      Una vez cerrada no podrás reabrir esta caja.
    </p>`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#dc2626",
        confirmButtonText: "Cerrar caja",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });
    if (!r.isConfirmed) return;

    cerrandoCaja.value = true;
    try {
        await http.post(
            `/api/cortes-caja/${corte.value.id}/cerrar`,
            formCierre,
        );
        await Swal.fire({
            icon: "success",
            title: "¡Caja cerrada!",
            confirmButtonColor: "#16a34a",
        });
        modalCerrar.value = false;
        await cargarCajasAbiertas();
        await cargarActual(false);
    } catch (e) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: e.response?.data?.message ?? "Error al cerrar caja",
        });
    } finally {
        cerrandoCaja.value = false;
    }
}
</script>
