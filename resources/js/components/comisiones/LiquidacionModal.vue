<template>
    <BaseModal :open="open" :title="liq ? `Liquidación #${liq.id}` : 'Liquidación'" :subtitle="subtitulo" size="xl" @close="emit('close')">
        <div v-if="!liq" class="flex items-center justify-center gap-2 p-12 text-sm text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin" /> Cargando…
        </div>

        <div v-else class="space-y-5">
            <!-- Estado y total -->
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4" :class="ESTILO_ESTADO[liq.estado].caja">
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="ESTILO_ESTADO[liq.estado].chip">
                        <span class="h-1.5 w-1.5 rounded-full" :class="ESTILO_ESTADO[liq.estado].punto"></span>
                        {{ ESTILO_ESTADO[liq.estado].label }}
                    </span>
                    <p class="mt-2 text-sm text-slate-600">
                        <template v-if="liq.estado === 'pagada'">
                            Pagada el {{ fechaHora(liq.pagado_en) }} por {{ liq.pagado_por }}
                            · {{ liq.salio_de_caja ? `Salió de caja (${liq.forma_pago})` : 'Sin movimiento de caja' }}
                        </template>
                        <template v-else-if="liq.estado === 'cancelada'">
                            Cancelada el {{ fechaHora(liq.cancelado_en) }} por {{ liq.cancelado_por }}. Sus comisiones volvieron a quedar pendientes.
                        </template>
                        <template v-else>Generada por {{ liq.creado_por }}. Sus comisiones quedan apartadas hasta pagarla o cancelarla.</template>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total</p>
                    <p class="text-3xl font-bold tabular-nums text-slate-900">{{ dinero(liq.total) }}</p>
                </div>
            </section>

            <!-- Resumen -->
            <div v-if="liq.estado !== 'cancelada'" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="t in tarjetas" :key="t.label" class="rounded-xl border border-slate-200 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ t.label }}</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums" :class="t.valor < 0 ? 'text-red-600' : 'text-slate-900'">{{ dinero(t.valor) }}</p>
                </div>
            </div>

            <!-- Pago -->
            <section v-if="liq.estado === 'borrador' && pagando" class="space-y-4 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
                <h3 class="text-sm font-bold text-slate-900">Confirmar pago de {{ dinero(liq.total) }}</h3>
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">¿Registrar la salida en la caja?</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <OpcionTarjeta
                            :activa="pago.registrar_caja"
                            :bloqueada="!liq.caja.puede_registrar"
                            :icono="Wallet"
                            titulo="Sí, sale de la caja"
                            :texto="textoCaja"
                            @elegir="pago.registrar_caja = true"
                        />
                        <OpcionTarjeta
                            :activa="!pago.registrar_caja"
                            :icono="FileCheck2"
                            titulo="No, solo registrar el pago"
                            texto="Por ejemplo, si se paga con la nómina."
                            @elegir="pago.registrar_caja = false"
                        />
                    </div>
                </div>
                <div v-if="pago.registrar_caja" class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Forma de pago</p>
                        <Segmentado v-model="pago.forma_pago" :opciones="FORMAS_PAGO" etiqueta="Forma de pago" />
                    </div>
                    <label v-if="pago.forma_pago === 'transferencia'" class="block">
                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cuenta</span>
                        <select v-model="pago.cuenta_bancaria_id" class="campo" :class="errores.cuenta_bancaria_id ? 'border-red-400' : ''">
                            <option :value="null" disabled>Selecciona la cuenta</option>
                            <option v-for="c in liq.cuentas_bancarias" :key="c.id" :value="c.id">{{ c.nombre }}{{ c.banco ? ` · ${c.banco}` : '' }}</option>
                        </select>
                        <span v-if="errores.cuenta_bancaria_id" class="mt-1 block text-xs text-red-600">{{ errores.cuenta_bancaria_id }}</span>
                    </label>
                </div>
                <p v-if="pago.registrar_caja && !liq.caja.abierta" class="flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <TriangleAlert class="h-4 w-4 shrink-0" /> No hay una caja abierta en esta terminal. Ábrela o elige "solo registrar el pago".
                </p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="boton-secundario" @click="pagando = false">Volver</button>
                    <button type="button" class="boton-primario" :disabled="trabajando || (pago.registrar_caja && !liq.caja.abierta)" @click="pagar">
                        <Loader2 v-if="trabajando" class="h-4 w-4 animate-spin" />
                        Confirmar pago
                    </button>
                </div>
            </section>

            <!-- Detalle -->
            <MovimientosComision
                v-if="liq.estado !== 'cancelada'"
                :pagina="{ data: liq.movimientos, last_page: 1 }"
                :filtrable="false"
                :titulo="`Comisiones incluidas (${liq.movimientos.length})`"
            />
            <p v-if="liq.notas" class="whitespace-pre-line rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ liq.notas }}</p>
        </div>

        <template v-if="liq" #footer>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <button
                    v-if="liq.estado !== 'cancelada'"
                    type="button"
                    class="boton-secundario border-red-200 text-red-600 hover:bg-red-50"
                    :disabled="trabajando"
                    @click="cancelar"
                >
                    <Ban class="h-4 w-4" /> Cancelar liquidación
                </button>
                <span v-else></span>
                <div class="flex gap-2">
                    <button type="button" class="boton-secundario" :disabled="descargando" @click="descargarPdf">
                        <Loader2 v-if="descargando" class="h-4 w-4 animate-spin" />
                        <FileDown v-else class="h-4 w-4" />
                        Descargar PDF
                    </button>
                    <button v-if="liq.estado === 'borrador' && !pagando" type="button" class="boton-primario" @click="iniciarPago">
                        <BadgeDollarSign class="h-4 w-4" /> Pagar
                    </button>
                </div>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { BadgeDollarSign, Ban, FileCheck2, FileDown, Loader2, TriangleAlert, Wallet } from "lucide-vue-next";
