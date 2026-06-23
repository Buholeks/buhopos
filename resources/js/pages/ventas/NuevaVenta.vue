<template>
    <div ref="root">
        <PosTopbar
            :guardando="guardando"
            :disableGuardar="guardando || detalles.length === 0"
            :cajaAbierta="!!corteActual?.id"
            :terminal="corteActual?.terminal || 'Sin caja'"
            :detallesCount="detalles.length"
            :total="total"
            :showTotal="false"
            :disableAccionesVenta="detalles.length === 0"
            :disableReimprimirUltima="!ultimaVentaDisponible || imprimiendoUltima"
            :imprimiendoUltima="imprimiendoUltima"
            :cliente="cliente"
            @abrirCaja="abrirCaja"
            @modal-mov="modalMov = true"
            @enEspera="ponerVentaEnEspera"
            @recuperar="abrirModalRecuperar"
            @reimprimirUltima="reimprimirUltimaVenta"
            @descuentoGlobal="abrirModalDescuento"
            @reset="resetearTodo"
            @guardar="abrirModalCobro"
            @select-cliente="store.setCliente"
            @clear-cliente="store.clearCliente"
        />
        <NuevoMovimientoModal
            v-if="modalMov"
            :open="modalMov"
            @close="modalMov = false"
            @submit="guardarMovimiento"
            :loading="guardandoMov"
        />

        <ModalRecuperarVenta
    :open="modalRecuperar"
    :items="ventasEnEspera"
    :formatPrecio="formatPrecio"
    @close="modalRecuperar = false"
    @recover="recuperarVenta"
    @delete="eliminarVentaEnEspera"
