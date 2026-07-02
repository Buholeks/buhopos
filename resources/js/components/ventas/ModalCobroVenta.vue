<template>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 sm:p-4">
        <div class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-3xl sm:rounded-3xl">
            <div class="shrink-0 border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Cobrar venta</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Captura el cobro y confirma la operación.
                </p>
            </div>

            <div class="grid flex-1 gap-6 overflow-y-auto px-6 py-5 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-4">
                    <div
                        v-if="cliente?.id && saldoDisponible > 0"
                        class="rounded-2xl border p-3"
                        :class="saldoBloqueado ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50'"
                    >
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" :class="saldoBloqueado ? 'text-amber-900' : 'text-emerald-900'">
                                    Saldo a favor
                                </p>
                                <p class="text-xs" :class="saldoBloqueado ? 'text-amber-700' : 'text-emerald-700'">
                                    Disponible: {{ formatPrecio(saldoDisponible) }}
                                </p>
                            </div>

                            <button
                                v-if="!saldoBloqueado"
                                type="button"
                                class="rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                @click="$emit('update:saldoAplicado', Math.min(saldoDisponible, total))"
                            >
                                Aplicar maximo
                            </button>
                        </div>

                        <p v-if="saldoBloqueado" class="text-xs leading-relaxed text-amber-800">
                            Este saldo está reservado para productos pendientes de pedidos o apartados. Para usarlo, carga únicamente esos productos.
                        </p>

                        <input
                            v-else
                            :value="saldoAplicado"
                            type="number"
                            min="0"
                            :max="Math.min(saldoDisponible, total)"
                            step="0.01"
                            class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @input="$emit('update:saldoAplicado', $event.target.value)"
                        />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-slate-700">
                            Métodos de pago
                        </label>

                        <button
                            v-if="pagos.length < 3"
                            type="button"
                            class="text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                            @click="$emit('agregar-linea')"
                        >
                            + Agregar otro método
                        </button>
                    </div>

                    <div
                        v-for="(linea, i) in pagos"
                        :key="i"
                        class="space-y-3 rounded-2xl border border-slate-200 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <select
                                :value="linea.forma_pago"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @change="$emit('update:lineaPago', i, 'forma_pago', $event.target.value)"
                            >
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>

                            <input
                                v-if="pagos.length > 1 && linea.forma_pago !== 'efectivo'"
                                :value="linea.monto"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Monto"
                                class="w-32 shrink-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @input="$emit('update:lineaPago', i, 'monto', $event.target.value)"
                            />

                            <button
                                v-if="pagos.length > 1"
                                type="button"
                                class="shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                title="Quitar método"
                                @click="$emit('quitar-linea', i)"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div v-if="linea.forma_pago === 'transferencia'">
                            <select
                                :value="linea.cuenta_bancaria_id"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @change="$emit('update:lineaPago', i, 'cuenta_bancaria_id', $event.target.value)"
                            >
                                <option value="" disabled>Selecciona una cuenta…</option>
                                <option v-for="c in cuentasBancarias" :key="c.id" :value="c.id">
                                    {{ c.nombre }}<template v-if="c.banco"> ({{ c.banco }})</template>
                                </option>
                            </select>
                        </div>

                        <div v-if="linea.forma_pago === 'tarjeta'">
                            <select
                                :value="linea.terminal_pago_id"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @change="$emit('update:lineaPago', i, 'terminal_pago_id', $event.target.value)"
                            >
                                <option value="" disabled>Selecciona una terminal…</option>
                                <option v-for="t in terminalesPago" :key="t.id" :value="t.id">
                                    {{ t.nombre }}<template v-if="t.banco"> ({{ t.banco }})</template>
                                </option>
                            </select>
                        </div>

                        <div v-if="linea.forma_pago === 'efectivo'">
                            <label class="mb-1 block text-xs font-medium text-slate-500">
                                Monto recibido
                            </label>
                            <input
                                :ref="(el) => setMontoRecibidoRef(i, el)"
                                :value="linea.monto_recibido"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @input="$emit('update:lineaPago', i, 'monto_recibido', $event.target.value)"
                            />
                        </div>
                    </div>

                    <div
                        v-if="pagos.length > 1"
                        class="flex items-center justify-between rounded-xl px-3 py-2 text-sm"
                        :class="restante !== 0 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'"
                    >
                        <span>Asignado: {{ formatPrecio(montoAsignado) }}</span>
                        <span class="font-semibold">Restante: {{ formatPrecio(restante) }}</span>
                    </div>

                    <p v-if="pagoInsuficiente" class="text-xs text-red-600">
                        El monto recibido es menor al monto de esa línea.
                    </p>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Notas
                        </label>

                        <textarea
                            :value="notas"
                            rows="3"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            placeholder="Observaciones de la venta"
                            @input="$emit('update:notas', $event.target.value)"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Resumen
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Cliente</span>
                            <span class="truncate text-right font-medium text-slate-900">
                                {{ cliente?.nombre || cliente?.name || "Publico en General" }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Vendedor</span>
                            <span class="truncate text-right font-medium text-slate-900">
                                {{ vendedor?.name || vendedor?.nombre || "Sin seleccionar" }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Subtotal</span>
                            <span class="font-medium text-slate-900">{{ formatPrecio(subtotal) }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Descuento</span>
                            <span class="font-medium text-slate-900">{{ formatPrecio(descuento) }}</span>
                        </div>

                        <div
                            v-if="saldoAplicado > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-slate-500">Saldo aplicado</span>
                            <span class="font-semibold text-emerald-700">
                                -{{ formatPrecio(saldoAplicado) }}
                            </span>
                        </div>

                        <div class="grid gap-3 border-t border-slate-200 pt-4">
                            <div class="rounded-2xl border border-emerald-200 bg-white px-4 py-3">
                                <p class="text-xs font-black uppercase tracking-wide text-emerald-700">
                                    Total de la venta
                                </p>
                                <p class="mt-1 font-mono text-3xl font-black tabular-nums text-emerald-700">
                                    {{ formatPrecio(total) }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border px-4 py-3"
                                :class="totalACobrar > 0
                                    ? 'border-amber-300 bg-amber-50'
                                    : 'border-emerald-200 bg-emerald-50'"
                            >
                                <p
                                    class="text-xs font-black uppercase tracking-wide"
                                    :class="totalACobrar > 0 ? 'text-amber-700' : 'text-emerald-700'"
                                >
                                    Restante a pagar
                                </p>
                                <p
                                    class="mt-1 font-mono text-3xl font-black tabular-nums"
                                    :class="totalACobrar > 0 ? 'text-amber-950' : 'text-emerald-800'"
                                >
                                    {{ formatPrecio(totalACobrar) }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="cambio > 0"
                            class="rounded-2xl border px-4 py-4 text-center shadow-sm border-sky-300 bg-sky-100 ring-4 ring-sky-50"
                        >
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-700">
                                Cambio a entregar
                            </p>
                            <p class="mt-1 font-mono text-4xl font-black tabular-nums sm:text-5xl text-sky-950">
                                {{ formatPrecio(cambio) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                <button
                    type="button"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('cancel')"
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="disableConfirm"
                    @click="$emit('confirm')"
                >
                    Confirmar venta
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted } from "vue";
import { X } from "lucide-vue-next";

const montoRecibidoRefs = {};

function setMontoRecibidoRef(i, el) {
    montoRecibidoRefs[i] = el;
}

defineProps({
    vendedorId: { type: [String, Number, null], default: null },
    vendedor: { type: Object, default: null },
    pagos: { type: Array, default: () => [] },
    montoAsignado: { type: Number, default: 0 },
    restante: { type: Number, default: 0 },
    cuentasBancarias: { type: Array, default: () => [] },
    terminalesPago: { type: Array, default: () => [] },
    notas: { type: String, default: "" },
    subtotal: { type: Number, default: 0 },
    descuento: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    totalACobrar: { type: Number, default: 0 },
    saldoDisponible: { type: Number, default: 0 },
    saldoAplicado: { type: Number, default: 0 },
    saldoBloqueado: { type: Boolean, default: false },
    cambio: { type: Number, default: 0 },
    pagoInsuficiente: { type: Boolean, default: false },
    cliente: { type: Object, default: null },
    disableConfirm: { type: Boolean, default: false },
    formatPrecio: { type: Function, required: true },
});

defineEmits([
    "update:lineaPago",
    "agregar-linea",
    "quitar-linea",
    "update:saldoAplicado",
    "update:notas",
    "cancel",
    "confirm",
]);

onMounted(() => {
    nextTick(() => montoRecibidoRefs[0]?.focus());
});
</script>