import BaseModal from "@/components/ui/BaseModal.vue";
import MovimientosComision from "@/components/comisiones/MovimientosComision.vue";
import OpcionTarjeta from "@/components/comisiones/OpcionTarjeta.vue";
import Segmentado from "@/components/comisiones/Segmentado.vue";
import http from "@/lib/http";
import { swal, toastError, toastSuccess } from "@/lib/alert";
import { dinero } from "@/helpers/comisiones";

const props = defineProps({
    open: { type: Boolean, default: false },
    liquidacionId: { type: Number, default: null },
    inicial: { type: Object, default: null }, // respuesta ya cargada (recién generada)
});

const emit = defineEmits(["close", "cambio"]);

const ESTILO_ESTADO = {
    borrador: { label: "Por pagar", caja: "border-amber-200 bg-amber-50/40", chip: "bg-amber-100 text-amber-800", punto: "bg-amber-500" },
    pagada: { label: "Pagada", caja: "border-emerald-200 bg-emerald-50/40", chip: "bg-emerald-100 text-emerald-800", punto: "bg-emerald-500" },
    cancelada: { label: "Cancelada", caja: "border-slate-200 bg-slate-50", chip: "bg-slate-200 text-slate-600", punto: "bg-slate-400" },
};
const FORMAS_PAGO = [
    { valor: "efectivo", label: "Efectivo" },
    { valor: "transferencia", label: "Transferencia" },
];

const liq = ref(null);
const pagando = ref(false);
const trabajando = ref(false);
const descargando = ref(false);
const errores = ref({});
const pago = ref({ registrar_caja: true, forma_pago: "efectivo", cuenta_bancaria_id: null });
let solicitud = 0;

const fmt = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric" });
const fmtHora = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });

const subtitulo = computed(() => {
    if (!liq.value) return "";
    return `${liq.value.empleado?.name} · ${fecha(liq.value.desde)} – ${fecha(liq.value.hasta)}`;
});

const tarjetas = computed(() => {
    const r = liq.value?.resumen ?? {};
    return [
        { label: "Como vendedor", valor: r.comision_vendedor },
        { label: "Como cajero", valor: r.comision_cajero },
        { label: "Devoluciones / cancelaciones", valor: r.descuentos },
        { label: "Ajustes", valor: r.ajustes },
    ];
});

const textoCaja = computed(() => {
    if (!liq.value?.caja.puede_registrar) return "Necesitas permiso de caja.";
    return liq.value.caja.abierta ? "Se registra un egreso en el corte abierto." : "No hay caja abierta en esta terminal.";
});

function fecha(iso) {
    if (!iso) return "—";
    const [a, m, d] = iso.slice(0, 10).split("-").map(Number);
    return fmt.format(new Date(a, m - 1, d));
}