/>

        <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:gap-4 px-3 sm:px-6 py-3 sm:py-6">
            <section
                v-if="pedidosDisponibles.length > 0"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-emerald-950">
                            Pedidos disponibles del cliente
                        </h2>
                        <p class="text-xs text-emerald-700">
                            Carga los articulos disponibles directo al carrito.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                    <div
                        v-for="pedido in pedidosDisponibles"
                        :key="pedido.id"
                        class="rounded-xl border border-emerald-100 bg-white p-3"
                    >
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="font-semibold text-slate-900">{{ pedido.folio }}</p>
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">
                                {{ formatPrecio(pedido.anticipo || 0) }} anticipo
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="detallePedido in detallesDisponiblesPedido(pedido)"
                                :key="detallePedido.id"
                                class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">
                                        {{ detallePedido.cantidad }} x {{ detallePedido.descripcion }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ formatPrecio(detallePedido.precio_acordado || 0) }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                    :disabled="detallePedido.estado === 'entregado' || detalles.some((d) => Number(d.pedido_detalle_id) === Number(detallePedido.id))"
                                    :title="detalles.some((d) => Number(d.pedido_detalle_id) === Number(detallePedido.id)) ? 'Ya en la venta' : ''"
                                    @click="agregarPedidoAlCarrito(pedido, detallePedido)"
                                >
                                    {{ detalles.some((d) => Number(d.pedido_detalle_id) === Number(detallePedido.id)) ? '✓ Agregado' : 'Agregar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <PosSearch
                ref="searchRef"
                v-model="busqueda"
                :resultados="resultados"
                :buscando="buscando"
                :dropdown="dropdown"
                :cursor="cursor"
                :formatPrecio="formatPrecio"
                :disabled="sinCajaAbierta"
                @input="onBusquedaInput"
                @enter="buscarProductos"
                @moveCursor="moverCursor"
                @selectCursor="seleccionarCursor"
                @close="cerrarDropdown"
                @clear="limpiarBusqueda"
                @selectItem="seleccionarItem"
                @hoverItem="(i) => (cursor = i)"
                @checkStock="abrirExistenciasSucursal"
            />

            <PosTable
                :detalles="detalles"
                :selectedIdx="selectedIdx"
                :popoverIdx="popoverIdx"
                :popoverStyle="popoverStyle"
                :hayExcedido="hayExcedido"
                :formatPrecio="formatPrecio"
                :subtotal="subtotal"
                :total="total"
                :formDescuento="form.descuento"
                :preciosValidos="preciosValidos"
                @selectRow="selectRow"
                @recalcularLinea="recalcularLinea"
                @quitarDetalle="quitarDetalle"
                @abrirPopoverPrecio="abrirPopoverPrecio"
                @cerrarPopover="cerrarPopover"
                @aplicarPrecio="aplicarPrecio"
                @guardarPrecioAnterior="guardarPrecioAnterior"
                @precioBlur="onPrecioBlur"
                @update:descuento="(v) => (form.descuento = v)"
            />
        </div>

        <ModalDatosVenta
            v-if="modalDatosVenta"
            :cliente="cliente"
            :vendedor-id="cobro.vendedor_id"
            :vendedor="cobro.vendedor"
            @select-cliente="store.setCliente"
            @select-vendedor="store.setVendedor"
            @cancel="modalDatosVenta = false"
            @confirm="continuarAlCobro"
        />

        <ModalCobroVenta
            v-if="cobro.visible"
            :vendedor-id="cobro.vendedor_id"
            :vendedor="cobro.vendedor"
            :forma-pago="cobro.forma_pago"
            :monto-recibido="cobro.monto_recibido"
            :notas="cobro.notas"
            :subtotal="subtotal"
            :descuento="Number(form.descuento || 0)"
            :total="total"
            :total-a-cobrar="totalACobrar"
            :saldo-disponible="Number(cobro.saldo_disponible || 0)"
            :saldo-aplicado="Number(cobro.saldo_aplicado || 0)"
            :saldo-bloqueado="saldoBloqueado"
            :cambio="cambio"
            :pago-insuficiente="pagoInsuficiente"
            :cliente="cliente"
            :disableConfirm="guardando"
            :formatPrecio="formatPrecio"
            @update:formaPago="(v) => (cobro.forma_pago = v)"
            @update:montoRecibido="(v) => (cobro.monto_recibido = v)"
            @update:saldoAplicado="actualizarSaldoAplicado"
            @update:notas="(v) => (cobro.notas = v)"
            @cancel="store.cerrarCobro"
            @confirm="guardarVentaFinal"
        />

        <ModalSerie
            v-if="modalSerie.visible"
            :productoId="modalSerie.productoId"
            :varianteId="modalSerie.varianteId"
            :nombreProducto="modalSerie.nombre"
            @cancel="modalSerie.visible = false"
            @confirm="onSerieSeleccionada"
        />

        <ModalPrecioManual
            v-if="modalPrecioManual.visible"
            :precioNuevo="modalPrecioManual.precioNuevo"
            v-model:motivo="modalPrecioManual.motivo"
            :formatPrecio="formatPrecio"
            @cancel="cancelarPrecioManual(modalPrecioManual.idx)"
            @confirm="confirmarPrecioManual"
        />

        <div
            v-if="modalExistencias.visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
            @click.self="cerrarExistenciasSucursal"
        >
            <div class="w-full max-w-xl rounded-xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-bold text-slate-900">
                            Existencias por sucursal
                        </h2>
                        <p class="mt-1 truncate text-sm text-slate-500">
                            {{ modalExistencias.producto?.nombre }}
                            <span v-if="modalExistencias.producto?.variante">
                                - {{ modalExistencias.producto.variante }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        @click="cerrarExistenciasSucursal"
                    >
                        <span class="sr-only">Cerrar</span>
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                    <div
                        v-if="modalExistencias.cargando"
                        class="py-8 text-center text-sm text-slate-500"
                    >
                        Consultando existencias...
                    </div>

                    <div
                        v-else-if="modalExistencias.error"
                        class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        {{ modalExistencias.error }}
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="item in modalExistencias.sucursales"
                            :key="item.sucursal_id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ item.sucursal }}
                                    </p>
                                    <span
                                        v-if="item.exhibido"
                                        class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-bold text-sky-700"
                                    >
                                        Exhibido
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Stock {{ formatoCantidad(item.stock) }}
                                    <span v-if="Number(item.reservado) > 0">
                                        · Reservado {{ formatoCantidad(item.reservado) }}
                                    </span>
                                </p>
                            </div>

                            <span
                                class="shrink-0 rounded-full px-3 py-1 text-sm font-bold"
                                :class="
                                    Number(item.disponible) <= 0
                                        ? 'bg-red-100 text-red-600'
                                        : Number(item.disponible) <= 5
                                          ? 'bg-amber-100 text-amber-700'
                                          : 'bg-emerald-100 text-emerald-700'
                                "
                            >
                                {{ formatoCantidad(item.disponible) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {
    ref,
    reactive,
    computed,
    onMounted,
    onBeforeUnmount,
    nextTick,
    watch,
} from "vue";
import { storeToRefs } from "pinia";
import Swal from "sweetalert2";
import http from "@/lib/http";
import { toastSuccess, toastError, toastWarning } from "@/lib/alert";

import { useVentaPosStore } from "@/stores/VentaPos";

import PosTopbar from "@/components/ventas/PosTopbar.vue";
import PosSearch from "@/components/ventas/PosSearch.vue";
import PosTable from "@/components/ventas/PosTable.vue";
import ModalPrecioManual from "@/components/ventas/ModalPrecioManual.vue";
import ModalSerie from "@/components/ventas/ModalSerie.vue";
import NuevoMovimientoModal from "@/components/caja/NuevoMovimientoModal.vue";
import ModalDatosVenta from "@/components/ventas/ModalDatosVenta.vue";
import ModalCobroVenta from "@/components/ventas/ModalCobroVenta.vue";

import { useAuthStore } from "@/stores/auth";
import ModalRecuperarVenta from "@/components/ventas/ModalRecuperarVenta.vue";

import {
    getVentasEnEspera,
    saveVentaEnEspera,
    removeVentaEnEspera,
} from "@/helpers/ventasEnEspera";
import { crearTicketVenta } from "@/helpers/tickets/ticketVenta";
import { imprimirTicketVenta } from "@/helpers/tickets/imprimirTicketVenta";
import { conectar, obtenerImpresoraTicket } from "@/helpers/qzTray";
import { X } from "lucide-vue-next";

const authStore = useAuthStore();

const ventasEnEspera = ref([]);
const modalRecuperar = ref(false);
const pedidosDisponibles = ref([]);
const tieneProductosPendientes = ref(false);

const empresaId = computed(() => authStore.user?.empresa_id ?? null);
const sucursalId = computed(() => authStore.user?.sucursal_id ?? null);
const userId = computed(() => authStore.user?.id ?? null);

const sinCajaAbierta = computed(() => !corteActual.value?.id);
const idsProductosPendientesDisponibles = computed(() =>
    new Set(
        pedidosDisponibles.value.flatMap((pedido) =>
            (pedido.detalles ?? [])
                .filter((detalle) => ["disponible", "reservado"].includes(detalle.estado))
                .map((detalle) => Number(detalle.id)),
        ),
    ),
);
const ventaSoloProductosPendientes = computed(() =>
    detalles.value.length > 0 &&
    detalles.value.every((detalle) =>
        idsProductosPendientesDisponibles.value.has(Number(detalle.pedido_detalle_id)),
    ),
);
const saldoBloqueado = computed(() =>
    tieneProductosPendientes.value && !ventaSoloProductosPendientes.value,
);
// Suma del anticipo de los pedidos que están representados en el carrito actual
const anticipoPedidosEnCarrito = computed(() => {
    if (!ventaSoloProductosPendientes.value) return 0;
    const pedidoIds = new Set(detalles.value.map((d) => Number(d.pedido_id)).filter(Boolean));
    return pedidosDisponibles.value
        .filter((p) => pedidoIds.has(Number(p.id)))
        .reduce((sum, p) => sum + Number(p.anticipo || 0), 0);
});


const modalMov = ref(false);
const guardandoMov = ref(false);
const corteActual = ref(null);
const abriendoCaja = ref(false);
const imprimiendoUltima = ref(false);

const store = useVentaPosStore();
const {
    guardando,
    cliente,
    form,
    cobro,
    detalles,
    subtotal,
    total,
    totalACobrar,
    cambio,
    pagoInsuficiente,
    hayExcedido,
    ultimaVenta,
} = storeToRefs(store);
const ultimaVentaDisponible = computed(() => {
    if (!ultimaVenta.value) return false;

    return (
        Number(ultimaVenta.value.empresa_id) === Number(empresaId.value) &&
        Number(ultimaVenta.value.sucursal_id) === Number(sucursalId.value)
    );
});

const root = ref(null);
const searchRef = ref(null);
const modalDatosVenta = ref(false);

const busqueda = ref("");
const resultados = ref([]);
const buscando = ref(false);
const dropdown = ref(false);
const cursor = ref(0);
const modalExistencias = reactive({
    visible: false,
    cargando: false,
    error: "",
    producto: null,
    sucursales: [],
});

watch(
    () => cliente.value?.id,
    () => cargarSaldoCliente(),
);

watch(
    () => total.value,
    () => actualizarSaldoAplicado(cobro.value.saldo_aplicado),
);

watch(
    saldoBloqueado,
    (bloqueado) => {
        if (bloqueado) cobro.value.saldo_aplicado = 0;
    },
);

// Cuando cambian qué pedidos están en el carrito, recalcular saldo sugerido
watch(anticipoPedidosEnCarrito, () => aplicarSaldoAutomatico());

// ── Fila seleccionada ─────────────────────────────────────────────────────────
const selectedIdx = ref(null);
function selectRow(idx) {
    selectedIdx.value = idx;
}

// ── Precios ───────────────────────────────────────────────────────────────────
const CLAVES_PRECIO = [
    "precio_venta",
    "precio1",
    "precio2",
    "precio3",
    "precio4",
    "precio5",
];
const ETIQUETAS_PRECIO = {
    precio_venta: "P.Venta",
    precio1: "P1",
    precio2: "P2",
    precio3: "P3",
    precio4: "P4",
    precio5: "P5",
};

// ── Popover precio ────────────────────────────────────────────────────────────
const popoverIdx = ref(null);
const popoverStyle = ref({});

// ── Modal precio manual ───────────────────────────────────────────────────────
const modalPrecioManual = reactive({
    visible: false,
    idx: null,
    motivo: "",
    precioNuevo: null,
});

// ── Modal serie/IMEI ──────────────────────────────────────────────────────────
const modalSerie = reactive({
    visible: false,
    productoId: null,
    varianteId: null,
    nombre: "",
    itemPendiente: null,
});

async function cargarCorteActual() {
    try {
        const { data } = await http.get("/api/cortes-caja/actual");
        corteActual.value = data?.id ? data : null;
    } catch (e) {
        corteActual.value = null;
    }
}

async function abrirCaja() {
    abriendoCaja.value = true;

    try {
        const { data } = await http.get("/api/cortes-caja/abiertas");
        const cajas = Array.isArray(data?.data) ? data.data : [];

        if (cajas.length > 0) {
            const inputOptions = Object.fromEntries(
                cajas.map((c) => [
                    c.terminal,
                    `${c.terminal} - ${c.user?.name || "Usuario"} - ${formatPrecio(c.esperado_efectivo)}`,
                ]),
            );

            const seleccion = await Swal.fire({
                title: "Seleccionar caja",
                text: "Hay cajas abiertas en esta sucursal. Puedes usar una existente o abrir una nueva.",
                input: "select",
                inputOptions,
                inputValue: localStorage.getItem("terminal") || cajas[0].terminal,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: "Usar caja",
                denyButtonText: "Abrir nueva",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#0891b2",
                denyButtonColor: "#059669",
                reverseButtons: true,
            });

            if (seleccion.isConfirmed && seleccion.value) {
                seleccionarTerminalCaja(seleccion.value);
                await cargarCorteActual();
                toastSuccess(`Caja seleccionada: ${seleccion.value}`);
                return;
            }

            if (!seleccion.isDenied) return;
        }

        const terminal = localStorage.getItem("terminal") || "POS-01";
        const confirmar = await Swal.fire({
            title: "Abrir nueva caja",
            text: `Se abrira una nueva caja para ${terminal}.`,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Abrir caja",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#059669",
            reverseButtons: true,
        });

        if (!confirmar.isConfirmed) return;

        await http.post("/api/cortes-caja/abrir");
        toastSuccess("Caja abierta");
        await cargarCorteActual();
    } catch (e) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: e.response?.data?.message ?? "Error al abrir caja",
        });
    } finally {
        abriendoCaja.value = false;
    }
}

function seleccionarTerminalCaja(terminal) {
    const normalizada = String(terminal || "POS-01").trim() || "POS-01";
    localStorage.setItem("terminal", normalizada);

    if (window.axios) {
        window.axios.defaults.headers.common["X-Terminal"] = normalizada;
    }
}

// async function cargarCorteActual() {
//     try {
//         const { data } = await http.get("/api/cortes-caja/actual");
//         corteActual.value = data ?? null;
//     } catch (e) {
//         corteActual.value = null;
//     }
// }

function cargarVentasEnEspera() {
    if (!empresaId.value || !sucursalId.value || !userId.value) {
        ventasEnEspera.value = [];
        return;
    }

    ventasEnEspera.value = getVentasEnEspera(
        empresaId.value,
        sucursalId.value,
        userId.value
    );
}

function buildVentaEnEsperaPayload() {
    return {
        id: `tmp_${Date.now()}`,
        referencia:
            cliente.value?.nombre ||
            cliente.value?.name ||
            `Venta ${new Date().toLocaleTimeString("es-MX", {
                hour: "2-digit",
                minute: "2-digit",
            })}`,
        created_at: new Date().toISOString(),
        total: Number(total.value || 0),

        cliente: cliente.value ? JSON.parse(JSON.stringify(cliente.value)) : null,

        form: JSON.parse(
            JSON.stringify(form.value ?? form)
        ),

        cobro: JSON.parse(
            JSON.stringify(cobro.value ?? cobro)
        ),

        detalles: JSON.parse(JSON.stringify(detalles.value)),
    };
}

function ponerVentaEnEspera() {
    if (sinCajaAbierta.value) {
        toastWarning("Debes abrir caja antes de operar");
        return;
    }

    if (!detalles.value.length) {
        toastWarning("No hay productos para enviar a espera");
        return;
    }

    const venta = buildVentaEnEsperaPayload();

    saveVentaEnEspera(
        empresaId.value,
        sucursalId.value,
        userId.value,
        venta
    );

    cargarVentasEnEspera();
    resetearTodo();

    toastSuccess("Venta enviada a espera");
}

function abrirModalRecuperar() {
    cargarVentasEnEspera();

    if (!ventasEnEspera.value.length) {
        toastWarning("No hay ventas en espera");
        return;
    }

    modalRecuperar.value = true;
}

function recuperarVenta(venta) {
    if (!venta) return;

    resetearTodo();

    if (venta.cliente) {
        store.setCliente(venta.cliente);
    }

    if (venta.form) {
        Object.assign(form.value ?? form, venta.form);
    }

    if (venta.cobro) {
        Object.assign(cobro.value ?? cobro, venta.cobro);
    }

    detalles.value = Array.isArray(venta.detalles)
        ? JSON.parse(JSON.stringify(venta.detalles))
        : [];

    removeVentaEnEspera(
        empresaId.value,
        sucursalId.value,
        userId.value,
        venta.id
    );

    cargarVentasEnEspera();
    modalRecuperar.value = false;

    toastSuccess("Venta recuperada");
    nextTick(() => searchRef.value?.focus?.());
}

function eliminarVentaEnEspera(id) {
    removeVentaEnEspera(
        empresaId.value,
        sucursalId.value,
        userId.value,
        id
    );

    cargarVentasEnEspera();
    toastSuccess("Venta en espera eliminada");
}


async function guardarMovimiento(payload) {
    if (!corteActual.value?.id) {
        toastError("No hay caja abierta");
        return;
    }

    guardandoMov.value = true;

    try {
        await http.post(
            `/api/cortes-caja/${corteActual.value.id}/movimiento`,
            payload,
        );

        toastSuccess("Movimiento registrado");
        modalMov.value = false;
        await cargarCorteActual();
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

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatPrecio(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}

function formatoCantidad(v) {
    const n = Number(v ?? 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

async function abrirExistenciasSucursal(item) {
    if (!item?.producto_id) return;

    modalExistencias.visible = true;
    modalExistencias.cargando = true;
    modalExistencias.error = "";
    modalExistencias.producto = {
        nombre: item.nombre,
        variante: item.nombre_variante,
    };
    modalExistencias.sucursales = [];

    try {
        const { data } = await http.get("/api/ventas/existencias", {
            params: {
                producto_id: item.producto_id,
                variante_id: item.id ?? null,
            },
        });

        modalExistencias.producto = data?.producto ?? modalExistencias.producto;
        modalExistencias.sucursales = Array.isArray(data?.sucursales)
            ? data.sucursales
            : [];
    } catch (e) {
        modalExistencias.error =
            e.response?.data?.message ?? "No se pudieron consultar las existencias.";
    } finally {
        modalExistencias.cargando = false;
    }
}

function cerrarExistenciasSucursal() {
    modalExistencias.visible = false;
    modalExistencias.cargando = false;
    modalExistencias.error = "";
    modalExistencias.producto = null;
    modalExistencias.sucursales = [];
}

// ── Precio manual ─────────────────────────────────────────────────────────────
function guardarPrecioAnterior(idx) {
    const d = detalles.value[idx];
    if (d) d._precioAnterior = Number(d.precio_venta);
}

function onPrecioBlur(idx, event) {
    const d = detalles.value[idx];
    if (!d) return;

    const nuevo = Number(event.target.value);
    const anterior = Number(d._precioAnterior ?? d.precio_venta);
    if (nuevo === anterior) return;

    event.target.value = anterior;

    modalPrecioManual.idx = idx;
    modalPrecioManual.precioNuevo = nuevo;
    modalPrecioManual.motivo = "";
    modalPrecioManual.visible = true;
}

function confirmarPrecioManual() {
    const d = detalles.value[modalPrecioManual.idx];
    if (!d || !modalPrecioManual.motivo.trim()) return;

    d.precio_venta = modalPrecioManual.precioNuevo;
    d.precio_lista_sel = null;
    d.motivo_precio = modalPrecioManual.motivo.trim();
    store.normalizeLinea(d);

    modalPrecioManual.visible = false;
}

function cancelarPrecioManual(idx) {
    const d = detalles.value[idx];
    if (d && d._precioAnterior !== undefined) {
        d.precio_venta = d._precioAnterior;
    }
    modalPrecioManual.visible = false;
}

// ── Lista de precios ──────────────────────────────────────────────────────────
function preciosValidos(det) {
    return CLAVES_PRECIO.filter(
        (c) => det[c] != null && Number(det[c]) > 0,
    ).map((c) => ({
        clave: ETIQUETAS_PRECIO[c],
        valor: Number(det[c]),
        key: c,
    }));
}

// ── Popover ───────────────────────────────────────────────────────────────────
function abrirPopoverPrecio(idx, event) {
    if (popoverIdx.value === idx) {
        popoverIdx.value = null;
        return;
    }

    const el = event.currentTarget;
    const spaceRight = window.innerWidth - el.getBoundingClientRect().left;

    popoverStyle.value =
        spaceRight < 220
            ? { top: `${el.offsetHeight + 6}px`, right: "0", left: "auto" }
            : { top: `${el.offsetHeight + 6}px`, left: "0", right: "auto" };

    popoverIdx.value = idx;
}

function cerrarPopover() {
    popoverIdx.value = null;
}

function aplicarPrecio(idx, p) {
    const d = detalles.value[idx];
    if (!d) return;

    d.precio_venta = p.valor;
    d.precio_lista_sel = p.clave;
    d.motivo_precio = null;
    store.normalizeLinea(d);
    popoverIdx.value = null;
}

// ── Búsqueda ──────────────────────────────────────────────────────────────────
async function intentarBuscarImei(q) {
    if (!/^\d{10,20}$/.test(q)) return false;

    try {
        const { data } = await http.get("/api/series/buscar-imei", {
            params: { q },
        });

        if (data.serie) {
            seleccionarItemConSerie(data.serie);
            return true;
        }
    } catch {
        // Continuar búsqueda normal
    }

    return false;
}

async function buscarProductos() {
    cursor.value = 0;
    const q = busqueda.value.trim();

    if (sinCajaAbierta.value) {
        toastWarning("Debes abrir caja antes de buscar productos");
        return;
    }

    if (q.length < 1) {
        resultados.value = [];
        dropdown.value = false;
        return;
    }

    buscando.value = true;

    try {
        const encontroImei = await intentarBuscarImei(q);
        if (encontroImei) {
            busqueda.value = "";
            resultados.value = [];
            dropdown.value = false;
            return;
        }

        const { data } = await http.get("/api/ventas/buscar-variantes", {
            params: { q },
        });

        resultados.value = Array.isArray(data) ? data : [];

        if (resultados.value.length === 1 && !resultados.value[0].sin_stock) {
            const qq = q.toLowerCase();
            const r = resultados.value[0];

            if (
                r.codigo_barras?.toLowerCase() === qq ||
                r.sku?.toLowerCase() === qq ||
                r.codigo?.toLowerCase() === qq
            ) {
                seleccionarItem(r);
                return;
            }
        }

        const firstOk = resultados.value.findIndex((r) => !r.sin_stock);
        cursor.value = firstOk === -1 ? 0 : firstOk;
        dropdown.value = resultados.value.length > 0;
    } catch {
        toastError("Error en la búsqueda");
    } finally {
        buscando.value = false;
    }
}

function onBusquedaInput() {
    const q = busqueda.value.trim();

    if (!q) {
        resultados.value = [];
        dropdown.value = false;
        cursor.value = 0;
    }
}

function moverCursor(dir) {
    if (!dropdown.value || resultados.value.length === 0) return;

    let next = cursor.value + dir;
    while (
        next >= 0 &&
        next < resultados.value.length &&
        resultados.value[next].sin_stock
    ) {
        next += dir;
    }

    if (next >= 0 && next < resultados.value.length) {
        cursor.value = next;
    }
}

function seleccionarCursor() {
    if (!dropdown.value || resultados.value.length === 0) return;

    const r = resultados.value[cursor.value];
    if (r && !r.sin_stock) seleccionarItem(r);
}

function cerrarDropdown() {
    dropdown.value = false;
    resultados.value = [];
}

function limpiarBusqueda() {
    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;
    nextTick(() => searchRef.value?.focus?.());
}

// ── Selección de item normal ──────────────────────────────────────────────────
function seleccionarItem(r) {
    if (r.sin_stock) {
        toastWarning(`Sin stock: ${r.nombre}`);
        return;
    }

    if (r.serie_id) {
        seleccionarItemConSerie({
            serie_id: r.serie_id,
            identificador: r.imei ?? r.codigo_barras,
            variante_id: r.id ?? null,
            producto_id: r.producto_id,
            nombre: r.nombre,
            sku: r.sku,
            codigo: r.codigo,
            imagen_url: r.imagen_url,
            precio_venta: r.precio_venta ?? 0,
            precio1: r.precio1 ?? null,
            precio2: r.precio2 ?? null,
            precio3: r.precio3 ?? null,
            precio4: r.precio4 ?? null,
            precio5: r.precio5 ?? null,
            exhibido: r.exhibido ?? false,
        });

        busqueda.value = "";
        resultados.value = [];
        dropdown.value = false;
        cursor.value = 0;
        return;
    }

    if (r.tiene_series) {
        modalSerie.productoId = r.producto_id;
        modalSerie.varianteId = r.id ?? null;
        modalSerie.nombre = r.nombre;
        modalSerie.itemPendiente = r;
        modalSerie.visible = true;

        busqueda.value = "";
        dropdown.value = false;
        resultados.value = [];
        return;
    }

    const det = store.agregarDetalle(r);
    const idx = detalles.value.findIndex((x) => x._key === det?._key);
    selectedIdx.value = idx >= 0 ? idx : 0;

    if (det && idx !== -1) {
        toastSuccess(`+1 a ${r.nombre}`);
    }

    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;
    cursor.value = 0;

    nextTick(() => searchRef.value?.focus?.());
}

// ── Selección con serie ───────────────────────────────────────────────────────
async function agregarPedidoAlCarrito(pedido, detallePedido) {
    if (detalles.value.some((d) => Number(d.pedido_detalle_id) === Number(detallePedido.id))) {
        toastWarning("Ese articulo del pedido ya esta en la venta");
        return;
    }

    const q = detallePedido.variante?.sku || detallePedido.producto?.codigo || detallePedido.descripcion;
    if (!q) {
        toastError("No se pudo localizar el producto del pedido");
        return;
    }

    try {
        const { data } = await http.get("/api/ventas/buscar-variantes", {
            params: {
                q,
                pedido_detalle_id: detallePedido.id,
            },
        });

        const items = Array.isArray(data) ? data : [];
        const item = items.find((r) =>
            Number(r.producto_id) === Number(detallePedido.producto_id) &&
            String(r.id ?? "") === String(detallePedido.variante_id ?? "")
        );

        if (!item || item.sin_stock) {
            toastWarning("El producto del pedido ya no tiene stock disponible");
            return;
        }

        const det = store.agregarDetalle({
            ...item,
            pedido_id: pedido.id,
            pedido_detalle_id: detallePedido.id,
            precio_venta: Number(detallePedido.precio_acordado || item.precio_venta || 0),
        });
        det.cantidad = Number(detallePedido.cantidad || 1);
        det.precio_venta = Number(detallePedido.precio_acordado || item.precio_venta || 0);
        det.pedido_id = pedido.id;
        det.pedido_detalle_id = detallePedido.id;
        det.cantidad_fija = true;
        store.normalizeLinea(det);

        selectedIdx.value = detalles.value.findIndex((x) => x._key === det._key);
        toastSuccess(`Pedido ${pedido.folio} cargado`);
    } catch {
        toastError("No se pudo cargar el pedido al carrito");
    }
}

function seleccionarItemConSerie(serieData) {
    const det = store.agregarDetalleConSerie({
        serie_id: serieData.serie_id,
        variante_id: serieData.variante_id,
        producto_id: serieData.producto_id,
        identificador: serieData.identificador,
        nombre: serieData.nombre,
        sku: serieData.sku ?? null,
        codigo: serieData.codigo,
        imagen_url: serieData.imagen_url,
        precio_venta: Number(serieData.precio_venta ?? 0),
        precio1: serieData.precio1 ?? null,
        precio2: serieData.precio2 ?? null,
        precio3: serieData.precio3 ?? null,
        precio4: serieData.precio4 ?? null,
        precio5: serieData.precio5 ?? null,
        exhibido: serieData.exhibido ?? false,
    });

    const idx = detalles.value.findIndex((x) => x._key === det?._key);
    selectedIdx.value = idx >= 0 ? idx : 0;

    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;

    toastSuccess(`IMEI: ${serieData.identificador}`);
    nextTick(() => searchRef.value?.focus?.());
}

// ── Callback ModalSerie ───────────────────────────────────────────────────────
function onSerieSeleccionada(serie) {
    modalSerie.visible = false;

    seleccionarItemConSerie({
        serie_id: serie.id,
        identificador: serie.identificador,
        variante_id: modalSerie.itemPendiente?.id ?? null,
        producto_id: modalSerie.itemPendiente?.producto_id,
        nombre: modalSerie.nombre,
        sku: serie.variante_sku,
        codigo: modalSerie.itemPendiente?.codigo,
        imagen_url: modalSerie.itemPendiente?.imagen_url,
        precio_venta:
            serie.precio_venta ?? modalSerie.itemPendiente?.precio_venta ?? 0,
        precio1: modalSerie.itemPendiente?.precio1,
        precio2: modalSerie.itemPendiente?.precio2,
        precio3: modalSerie.itemPendiente?.precio3,
        precio4: modalSerie.itemPendiente?.precio4,
        precio5: modalSerie.itemPendiente?.precio5,
        exhibido: modalSerie.itemPendiente?.exhibido ?? false,
    });
}

// ── Tabla ─────────────────────────────────────────────────────────────────────
function recalcularLinea(idx) {
    const d = detalles.value[idx];
    if (!d) return;

    const matchKey = CLAVES_PRECIO.find(
        (c) => d[c] != null && Number(d[c]) === Number(d.precio_venta),
    );

    d.precio_lista_sel = matchKey ? ETIQUETAS_PRECIO[matchKey] : null;
    store.recalcularLinea(idx);
}

function quitarDetalle(idx) {
    store.quitarDetalle(idx);

    if (selectedIdx.value === idx) selectedIdx.value = null;
    else if (selectedIdx.value != null && selectedIdx.value > idx) {
        selectedIdx.value--;
    }
}

async function abrirModalDescuento() {
    if (detalles.value.length === 0) {
        toastWarning("Agrega al menos un producto");
        return;
    }

    detalles.value.forEach(store.normalizeLinea);

    const ventaForm = form.value ?? form;
    const maximo = Number(subtotal.value || 0);
    const actual = Number(ventaForm.descuento || 0);

    const { value, isConfirmed } = await Swal.fire({
        title: "Descuento global",
        input: "text",
        inputLabel: `Subtotal: ${formatPrecio(maximo)}`,
        inputValue: actual > 0 ? String(actual) : "",
        inputPlaceholder: "0.00",
        showCancelButton: true,
        confirmButtonText: "Aplicar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#059669",
        reverseButtons: true,
        preConfirm: (raw) => {
            const monto = Number(String(raw || "0").replace(",", "."));

            if (!Number.isFinite(monto) || monto < 0) {
                Swal.showValidationMessage("Captura un monto valido");
                return false;
            }

            if (monto > maximo) {
                Swal.showValidationMessage("El descuento no puede superar el subtotal");
                return false;
            }

            return monto;
        },
    });

    if (!isConfirmed) return;

    ventaForm.descuento = Number(value || 0);
    actualizarSaldoAplicado(cobro.value.saldo_aplicado);
    nextTick(() => searchRef.value?.focus?.());
}

function resetearTodo() {
    store.resetVenta();

    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;
    popoverIdx.value = null;
    selectedIdx.value = null;

    nextTick(() => searchRef.value?.focus?.());
}

async function reimprimirUltimaVenta() {
    if (imprimiendoUltima.value) return;
    if (!ultimaVentaDisponible.value) {
        toastWarning("No hay una ultima venta para reimprimir");
        return;
    }

    imprimiendoUltima.value = true;
    try {
        await imprimirTicketVenta(crearTicketVenta(ultimaVenta.value), obtenerImpresoraTicket());
    } catch (e) {
        toastError(e.message ?? "No se pudo reimprimir el ticket");
    } finally {
        imprimiendoUltima.value = false;
    }
}

// ── Apertura modal cobro ──────────────────────────────────────────────────────
function abrirModalCobro() {
    if (!corteActual.value?.id) {
        toastWarning("Debes abrir caja antes de registrar una venta");
        return;
    }

    if (detalles.value.length === 0) {
        toastWarning("Agrega al menos un producto");
        return;
    }

    detalles.value.forEach(store.normalizeLinea);

    if (hayExcedido.value) {
        toastError("Hay artículos que superan el stock disponible");
        return;
    }

    const sinSerie = detalles.value.find((d) => d.tiene_series && !d.serie_id);
    if (sinSerie) {
        toastError(`«${sinSerie.nombre}» requiere seleccionar un IMEI/serie.`);
        return;
    }

    modalDatosVenta.value = true;
}

function continuarAlCobro() {
    if (!cobro.value.vendedor_id) {
        toastWarning("Selecciona un vendedor");
        return;
    }

    modalDatosVenta.value = false;
    store.abrirCobro();
    aplicarSaldoAutomatico();
}

async function cargarSaldoCliente() {
    cobro.value.saldo_disponible = 0;
    cobro.value.saldo_aplicado = 0;
    pedidosDisponibles.value = [];
    tieneProductosPendientes.value = false;

    if (!cliente.value?.id) return;

    try {
        const { data } = await http.get(`/api/clientes/${cliente.value.id}/pedidos-resumen`);
        cobro.value.saldo_disponible = Number(data?.saldo_favor ?? 0);
        pedidosDisponibles.value = Array.isArray(data?.pedidos_disponibles)
            ? data.pedidos_disponibles
            : [];
        tieneProductosPendientes.value = !!data?.tiene_productos_pendientes;
        aplicarSaldoAutomatico();
    } catch {
        cobro.value.saldo_disponible = 0;
        pedidosDisponibles.value = [];
        tieneProductosPendientes.value = false;
    }
}

function aplicarSaldoAutomatico() {
    if (!cliente.value?.id || saldoBloqueado.value) {
        cobro.value.saldo_aplicado = 0;
        return;
    }

    // Si la venta es de pedidos específicos, limitamos el saldo al anticipo de esos pedidos
    // para no consumir anticipos de otros pedidos del mismo cliente
    const topePorPedido =
        ventaSoloProductosPendientes.value && anticipoPedidosEnCarrito.value > 0
            ? Math.min(Number(cobro.value.saldo_disponible || 0), anticipoPedidosEnCarrito.value)
            : Number(cobro.value.saldo_disponible || 0);

    cobro.value.saldo_aplicado = Math.min(topePorPedido, Number(total.value || 0));

    if (cobro.value.forma_pago === "efectivo") {
        cobro.value.monto_recibido = totalACobrar.value > 0 ? totalACobrar.value : 0;
    }
}

function actualizarSaldoAplicado(value) {
    if (saldoBloqueado.value) {
        cobro.value.saldo_aplicado = 0;
        return;
    }

    const monto = Number(value || 0);
    cobro.value.saldo_aplicado = Math.min(
        Math.max(0, monto),
        Number(cobro.value.saldo_disponible || 0),
        Number(total.value || 0),
    );
}

// ── Guardar venta final ───────────────────────────────────────────────────────
function detallesDisponiblesPedido(pedido) {
    return (pedido?.detalles ?? []).filter((d) =>
        ["disponible", "reservado"].includes(d.estado),
    );
}

async function guardarVentaFinal() {
    if (!cobro.value.vendedor_id) {
        toastWarning("Selecciona un vendedor");
        return;
    }

    if (cobro.value.forma_pago === "efectivo" && pagoInsuficiente.value) {
        toastError("El monto recibido es menor al total");
        return;
    }

    const r = await Swal.fire({
        title: "Confirmar venta",
        html: `
            <p style="font-size:14px;color:#475569;">
                <strong>${detalles.value.length} artículo${detalles.value.length !== 1 ? "s" : ""}</strong>
                por un total de <strong style="color:#059669;">${formatPrecio(total.value)}</strong>.
            </p>
            <p style="margin-top:8px;font-size:12px;color:#94a3b8;">
                El stock se descontará automáticamente.
            </p>
        `,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#059669",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Confirmar venta",
        cancelButtonText: "Revisar",
        reverseButtons: true,
    });

    if (!r.isConfirmed) return;

    const res = await store.guardarVenta();

    if (!res.ok) {
        if (res.campo === "stock" || res.campo === "serie") {
            toastError(res.message);
        } else {
            await Swal.fire({
                icon: "error",
                title: "Error al registrar",
                text: res.message,
            });
        }
        return;
    }

    await Swal.fire({
        icon: "success",
        title: "¡Venta registrada!",
        html: `<p style="font-size:14px;color:#475569;">Stock actualizado correctamente.</p>`,
        confirmButtonColor: "#059669",
        showCancelButton: true,
        confirmButtonText: "Imprimir ticket",
        cancelButtonText: "Nueva venta",
        reverseButtons: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            try {
                await imprimirTicketVenta(crearTicketVenta(res.venta), obtenerImpresoraTicket());
            } catch (e) {
                Swal.showValidationMessage(e.message ?? "No se pudo imprimir el ticket");
                return false;
            }
            return true;
        },
    });

    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;
    popoverIdx.value = null;
    selectedIdx.value = null;

    nextTick(() => searchRef.value?.focus?.());
}

// ── Shortcuts ─────────────────────────────────────────────────────────────────
function onKeydown(e) {
    if (e.key === "F2") {
        e.preventDefault();
        searchRef.value?.focus?.();
        return;
    }

    if (e.ctrlKey && e.key === "Enter") {
        e.preventDefault();
        if (cobro.value.visible) guardarVentaFinal();
        else if (modalDatosVenta.value) continuarAlCobro();
        else abrirModalCobro();
        return;
    }

    if (e.altKey && e.key.toLowerCase() === "d") {
        e.preventDefault();
        abrirModalDescuento();
        return;
    }

    if (e.key === "Escape") {
        if (modalExistencias.visible) {
            e.preventDefault();
            cerrarExistenciasSucursal();
            return;
        }

        if (modalSerie.visible) {
            e.preventDefault();
            modalSerie.visible = false;
            return;
        }

        if (modalPrecioManual.visible) {
            e.preventDefault();
            cancelarPrecioManual(modalPrecioManual.idx);
            return;
        }

        if (cobro.value.visible) {
            e.preventDefault();
            store.cerrarCobro();
            return;
        }

        if (modalDatosVenta.value) {
            e.preventDefault();
            modalDatosVenta.value = false;
            return;
        }

        if (popoverIdx.value !== null) {
            e.preventDefault();
            cerrarPopover();
            return;
        }

        if (dropdown.value) {
            e.preventDefault();
            cerrarDropdown();
            return;
        }
    }

    if (e.key === "Delete" && selectedIdx.value != null) {
        const tag = (e.target?.tagName || "").toLowerCase();
        if (tag === "input" || tag === "textarea" || tag === "select") return;

        e.preventDefault();
        quitarDetalle(selectedIdx.value);
    }
}

// ── Click afuera ──────────────────────────────────────────────────────────────
function onDocClick(e) {
    if (!root.value) return;

    if (dropdown.value && !root.value.contains(e.target)) {
        cerrarDropdown();
    }

    if (popoverIdx.value !== null && !modalPrecioManual.visible) {
        cerrarPopover();
    }
}

onMounted(async () => {
    document.addEventListener("click", onDocClick);
    document.addEventListener("keydown", onKeydown);
    await cargarCorteActual();
    cargarVentasEnEspera();
    if (obtenerImpresoraTicket()) void conectar();

    nextTick(() => searchRef.value?.focus?.());
});

onBeforeUnmount(() => {
    document.removeEventListener("click", onDocClick);
    document.removeEventListener("keydown", onKeydown);
});
</script>
