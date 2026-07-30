<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-4">
    <p class="text-xs text-slate-500">{{ label }}</p>
    <p class="mt-1 text-xl font-bold" :class="cls">
      {{ fmt(valor) }}
    </p>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  label: { type: String, required: true },
  valor: { type: [Number, String], default: 0 },
  color: { type: String, default: "emerald" },
});

const colores = {
  emerald: "text-emerald-700",
  blue:    "text-sky-700",
  violet:  "text-emerald-700",
  orange:  "text-amber-700",
  cyan:    "text-emerald-700",
};

const cls = computed(() => colores[props.color] ?? colores.emerald);

const fmt = (v) =>
  new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(v ?? 0));
</script>