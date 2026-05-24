<template>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
    <ResumenCardItem
      titulo="Ventas efectivo"
      :valor="fmt(corte?.ventas_efectivo)"
      sub="(solo arqueable)"
      tone="emerald"
    />

    <ResumenCardItem
      titulo="Ventas crédito"
      :valor="fmt(ventasCredito)"
      sub="(informativo)"
      tone="indigo"
    />

    <ResumenCardItem
      titulo="Movimientos efectivo"
      :valor="fmt(corte?.movs_efectivo ?? corte?.movimientos_efectivo)"
      sub="ingresos/egresos"
      tone="amber"
    />

    <ResumenCardItem
      titulo="Esperado efectivo"
      :valor="fmt(corte?.esperado_efectivo)"
      sub="ventas + movimientos"
      tone="cyan"
    />
  </div>
</template>

<script setup>
import { computed } from "vue";
import ResumenCardItem from "@/components/caja/ResumenCardItem.vue";

const props = defineProps({
  corte: { type: Object, default: null },
});

function fmt(v) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(v ?? 0));
}

// si tu backend ya manda ventas_credito úsalo; si no, queda 0
const ventasCredito = computed(() => Number(props.corte?.ventas_credito ?? 0));
</script>