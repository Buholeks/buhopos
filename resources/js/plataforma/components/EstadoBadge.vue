<template><span :class="clase" class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold capitalize">{{ estado || 'sin suscripción' }}</span></template>
<script setup>
import { computed } from "vue";
const props = defineProps({ estado: String });
const clase = computed(() => ({ activa: 'bg-emerald-100 text-emerald-700', prueba: 'bg-blue-100 text-blue-700', pendiente: 'bg-amber-100 text-amber-700', vencida: 'bg-orange-100 text-orange-700', suspendida: 'bg-rose-100 text-rose-700', cancelada: 'bg-slate-200 text-slate-600' }[props.estado] || 'bg-slate-100 text-slate-500'));
</script>
