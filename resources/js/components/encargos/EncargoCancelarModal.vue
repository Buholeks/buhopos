<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-black text-slate-900">{{ titulo }}</h2>
                <p class="text-sm text-slate-500">{{ pedido?.folio }} · {{ pedido?.cliente?.nombre }}</p>
            </div>
            <div class="space-y-3 p-5">
                <p class="text-sm text-slate-700">¿Confirmas la cancelación? Esta acción actualizará el estado del encargo.</p>
                <div v-if="detalle" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p class="font-bold text-slate-900">{{ detalle.descripcion }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ Number(detalle.cantidad || 0) }} pza. - {{ money(detalle.subtotal) }}
                    </p>
                </div>
                <div v-if="tieneAnticipo" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    El cliente tiene un anticipo de <strong>{{ money(pedido?.anticipo) }}</strong>. Ese saldo ya existe como saldo a favor; al cancelar puedes mantenerlo o devolverlo.
                    <span v-if="cargandoSaldo">Calculando el disponible real para devolver…</span>
                    <span v-else-if="maximoDevolucion < Number(pedido?.anticipo || 0)">
                        Ojo: parte de ese anticipo ya se usó como saldo a favor en otra venta. Disponible para devolver: <strong>{{ money(maximoDevolucion) }}</strong>.
                    </span>
                </div>
                <div v-if="tieneAnticipo" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Destino del saldo</label>
                    <select
                        v-model="form.destino_saldo"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    >
                        <option value="mantener_saldo">Mantener como saldo a favor</option>
                        <option value="efectivo" :disabled="maximoDevolucion <= 0">Devolver efectivo</option>
                        <option value="transferencia" :disabled="maximoDevolucion <= 0">Devolver por transferencia</option>
                    </select>

                    <div v-if="requiereDevolucion" class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500">
                            Monto a devolver <span class="normal-case text-slate-400">(disponible {{ money(maximoDevolucion) }})</span>
                        </label>
                        <input
                            v-model.number="form.monto_devolucion"
                            type="number"
                            min="0"
                            :max="maximoDevolucion"
                            step="0.01"
                            :disabled="cargandoSaldo"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-100"
                        />
                    </div>

                    <div v-if="form.destino_saldo === 'transferencia'" class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Cuenta bancaria</label>
                        <select
                            v-model="form.cuenta_bancaria_id"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        >
                            <option value="">Selecciona cuenta</option>
                            <option v-for="cuenta in cuentasBancarias" :key="cuenta.id" :value="cuenta.id">
                                {{ cuenta.nombre }}{{ cuenta.banco ? ` - ${cuenta.banco}` : "" }}
                            </option>
                        </select>
                    </div>
                </div>
                <div v-if="pedido?.tipo === 'apartado'" class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
                    Se liberará el inventario reservado para este apartado.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" :disabled="procesando" @click="$emit('close')">Volver</button>
                <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60" :disabled="procesando || !puedeConfirmar" @click="$emit('confirm', payload)">
                    <Loader2 v-if="procesando" class="h-4 w-4 animate-spin" />
                    Confirmar cancelación
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from "vue";
import { Loader2 } from 'lucide-vue-next'

const props = defineProps({
    visible: { type: Boolean, default: false },
    procesando: { type: Boolean, default: false },
    pedido: { type: Object, default: null },
    detalle: { type: Object, default: null },
    cuentasBancarias: { type: Array, default: () => [] },
    maximoDevolucion: { type: Number, default: 0 },
    cargandoSaldo: { type: Boolean, default: false },
})
defineEmits(['close', 'confirm'])

const form = reactive({
    destino_saldo: "mantener_saldo",
    monto_devolucion: 0,
    cuenta_bancaria_id: "",
});

const tieneAnticipo = computed(() => Number(props.pedido?.anticipo || 0) > 0);
const titulo = computed(() => props.detalle ? 'Cancelar articulo' : `Cancelar ${props.pedido?.tipo || "pedido"}`);
const requiereDevolucion = computed(() => ["efectivo", "transferencia"].includes(form.destino_saldo));
const puedeConfirmar = computed(() => {
    if (!requiereDevolucion.value) return true;
    if (props.cargandoSaldo) return false;
    const monto = Number(form.monto_devolucion || 0);
    if (monto <= 0) return false;
    if (monto > props.maximoDevolucion + 0.01) return false;
    if (form.destino_saldo === "transferencia" && !form.cuenta_bancaria_id) return false;
    return true;
});
const payload = computed(() => ({
    destino_saldo: tieneAnticipo.value ? form.destino_saldo : "mantener_saldo",
    monto_devolucion: requiereDevolucion.value ? Number(form.monto_devolucion || 0) : 0,
    cuenta_bancaria_id: form.destino_saldo === "transferencia" ? form.cuenta_bancaria_id || null : null,
}));

watch(
    () => `${props.pedido?.id || ""}:${props.detalle?.id || ""}`,
    () => {
        form.destino_saldo = "mantener_saldo";
        form.monto_devolucion = Number(props.maximoDevolucion || 0);
        form.cuenta_bancaria_id = "";
    },
    { immediate: true },
);

watch(
    () => props.maximoDevolucion,
    (max) => {
        form.monto_devolucion = Number(max || 0);
    },
);

watch(
    () => form.destino_saldo,
    (destino) => {
        if (destino !== "transferencia") form.cuenta_bancaria_id = "";
        if (destino === "mantener_saldo") form.monto_devolucion = Number(props.maximoDevolucion || 0);
    },
);

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
}
</script>
