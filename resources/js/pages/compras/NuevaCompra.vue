<template>
    <div class="min-h-screen">
        <!-- CONTENT -->
        <main class="mx-auto max-w-7xl space-y-3 sm:space-y-3">
            <!-- RESUMEN SUPERIOR -->
            <section
                class="grid grid-cols-1 gap-3 sm:grid-cols-3 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm"
            >
                <!-- Mercancía -->
                <div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-400">
                                Artículos
                            </p>
                            <p class="text-2xl font-black text-slate-950">
                                {{ compra.totalArticulos }}
                            </p>
                        </div>
                        <div class="border-l border-slate-100 pl-3">
                            <p class="text-xs font-semibold text-slate-400">
                                Piezas
                            </p>
                            <p class="text-2xl font-black text-emerald-700">
                                {{ formatCantidad(compra.totalPiezas) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div>
                    <p class="text-xs font-bold uppercase text-emerald-600">
                        Total compra
                    </p>
                    <p
                        class="mt-2 font-mono text-xl font-black text-emerald-800 tabular-nums"
                    >
                        {{ compra.formatPrecio(compra.totalCompra) }}
                    </p>
                </div>
                <!-- botones -->
                <div class="flex flex-wrap items-center justify-end gap-5">
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
                        @click="panelDatos = true"
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
            </section>

            <!-- BUSCADOR PRINCIPAL -->
            <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <!-- Buscador -->
                    <div class="min-w-0 flex-1">
                        <CompraBuscador
                            :ref="compra.setBuscadorRef"
                            :formatPrecio="compra.formatPrecio"
                            :escaneoRapido="escaneoRapido"
                            @seleccionar="compra.seleccionarItem"
                        />
                    </div>

                    <!-- Botones -->
                    <div
                        class="inline-flex shrink-0 self-start rounded-xl border border-slate-200 bg-slate-50 p-1 sm:self-auto"
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
                </div>
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
                                @click="confirmarDesdePanel"
                                :disabled="compra.guardando"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Loader2
                                    v-if="compra.guardando"
                                    class="h-4 w-4 animate-spin"
                                />
                                <CheckCircle2 v-else class="h-4 w-4" />
                                Revisar y guardar compra
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
import { onMounted, ref } from "vue";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
import {
    Loader2,
    CheckCircle2,
    RotateCcw,
    Barcode,
    Keyboard,
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

function formatCantidad(value) {
    return new Intl.NumberFormat("es-MX", {
        maximumFractionDigits: 3,
    }).format(Number(value || 0));
}

async function confirmarDesdePanel() {
    if (!compra.validarCompra()) return;

    panelDatos.value = false;
    const guardada = await compra.confirmarGuardar();

    if (guardada === false) {
        panelDatos.value = true;
    }
}

onMounted(() => {
    const pedidos = consumirCompraDesdePedidos();
    if (pedidos.length) {
        compra.precargarDesdePedidos(pedidos);
    }
    compra.cargarProveedores();
});
</script>
