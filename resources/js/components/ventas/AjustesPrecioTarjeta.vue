<template>
    <div
        v-if="resumen?.lineas > 0"
        class="rounded-2xl border border-amber-200 bg-amber-50 p-4"
    >
        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">
            Ajustes de precio manual
        </p>
        <p class="mt-2 text-lg font-semibold text-amber-800">
            −{{ fmt(resumen.monto_abajo) }}
            <span v-if="resumen.monto_arriba > 0" class="text-sm font-medium text-amber-700">
                / +{{ fmt(resumen.monto_arriba) }}
            </span>
        </p>
        <p class="mt-1 text-xs text-amber-700/80">
            {{ resumen.lineas }} {{ resumen.lineas === 1 ? "línea" : "líneas" }} en
            {{ resumen.ventas }} {{ resumen.ventas === 1 ? "venta" : "ventas" }} · contra precio de lista
        </p>
    </div>
</template>

<script setup>
defineProps({
    // { lineas, ventas, monto_abajo, monto_arriba } de AjustesPrecioResumen
    resumen: { type: Object, default: null },
});

const mxn = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const fmt = (v) => mxn.format(+v || 0);
</script>
