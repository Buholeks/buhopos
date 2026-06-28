<template>
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6">
                <div class="flex items-start gap-3">
                    <div
                        class="grid h-11 w-11 place-items-center rounded-2xl bg-rose-50 text-rose-600 ring-1 ring-rose-100"
                    >
                        <RotateCcw class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">
                            Cancelaciones y devoluciones
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Busca por numero de ticket para cancelar la venta o registrar una devolucion.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Numero de ticket
                </label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative flex-1">
                        <Hash
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model.trim="folio"
                            type="text"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            placeholder="Ej. TKT-000001"
                            @keydown.enter="buscarVenta"
                        />
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="buscando || !folio"
                        @click="buscarVenta"
                    >
                        <Loader2 v-if="buscando" class="h-4 w-4 animate-spin" />
                        <Search v-else class="h-4 w-4" />
                        Buscar
                    </button>
                </div>
            </div>

            <div
                v-if="mensaje"
                class="rounded-xl border px-4 py-3 text-sm"
                :class="
                    mensajeTipo === 'error'
                        ? 'border-red-200 bg-red-50 text-red-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                "
            >
                {{ mensaje }}
            </div>

            <template v-if="venta">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-slate-900">
                                    Ticket {{ venta.folio }}
                                </h2>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                    :class="
                                        venta.estado === 'confirmada'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-red-50 text-red-700'
                                    "
                                >
                                    {{ venta.estado }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ formatoFecha(venta.fecha) }} ·
                                {{ venta.cliente?.nombre ?? "Publico general" }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                Usuario: {{ venta.usuario?.name ?? "-" }} ·
                                Vendedor: {{ venta.vendedor?.name ?? "-" }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-right ring-1 ring-slate-200">
                            <p class="text-xs font-medium uppercase text-slate-400">
                                Total
                            </p>
                            <p class="text-xl font-semibold text-emerald-700">
                                {{ formatoDinero(venta.total) }}
                            </p>
                            <p class="text-xs capitalize text-slate-500">
                                {{ venta.forma_pago }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="venta.estado === 'cancelada'"
                        class="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        Venta cancelada.
                        <span v-if="venta.motivo_cancelacion">
                            Motivo: {{ venta.motivo_cancelacion }}
                        </span>
                    </div>
                </section>

                <section
                    v-if="venta.estado !== 'cancelada'"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="font-semibold text-slate-900">
                        ¿Que proceso quieres realizar?
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Primero elige una accion para mostrar solo los campos necesarios.
                    </p>

                    <div
                        class="mt-4 grid gap-3"
                        :class="puedeDevolucionParcial ? 'sm:grid-cols-2' : 'sm:grid-cols-1'"
                    >
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-2xl border p-4 text-left transition"
                            :class="
                                modo === 'cancelacion'
                                    ? 'border-red-300 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            "
                            :disabled="venta.estado === 'cancelada'"
                            @click="modo = 'cancelacion'"
                        >
                            <Ban class="h-5 w-5" />
                            <span>
                                <span class="block font-semibold">Procesar ticket completo</span>
                                <span class="block text-sm opacity-80">Anula o devuelve toda la venta y regresa todo el inventario.</span>
                            </span>
                        </button>

                        <button
                            v-if="puedeDevolucionParcial"
                            type="button"
                            class="flex items-center gap-3 rounded-2xl border p-4 text-left transition"
                            :class="
                                modo === 'devolucion'
                                    ? 'border-amber-300 bg-amber-50 text-amber-700'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            "
                            :disabled="venta.estado === 'cancelada'"
                            @click="modo = 'devolucion'"
                        >
                            <Undo2 class="h-5 w-5" />
                            <span>
                                <span class="block font-semibold">Devolucion parcial</span>
                                <span class="block text-sm opacity-80">Devuelve una o varias partidas; la venta original se conserva.</span>
                            </span>
                        </button>
                    </div>

                    <div
                        v-if="!puedeDevolucionParcial"
                        class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                    >
                        Esta venta tiene una sola partida; usa cancelacion completa.
                    </div>
                </section>

                <section v-if="venta.estado !== 'cancelada' && modo === 'cancelacion'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-2">
                            <Ban class="h-5 w-5 text-red-600" />
                            <h3 class="font-semibold text-slate-900">
                                Procesar ticket completo
                            </h3>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">
                            Cancela la venta completa y define el destino del dinero.
                        </p>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label v-if="ventaTienePedido" class="block">
                                <span class="text-sm font-medium text-slate-700">Tipo de proceso</span>
                                <select
                                    v-model="tipoProcesoCancelacion"
                                    class="mt-2 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"
                                >
                                    <option value="anulacion">Anulacion</option>
                                    <option value="devolucion">Devolucion</option>
                                </select>
                            </label>

                            <label class="block" :class="ventaTienePedido ? '' : 'md:col-span-2'">
                                <span class="text-sm font-medium text-slate-700">Destino del dinero</span>
                                <select
                                    v-model="formaCancelacion"
                                    class="mt-2 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100"
                                >
                                    <option value="efectivo">Reembolso efectivo</option>
                                    <option value="tarjeta">Reverso tarjeta</option>
                                    <option value="transferencia">Reverso transferencia</option>
                                    <option v-if="ventaTieneCliente" value="credito">Saldo a favor</option>
                                </select>
                            </label>
                        </div>

                        <label class="mt-4 block text-sm font-medium text-slate-700">
                            Motivo de cancelacion
                        </label>
                        <textarea
                            v-model.trim="motivoCancelacion"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-red-500 focus:ring-4 focus:ring-red-100"
                            placeholder="Describe el motivo"
                            :disabled="venta.estado === 'cancelada'"
                        />

                        <button
                            type="button"
                            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="procesando || venta.estado === 'cancelada' || !motivoCancelacion"
                            @click="cancelarVenta"
                        >
                            <Loader2 v-if="procesandoAccion === 'cancelar'" class="h-4 w-4 animate-spin" />
                            <Ban v-else class="h-4 w-4" />
                            Procesar ticket
                        </button>
                </section>

                <template v-if="venta.estado !== 'cancelada' && modo === 'devolucion'">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-2">
                            <Undo2 class="h-5 w-5 text-amber-600" />
                            <h3 class="font-semibold text-slate-900">
                                Registrar devolucion
                            </h3>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">
                            Selecciona las partidas devueltas. La venta original se conserva.
                        </p>

                        <div class="mt-4">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Forma de devolucion</span>
                                <select
                                    v-model="formaDevolucion"
                                    class="mt-2 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option v-if="ventaTieneCliente" value="credito">Saldo a favor</option>
                                </select>
                            </label>
                        </div>

                        <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                            Toda devolucion regresa automaticamente al inventario.
                        </div>

                        <label class="mt-4 block text-sm font-medium text-slate-700">
                            Motivo de devolucion
                        </label>
                        <textarea
                            v-model.trim="motivoDevolucion"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-100"
                            placeholder="Describe el motivo"
                            :disabled="venta.estado === 'cancelada'"
                        />
                    </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-900">
                                Partidas para devolucion
                            </h3>
                            <p class="text-sm text-slate-500">
                                No se puede devolver mas de la cantidad disponible.
                            </p>
                        </div>
                        <div class="rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 ring-1 ring-amber-100">
                            Total a devolver: {{ formatoDinero(totalDevolucion) }}
                        </div>
                    </div>

                    <div class="space-y-3">
                        <article
                            v-for="d in venta.detalles"
                            :key="d.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h4 class="font-semibold text-slate-900">
                                        {{ d.producto_nombre }}
                                    </h4>
                                    <p class="text-sm text-slate-500">
                                        <span v-if="d.producto_codigo">{{ d.producto_codigo }}</span>
                                        <span v-if="d.variante_nombre"> · {{ d.variante_nombre }}</span>
                                        <span v-if="d.serie"> · Serie: {{ d.serie }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Vendido: {{ d.cantidad }} · Devuelto: {{ d.cantidad_devuelta }} · Disponible:
                                        {{ d.cantidad_disponible_devolucion }}
                                    </p>
                                </div>

                                <div class="grid gap-2 text-right sm:min-w-48">
                                    <div class="text-sm text-slate-500">
                                        {{ formatoDinero(d.precio_venta) }} c/u
                                    </div>
                                    <input
                                        v-model.number="cantidades[d.id]"
                                        type="number"
                                        min="0"
                                        :max="d.cantidad_disponible_devolucion"
                                        step="1"
                                        class="h-10 rounded-xl border border-slate-200 px-3 text-right text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                        :disabled="venta.estado === 'cancelada' || d.cantidad_disponible_devolucion <= 0"
                                    />
                                </div>
                            </div>
                        </article>
                    </div>

                    <button
                        type="button"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="procesando || venta.estado === 'cancelada' || totalDevolucion <= 0 || !motivoDevolucion"
                        @click="registrarDevolucion"
                    >
                        <Loader2 v-if="procesandoAccion === 'devolver'" class="h-4 w-4 animate-spin" />
                        <Undo2 v-else class="h-4 w-4" />
                        Registrar devolucion
                    </button>
                </section>
                </template>
            </template>
        </section>
    </main>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import Swal from "sweetalert2";
import http from "@/lib/http";
import {
    Ban,
    Hash,
    Loader2,
    RotateCcw,
    Search,
    Undo2,
} from "lucide-vue-next";

const folio = ref("");
const venta = ref(null);
const buscando = ref(false);
const procesandoAccion = ref("");
const mensaje = ref("");
const mensajeTipo = ref("ok");
const motivoCancelacion = ref("");
const motivoDevolucion = ref("");
const formaDevolucion = ref("efectivo");
const tipoProcesoCancelacion = ref("anulacion");
const formaCancelacion = ref("efectivo");
const modo = ref(null);
const cantidades = reactive({});

const procesando = computed(() => Boolean(procesandoAccion.value));
const puedeDevolucionParcial = computed(() => {
    const detalles = venta.value?.detalles ?? [];
    return detalles.filter((detalle) => Number(detalle.cantidad || 0) > 0).length > 1;
});
const ventaTienePedido = computed(() =>
    Boolean(venta.value?.tiene_pedido) ||
    (venta.value?.detalles ?? []).some((detalle) => detalle.pedido_detalle_id),
);
const ventaTieneCliente = computed(() => Boolean(venta.value?.cliente?.id));
const totalDevolucion = computed(() => {
    if (!venta.value) return 0;

    return venta.value.detalles.reduce((acc, detalle) => {
        const cantidad = Number(cantidades[detalle.id] || 0);
        const disponible = Number(detalle.cantidad_disponible_devolucion || 0);
        const cantidadValida = Math.min(Math.max(cantidad, 0), disponible);
        return acc + cantidadValida * Number(detalle.precio_venta || 0);
    }, 0);
});

async function buscarVenta() {
    if (!folio.value) return;

    buscando.value = true;
    limpiarMensaje();
    venta.value = null;
    limpiarFormulario();

    try {
        const { data } = await http.get("/api/cancelaciones-devoluciones/buscar", {
            params: { folio: folio.value },
        });
        cargarVenta(data);
    } catch (error) {
        mostrarError(error.response?.data?.message ?? "No se encontro el ticket.");
    } finally {
        buscando.value = false;
    }
}

async function cancelarVenta() {
    const confirmar = await Swal.fire({
        icon: "warning",
        title: "Cancelar venta",
        text: "Esta accion cancelara la venta completa y regresara todo el inventario.",
        showCancelButton: true,
        confirmButtonText: "Si, cancelar venta",
        cancelButtonText: "Volver",
        confirmButtonColor: "#dc2626",
        cancelButtonColor: "#64748b",
        reverseButtons: true,
    });

    if (!confirmar.isConfirmed) {
        return;
    }

    procesandoAccion.value = "cancelar";
    limpiarMensaje();

    try {
        const { data } = await http.post("/api/cancelaciones-devoluciones/cancelar", {
            folio: venta.value.folio,
            motivo: motivoCancelacion.value,
            tipo_proceso: ventaTienePedido.value ? tipoProcesoCancelacion.value : "anulacion",
            forma_devolucion: formaCancelacion.value,
        });
        cargarVenta(data.venta);
        motivoCancelacion.value = "";
        mostrarOk(data.message ?? "Venta cancelada correctamente.");
    } catch (error) {
        mostrarError(error.response?.data?.message ?? "No se pudo cancelar la venta.");
    } finally {
        procesandoAccion.value = "";
    }
}

async function registrarDevolucion() {
    const confirmar = await Swal.fire({
        icon: "question",
        title: "Registrar devolucion",
        html: `
            <p style="font-size:14px;color:#475569;">
                Total a devolver: <strong style="color:#d97706;">${formatoDinero(totalDevolucion.value)}</strong>
            </p>
            <p style="margin-top:6px;font-size:12px;color:#64748b;">
                Las partidas seleccionadas regresaran al inventario.
            </p>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar devolucion",
        cancelButtonText: "Volver",
        confirmButtonColor: "#d97706",
        cancelButtonColor: "#64748b",
        reverseButtons: true,
    });

    if (!confirmar.isConfirmed) {
        return;
    }

    procesandoAccion.value = "devolver";
    limpiarMensaje();

    const detalles = venta.value.detalles
        .map((detalle) => ({
            venta_detalle_id: detalle.id,
            cantidad: Math.min(
                Math.max(Number(cantidades[detalle.id] || 0), 0),
                Number(detalle.cantidad_disponible_devolucion || 0),
            ),
        }))
        .filter((detalle) => detalle.cantidad > 0);

    try {
        const { data } = await http.post("/api/cancelaciones-devoluciones/devolver", {
            folio: venta.value.folio,
            motivo: motivoDevolucion.value,
            forma_devolucion: formaDevolucion.value,
            detalles,
        });
        cargarVenta(data.venta);
        motivoDevolucion.value = "";
        mostrarOk(data.message ?? "Devolucion registrada correctamente.");
    } catch (error) {
        mostrarError(error.response?.data?.message ?? "No se pudo registrar la devolucion.");
    } finally {
        procesandoAccion.value = "";
    }
}

function cargarVenta(data) {
    venta.value = data;
    if (!ventaTieneCliente.value) {
        formaCancelacion.value = "efectivo";
        formaDevolucion.value = "efectivo";
    }
    if (!puedeDevolucionParcial.value && modo.value === "devolucion") {
        modo.value = null;
    }
    limpiarCantidades();
    for (const detalle of venta.value.detalles ?? []) {
        cantidades[detalle.id] = 0;
    }
}

function limpiarFormulario() {
    motivoCancelacion.value = "";
    motivoDevolucion.value = "";
    formaDevolucion.value = "efectivo";
    tipoProcesoCancelacion.value = "anulacion";
    formaCancelacion.value = "efectivo";
    modo.value = null;
    limpiarCantidades();
}

function limpiarCantidades() {
    for (const key of Object.keys(cantidades)) {
        delete cantidades[key];
    }
}

function mostrarOk(texto) {
    mensaje.value = texto;
    mensajeTipo.value = "ok";
    Swal.fire({
        icon: "success",
        title: "Listo",
        text: texto,
        confirmButtonColor: "#059669",
    });
}

function mostrarError(texto) {
    mensaje.value = texto;
    mensajeTipo.value = "error";
    Swal.fire({
        icon: "error",
        title: "Error",
        text: texto,
        confirmButtonColor: "#dc2626",
    });
}

function limpiarMensaje() {
    mensaje.value = "";
}

function formatoDinero(valor) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(valor || 0));
}

function formatoFecha(valor) {
    if (!valor) return "-";

    return new Date(valor).toLocaleString("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}
</script>
