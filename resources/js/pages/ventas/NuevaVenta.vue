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
            @abrirCaja="abrirCaja"
            @modal-mov="modalMov = true"
            @enEspera="ponerVentaEnEspera"
            @recuperar="abrirModalRecuperar"
            @descuentoGlobal="abrirModalDescuento"
            @reset="resetearTodo"
            @guardar="abrirModalCobro"
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

        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-6">
            <VentaClienteSelector
                :cliente="cliente"
                @select="store.setCliente"
                @clear="store.clearCliente"
            />

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
            :cambio="cambio"
            :pago-insuficiente="pagoInsuficiente"
            :cliente="cliente"
            :disableConfirm="guardando"
            :formatPrecio="formatPrecio"
            @select-vendedor="store.setVendedor"
            @update:formaPago="(v) => (cobro.forma_pago = v)"
            @update:montoRecibido="(v) => (cobro.monto_recibido = v)"
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
import VentaClienteSelector from "@/components/ventas/VentaClienteSelector.vue";
import ModalCobroVenta from "@/components/ventas/ModalCobroVenta.vue";

import { useAuthStore } from "@/stores/auth";
import ModalRecuperarVenta from "@/components/ventas/ModalRecuperarVenta.vue";

import {
    getVentasEnEspera,
    saveVentaEnEspera,
    removeVentaEnEspera,
} from "@/helpers/ventasEnEspera";


const authStore = useAuthStore();

const ventasEnEspera = ref([]);
const modalRecuperar = ref(false);

const empresaId = computed(() => authStore.user?.empresa_id ?? null);
const sucursalId = computed(() => authStore.user?.sucursal_id ?? null);
const userId = computed(() => authStore.user?.id ?? null);

const sinCajaAbierta = computed(() => !corteActual.value?.id);


const modalMov = ref(false);
const guardandoMov = ref(false);
const corteActual = ref(null);
const abriendoCaja = ref(false);

const store = useVentaPosStore();
const {
    guardando,
    cliente,
    form,
    cobro,
    detalles,
    subtotal,
    total,
    cambio,
    pagoInsuficiente,
    hayExcedido,
} = storeToRefs(store);

const root = ref(null);
const searchRef = ref(null);

const busqueda = ref("");
const resultados = ref([]);
const buscando = ref(false);
const dropdown = ref(false);
const cursor = ref(0);

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

function resetearTodo() {
    store.resetVenta();

    busqueda.value = "";
    resultados.value = [];
    dropdown.value = false;
    popoverIdx.value = null;
    selectedIdx.value = null;

    nextTick(() => searchRef.value?.focus?.());
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

    store.abrirCobro();
}

// ── Guardar venta final ───────────────────────────────────────────────────────
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
        confirmButtonText: "Nueva venta",
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
        else abrirModalCobro();
        return;
    }

    if (e.key === "Escape") {
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

onMounted(() => {
    document.addEventListener("click", onDocClick);
    document.addEventListener("keydown", onKeydown);
    cargarCorteActual();
        cargarVentasEnEspera();

    nextTick(() => searchRef.value?.focus?.());
});

onBeforeUnmount(() => {
    document.removeEventListener("click", onDocClick);
    document.removeEventListener("keydown", onKeydown);
});
</script>
