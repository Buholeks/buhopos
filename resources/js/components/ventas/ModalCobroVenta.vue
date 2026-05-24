<template>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4">
        <div class="w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Registrar venta</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Captura el cobro y confirma la operación.
                </p>
            </div>

            <div class="grid gap-6 px-6 py-5 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-4">
                    <BaseSearchSelect
                        :model-value="vendedor?.id ?? vendedorId ?? null"
                        label="Vendedor"
                        placeholder="Buscar vendedor..."
                        :fetcher="buscarVendedores"
                        :min-chars="1"
                        :debounce-ms="250"
                        :label-key="(it) => it.name || it.nombre || 'Sin nombre'"
                        :sub-label-key="(it) => it.email || it.telefono || ''"
                        value-key="id"
                        @selected="(it) => $emit('select-vendedor', it)"
                    />

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Método de pago
                        </label>

                        <select
                            :value="formaPago"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="$emit('update:formaPago', $event.target.value)"
                        >
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>

                    <div v-if="formaPago === 'efectivo'">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Monto recibido
                        </label>

                        <input
                            :value="montoRecibido"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition"
                            :class="
                                pagoInsuficiente
                                    ? 'border-red-300 ring-4 ring-red-100'
                                    : 'border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100'
                            "
                            placeholder="0.00"
                            @input="$emit('update:montoRecibido', $event.target.value)"
                        />

                        <p v-if="pagoInsuficiente" class="mt-1 text-xs text-red-600">
                            El monto recibido es menor al total.
                        </p>
                    </div>

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
                                {{ cliente?.nombre || cliente?.name || "Mostrador" }}
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

                        <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-3">
                            <span class="text-sm font-semibold text-slate-700">Total</span>
                            <span class="text-2xl font-bold text-emerald-600">
                                {{ formatPrecio(total) }}
                            </span>
                        </div>

                        <div
                            v-if="formaPago === 'efectivo'"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-slate-500">Cambio</span>
                            <span class="font-semibold text-slate-900">
                                {{ formatPrecio(cambio) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
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
import http from "@/lib/http";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";

defineProps({
    vendedorId: { type: [String, Number, null], default: null },
    vendedor: { type: Object, default: null },
    formaPago: { type: String, default: "efectivo" },
    montoRecibido: { type: [String, Number, null], default: "" },
    notas: { type: String, default: "" },
    subtotal: { type: Number, default: 0 },
    descuento: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    cambio: { type: Number, default: 0 },
    pagoInsuficiente: { type: Boolean, default: false },
    cliente: { type: Object, default: null },
    disableConfirm: { type: Boolean, default: false },
    formatPrecio: { type: Function, required: true },
});

defineEmits([
    "update:formaPago",
    "update:montoRecibido",
    "update:notas",
    "select-vendedor",
    "cancel",
    "confirm",
]);

async function buscarVendedores(q) {
    const { data } = await http.get("/api/users/vendedores", {
        params: { q },
    });

    return Array.isArray(data?.data)
        ? data.data
        : Array.isArray(data)
          ? data
          : [];
}
</script>