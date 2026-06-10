<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-black text-slate-900">Cancelar {{ pedido?.tipo || 'pedido' }}</h2>
                <p class="text-sm text-slate-500">{{ pedido?.folio }} · {{ pedido?.cliente?.nombre }}</p>
            </div>
            <div class="space-y-3 p-5">
                <p class="text-sm text-slate-700">¿Confirmas la cancelación? Esta acción actualizará el estado del encargo.</p>
                <div v-if="Number(pedido?.anticipo || 0) > 0" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    El cliente tiene un anticipo de <strong>{{ money(pedido?.anticipo) }}</strong>. Se acreditará como saldo a favor.
                </div>
                <div v-if="pedido?.tipo === 'apartado'" class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
                    Se liberará el inventario reservado para este apartado.
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" :disabled="procesando" @click="$emit('close')">Volver</button>
                <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60" :disabled="procesando" @click="$emit('confirm')">
                    <Loader2 v-if="procesando" class="h-4 w-4 animate-spin" />
                    Confirmar cancelación
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Loader2 } from 'lucide-vue-next'

defineProps({
    visible: { type: Boolean, default: false },
    procesando: { type: Boolean, default: false },
    pedido: { type: Object, default: null },
})
defineEmits(['close', 'confirm'])

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
}
</script>
