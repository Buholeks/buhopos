<template>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between text-sm">
            <span class="text-slate-500">Subtotal</span>
            <span class="font-black text-slate-900">{{ money(subtotal) }}</span>
        </div>
        <div class="mt-1 flex items-center justify-between text-sm">
            <span class="text-slate-500">Anticipo</span>
            <span class="font-bold text-slate-700">{{ money(anticipo) }}</span>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
            <span class="text-sm font-semibold text-slate-700">Saldo pendiente</span>
            <span class="text-lg font-black text-emerald-700">{{ money(saldoPendiente) }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    subtotal: { type: Number, default: 0 },
    anticipo: { type: Number, default: 0 },
    saldoPendiente: { type: Number, default: 0 },
})

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
}
</script>
