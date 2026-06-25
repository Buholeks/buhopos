<template>
    <div class="min-h-screen">
        <!-- CONTENT -->
        <main class="mx-auto max-w-7xl space-y-4 sm:space-y-5">
            <!-- RESUMEN SUPERIOR -->
            <section
                class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_260px_200px]"
            >
                <!-- Proveedor / pago -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                >
                                    <Building2 class="h-4 w-4" />
                                </div>

                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-bold uppercase tracking-wide text-slate-400"
                                    >
                                        Proveedor
                                    </p>
                                    <p
                                        class="truncate text-sm font-bold text-slate-900"
                                    >
                                        {{ proveedorNombre }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-2 flex flex-wrap items-center gap-1.5"
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"
                                >
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    {{ compra.form.fecha || "Sin fecha" }}
                                </span>

                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="
                                        ['credito', 'tarjeta_credito'].includes(
                                            compra.form.forma_pago,
                                        )
                                            ? 'bg-amber-50 text-amber-700'
                                            : 'bg-emerald-50 text-emerald-700'
                                    "
                                >
                                    <CreditCard class="h-3.5 w-3.5" />
                                    {{ formaPagoLabel }}
                                </span>

                                <span
                                    v-if="
                                        ['credito', 'tarjeta_credito'].includes(
                                            compra.form.forma_pago,
                                        )
                                    "
                                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700"
                                >
                                    Vence:
                                    {{
                                        compra.form.fecha_vencimiento ||
                                        "pendiente"
                                    }}
                                </span>

                                <span
                                    v-if="compra.form.folio"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"
                                >
                                    Folio: {{ compra.form.folio }}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="panelDatos = true"
                            class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                        >
                            Editar datos
                        </button>
                    </div>
                </div>

                <!-- Mercancía -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400">
                                Artículos
                            </p>
                            <p class="text-2xl font-black text-slate-950">
                                {{ compra.totalArticulos }}
                            </p>
                        </div>
                        <div class="border-l border-slate-100 pl-3">
                            <p class="text-[11px] font-semibold text-slate-400">
                                Piezas
                            </p>
                            <p class="text-2xl font-black text-emerald-700">
                                {{ formatCantidad(compra.totalPiezas) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"
                >
                    <p
                        class="text-xs font-bold uppercase tracking-wide text-emerald-600"
                    >
                        Total compra
                    </p>
                    <p
                        class="mt-2 font-mono text-xl font-black text-emerald-800 tabular-nums"
                    >
                        {{ compra.formatPrecio(compra.totalCompra) }}
                    </p>
                </div>
            </section>

            <!-- BUSCADOR PRINCIPAL -->
            <section
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <div
                        class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1"
                    >
                        <button
                            type="button"
                            @click="escaneoRapido = true"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition"
                            :class="
                                escaneoRapido
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                            "
                            title="Lector de barras"
                        >
                            <Barcode class="h-3.5 w-3.5" />
                            Lector
                        </button>
                        <button
                            type="button"
                            @click="escaneoRapido = false"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition"
                            :class="
                                !escaneoRapido
                                    ? 'bg-slate-900 text-white shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                            "
                            title="Captura manual"
                        >
                            <Keyboard class="h-3.5 w-3.5" />
                            Manual
                        </button>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            @click="compra.resetear"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Limpiar
                        </button>

                        <button
                            v-if="auth.can('compras.crear')"
                            type="button"
                            @click="compra.confirmarGuardar"
                            :disabled="
                                compra.guardando || compra.detalles.length === 0
                            "
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-xs font-black text-white shadow-sm shadow-emerald-200 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Loader2
                                v-if="compra.guardando"
                                class="h-4 w-4 animate-spin"
                            />
                            <CheckCircle2 v-else class="h-4 w-4" />
                            Guardar compra
                        </button>
                    </div>
                </div>

                <CompraBuscador
                    :ref="compra.setBuscadorRef"
                    :formatPrecio="compra.formatPrecio"
                    :escaneoRapido="escaneoRapido"
                    @seleccionar="compra.seleccionarItem"
                />
            </section>

            <!-- DETALLES -->
            <section
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
            >
                <CompraDetalles
                    :detalles="compra.detalles"
                    :total="compra.totalCompra"
                    :formatPrecio="compra.formatPrecio"
                    @recalcular="compra.recalcularLinea"
                    @quitar="compra.quitarDetalle"
                    @editar-imeis="compra.abrirEditarImeis"
                />
            </section>
        </main>
        <!-- PANEL LATERAL DATOS -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="panelDatos"
                    class="fixed inset-0 z-50 bg-slate-950/40"
                    @mousedown.self="panelDatos = false"
                >
                    <div
                        class="ml-auto flex h-full w-full max-w-md flex-col bg-white shadow-2xl"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                        >
                            <div>
                                <h3 class="text-base font-black text-slate-950">
                                    Datos de la compra
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Proveedor, pago, factura y notas.
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="panelDatos = false"
                                class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-5">
                            <CompraForm
                                :proveedores="compra.proveedores"
                                :form="compra.form"
                                :total="compra.totalCompra"
                                :cantidadArticulos="compra.totalArticulos"
                                :saldoFavorDisponible="
                                    compra.saldoFavorDisponible
                                "
                                :saldoFavorAplicado="compra.saldoFavorAplicado"
                                :restantePorPagar="compra.restantePorPagar"
                                :formatPrecio="compra.formatPrecio"
                                @update:form="
                                    ({ key, value }) =>
                                        compra.actualizarCampoForm(key, value)
                                "
                            />
                        </div>

                        <div class="border-t border-slate-100 p-5">
                            <button
                                type="button"
                                @click="panelDatos = false"
                                class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white hover:bg-slate-800"
                            >
                                Listo
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- MODALES -->
        <ModalCantidadCompra
            :mostrar="compra.modalCantidad.mostrar"
            :item="compra.modalCantidad.item"
            :pedidos-pendientes="compra.modalCantidad.pedidosPendientes"
            :cargando-pedidos="compra.modalCantidad.cargandoPedidos"
            @confirmar="compra.confirmarCantidad"
            @cancelar="compra.cancelarModal"
        />

        <ModalImeiCompra
            :mostrar="compra.modalImei.mostrar"
            :item="compra.modalImei.item"
            :cantidad="compra.modalImei.cantidad"
            :imeis-excluir="compra.todosLosImeis"
            @confirmar="compra.confirmarImeis"
            @atras="compra.volverACantidad"
        />

        <ModalEditarImeis
            :mostrar="compra.modalEditar.mostrar"
            :detalle="compra.modalEditar.detalle"
            :imeis-excluir="compra.imeisDeOtrosDetalles(compra.modalEditar.idx)"
            @guardar="compra.guardarImeisEditados"
            @cerrar="compra.cerrarEditarImeis"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
import {
    Loader2,
    CheckCircle2,
    RotateCcw,
    Barcode,
    Keyboard,
    Building2,
    CalendarDays,
    CreditCard,
    X,
} from "lucide-vue-next";

import { useCompraStore } from "@/stores/useCompraStore";
import { consumirCompraDesdePedidos } from "@/helpers/compraDesdePedidos";

import CompraForm from "@/components/compras/CompraForm.vue";
import CompraBuscador from "@/components/compras/CompraBuscador.vue";
import CompraDetalles from "@/components/compras/CompraDetalles.vue";
import ModalCantidadCompra from "@/components/compras/ModalCantidadCompra.vue";
import ModalImeiCompra from "@/components/compras/ModalImeiCompra.vue";
import ModalEditarImeis from "@/components/compras/ModalEditarImeis.vue";

const compra = useCompraStore();
const panelDatos = ref(false);
const escaneoRapido = ref(true);

const proveedorActual = computed(() => {
    return compra.proveedores.find(
        (p) => Number(p.id) === Number(compra.form.proveedor_id),
    );
});

const proveedorNombre = computed(() => {
    return (
        proveedorActual.value?.nombre_comercial || "Proveedor no seleccionado"
    );
});

const formaPagoLabel = computed(() => {
    const labels = {
        efectivo: "Efectivo",
        transferencia: "Transferencia",
        tarjeta_debito: "T. Débito",
        tarjeta_credito: "T. Crédito",
        credito: "Crédito",
    };
    return labels[compra.form.forma_pago] || "Sin pago";
});

function formatCantidad(value) {
    return new Intl.NumberFormat("es-MX", {
        maximumFractionDigits: 3,
    }).format(Number(value || 0));
}

onMounted(() => {
    const pedidos = consumirCompraDesdePedidos();
    if (pedidos.length) {
        compra.precargarDesdePedidos(pedidos);
    }
    compra.cargarProveedores();
});
</script>