function fechaHora(iso) {
    return iso ? fmtHora.format(new Date(iso)) : "—";
}

function erroresDe(e) {
    return Object.fromEntries(Object.entries(e?.response?.data?.errors ?? {}).map(([k, v]) => [k, v[0]]));
}

async function cargar() {
    const actual = ++solicitud;
    try {
        const { data } = await http.get(`/api/comisiones/liquidaciones/${props.liquidacionId}`);
        if (actual === solicitud) liq.value = data;
    } catch (e) {
        if (actual !== solicitud) return;
        toastError(e?.response?.data?.message || "No se pudo cargar la liquidación.");
        emit("close");
    }
}

watch(
    () => [props.open, props.liquidacionId],
    ([abierto]) => {
        ++solicitud;
        pagando.value = false;
        errores.value = {};
        liq.value = null;
        if (!abierto) return;
        if (props.inicial && props.inicial.id === props.liquidacionId) liq.value = props.inicial;
        else if (props.liquidacionId) cargar();
    },
    { immediate: true },
);

function iniciarPago() {
    errores.value = {};
    pago.value = {
        registrar_caja: !!(liq.value.caja.puede_registrar && liq.value.caja.abierta),
        forma_pago: "efectivo",
        cuenta_bancaria_id: null,
    };
    pagando.value = true;
}

async function pagar() {
    trabajando.value = true;
    errores.value = {};
    try {
        const { data } = await http.post(`/api/comisiones/liquidaciones/${liq.value.id}/pagar`, {
            registrar_caja: pago.value.registrar_caja,
            forma_pago: pago.value.registrar_caja ? pago.value.forma_pago : null,
            cuenta_bancaria_id: pago.value.registrar_caja && pago.value.forma_pago === "transferencia" ? pago.value.cuenta_bancaria_id : null,
        });
        liq.value = data;
        pagando.value = false;
        toastSuccess("Liquidación pagada.");
        emit("cambio", data);
    } catch (e) {
        errores.value = erroresDe(e);
        if (!errores.value.cuenta_bancaria_id) {
            toastError(Object.values(errores.value)[0] || e?.response?.data?.message || "No se pudo registrar el pago.");
        }
    } finally {
        trabajando.value = false;
    }
}

async function cancelar() {
    const conCaja = liq.value.estado === "pagada" && liq.value.salio_de_caja;
    const { value: motivo, isConfirmed } = await swal.fire({
        title: "Cancelar liquidación",
        text: conCaja
            ? "El dinero se registrará de regreso en la caja abierta y las comisiones volverán a quedar pendientes."
            : "Las comisiones volverán a quedar pendientes para una nueva liquidación.",
        input: "text",
        inputPlaceholder: "Motivo",
        showCancelButton: true,
        confirmButtonText: "Cancelar liquidación",
        cancelButtonText: "Volver",
        inputValidator: (v) => (!v || v.trim().length < 3 ? "Escribe el motivo." : undefined),
    });
    if (!isConfirmed) return;

    trabajando.value = true;
    try {
        const { data } = await http.post(`/api/comisiones/liquidaciones/${liq.value.id}/cancelar`, { motivo: motivo.trim() });
        liq.value = data;
        pagando.value = false;
        toastSuccess("Liquidación cancelada.");
        emit("cambio", data);
    } catch (e) {
        toastError(Object.values(erroresDe(e))[0] || e?.response?.data?.message || "No se pudo cancelar.");
    } finally {
        trabajando.value = false;
    }
}

async function descargarPdf() {
    descargando.value = true;
    try {
        const resp = await http.get(`/api/comisiones/liquidaciones/${liq.value.id}/pdf`, { responseType: "blob" });
        // El servidor manda el nombre en Content-Disposition; si no viene se usa uno genérico.
        const disposicion = resp.headers?.["content-disposition"] ?? "";
        const nombre = disposicion.match(/filename="?([^";]+)"?/)?.[1] ?? `liquidacion_${liq.value.id}.pdf`;
        const url = URL.createObjectURL(new Blob([resp.data], { type: "application/pdf" }));
        const a = document.createElement("a");
        a.href = url;
        a.download = nombre;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        toastError("No se pudo generar el PDF.");
    } finally {
        descargando.value = false;
    }
}
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
.boton-primario {
    @apply flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50;
}
.boton-secundario {
    @apply flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50;
}
</style>
