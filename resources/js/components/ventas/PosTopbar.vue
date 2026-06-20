<template>
    <div
        class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur"
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-3 py-2 sm:px-6">
            <!-- Fila superior -->
            <div
                class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between"
            >
                <!-- Izquierda -->
                <div class="flex min-w-0 items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600"
                    >
                        <ShoppingCart class="h-4 w-4" />
                    </div>

                    <div class="min-w-0">
    <div class="flex flex-wrap items-center gap-1.5">
        <h1 class="truncate text-base font-semibold text-slate-900">
            Nueva venta
        </h1>

        <!-- Estado de caja -->
        <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
            :class="
                cajaAbierta
                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                    : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
            "
            :title="
                cajaAbierta
                    ? 'Caja activa'
                    : 'Debes abrir caja para operar'
            "
        >
            <span
                class="h-1.5 w-1.5 rounded-full"
                :class="
                    cajaAbierta
                        ? 'bg-emerald-500'
                        : 'bg-amber-500'
                "
            />
            {{ cajaAbierta ? "Caja abierta" : "Sin caja abierta" }}
        </span>

        <!-- Terminal -->
        <span
            v-if="terminal"
            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600 ring-1 ring-slate-200"
        >
            {{ terminal }}
        </span>
    </div>

    <p class="hidden">
        Operaciones rápidas para la venta y la caja
    </p>
</div>
                </div>

                <!-- Derecha -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <div
                        v-if="typeof detallesCount === 'number'"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600"
                    >
                        <Package class="h-3.5 w-3.5 text-slate-400" />
                        <span class="font-medium text-slate-700">
                            {{ detallesCount }}
                        </span>
                        <span
                            >artículo{{ detallesCount === 1 ? "" : "s" }}</span
                        >
                    </div>

                    <div
                        v-if="showTotal && total != null"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs"
                    >
                        <BadgeDollarSign class="h-3.5 w-3.5 text-emerald-600" />
                        <span class="text-slate-500">Total</span>
                        <span class="font-mono font-semibold text-emerald-700">
                            {{ formatPrecio(total) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Fila de acciones -->
            <div class="flex flex-wrap items-center gap-1.5">
                <!-- Caja -->
                <button
                    v-if="!cajaAbierta"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    @click="$emit('abrirCaja')"
                >
                    <Wallet class="h-3.5 w-3.5 text-emerald-600" />
                    Abrir caja
                </button>

                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    @click="$emit('modal-mov')"
                >
                    <ArrowLeftRight class="h-3.5 w-3.5 text-slate-500" />
                    Mov. caja
                </button>

                <!-- Venta -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disableAccionesVenta"
                    @click="$emit('enEspera')"
                >
                    <PauseCircle class="h-3.5 w-3.5 text-amber-600" />
                    En espera
                    <span class="hidden text-[11px] text-slate-400 md:inline">
                        Alt+W
                    </span>
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    @click="$emit('recuperar')"
                >
                    <FolderOpen class="h-3.5 w-3.5 text-sky-600" />
                    Recuperar
                    <span class="hidden text-[11px] text-slate-400 md:inline">
                        Alt+R
                    </span>
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disableReimprimirUltima"
                    title="Reimprimir ultima venta"
                    @click="$emit('reimprimirUltima')"
                >
                    <Printer class="h-3.5 w-3.5 text-slate-600" />
                    Reimprimir
                </button>

                <button
                    v-if="auth.can('ventas.descuento')"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disableAccionesVenta"
                    @click="$emit('descuentoGlobal')"
                >
                    <Percent class="h-3.5 w-3.5 text-violet-600" />
                    Desc. global
                    <span class="hidden text-[11px] text-slate-400 md:inline">
                        Alt+D
                    </span>
                </button>

                <div class="mx-1 hidden h-5 w-px bg-slate-200 lg:block" />

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="guardando"
                    @click="$emit('reset')"
                >
                    <RotateCcw class="h-3.5 w-3.5 text-slate-500" />
                    Nueva venta
                    <span class="hidden text-[11px] text-slate-400 md:inline">
                        Esc
                    </span>
                </button>

                <VentaClienteSelector
                    :cliente="cliente"
                    @select="$emit('selectCliente', $event)"
                    @clear="$emit('clearCliente')"
                />

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disableGuardar || guardando"
                    @click="$emit('guardar')"
                >
                    <Loader2 v-if="guardando" class="h-3.5 w-3.5 animate-spin" />
                    <Receipt v-else class="h-3.5 w-3.5" />
                    {{ guardando ? "Procesando..." : "Cobrar" }}
                    <span class="hidden text-[11px] text-emerald-100 md:inline">
                        Ctrl+Enter
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {
    ShoppingCart,
    Wallet,
    ArrowLeftRight,
    PauseCircle,
    FolderOpen,
    Percent,
    RotateCcw,
    Receipt,
    Loader2,
    Package,
    BadgeDollarSign,
    Printer,
} from "lucide-vue-next";
import VentaClienteSelector from "@/components/ventas/VentaClienteSelector.vue";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();

const props = defineProps({
    guardando: { type: Boolean, default: false },
    disableGuardar: { type: Boolean, default: false },

    cajaAbierta: { type: Boolean, default: false },
    terminal: { type: String, default: "" },

    detallesCount: { type: Number, default: 0 },

    total: { type: [Number, String], default: 0 },
    showTotal: { type: Boolean, default: false },

    disableAccionesVenta: { type: Boolean, default: false },
    disableReimprimirUltima: { type: Boolean, default: true },
    cliente: { type: Object, default: null },
});

defineEmits([
    "abrirCaja",
    "modal-mov",
    "enEspera",
    "recuperar",
    "reimprimirUltima",
    "descuentoGlobal",
    "reset",
    "guardar",
    "selectCliente",
    "clearCliente",
]);

function formatPrecio(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}
</script>
