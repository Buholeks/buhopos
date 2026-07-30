<template>
    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="clase">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    estado: { type: String, default: 'pendiente' },
})

const labels = {
    pendiente: 'Pendiente',
    en_proceso: 'En proceso',
    disponible: 'Disponible',
    parcial: 'Parcial',
    entregado: 'Entregado',
    devuelto: 'Devuelto',
    cancelado: 'Cancelado',
    vencido: 'Vencido',
}

const clases = {
    pendiente: 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
    en_proceso: 'bg-sky-50 text-sky-700 ring-1 ring-sky-100',
    disponible: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
    parcial: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
    entregado: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    devuelto: 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
    cancelado: 'bg-red-50 text-red-700 ring-1 ring-red-100',
    vencido: 'bg-red-50 text-red-700 ring-1 ring-red-100',
}

const label = computed(() => labels[props.estado] || props.estado || 'Pendiente')
const clase = computed(() => clases[props.estado] || clases.pendiente)
</script>
