<template>
    <div
        class="sticky bottom-4 z-20 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
    >
        <div class="grid gap-4 md:grid-cols-5">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Cliente
                </div>
                <div class="mt-1 truncate text-sm font-semibold text-slate-900">
                    {{ cliente?.nombre || cliente?.name || "Mostrador" }}
                </div>
            </div>

            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Artículos
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-900">
                    {{ detallesCount }}
                </div>
            </div>

            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Subtotal
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-900">
                    {{ formatPrecio(subtotal) }}
                </div>
            </div>

            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Descuento
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-900">
                    {{ formatPrecio(descuento) }}
                </div>
            </div>

            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Total
                </div>
                <div class="mt-1 text-lg font-bold text-emerald-600">
                    {{ formatPrecio(total) }}
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-slate-700">Descuento</label>

                <input
                    :value="descuento"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-36 rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    @input="$emit('update:descuento', $event.target.value)"
                />
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="disableRegistrar"
                @click="$emit('registrar')"
            >
                Registrar venta
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    cliente: { type: Object, default: null },
    detallesCount: { type: Number, default: 0 },
    subtotal: { type: Number, default: 0 },
    descuento: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    disableRegistrar: { type: Boolean, default: false },
    formatPrecio: { type: Function, required: true },
});

defineEmits(["registrar", "update:descuento"]);
</script>