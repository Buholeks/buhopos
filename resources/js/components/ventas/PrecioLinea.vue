<template>
    <div class="inline-flex flex-col items-end gap-0.5 text-right">
        <div class="flex flex-wrap items-center justify-end gap-1.5">
            <span
                v-if="info.manual && info.diferencia !== 0"
                class="font-mono text-xs text-slate-400 line-through"
                :title="'Precio de lista (P.Venta)'"
            >
                {{ fmt(info.referencia) }}
            </span>
            <span class="font-mono" :class="info.manual ? 'font-semibold text-slate-900' : 'text-slate-700'">
                {{ fmt(info.aplicado) }}
            </span>
            <span
                v-if="info.manual"
                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200"
                :title="info.motivo ? `Motivo: ${info.motivo}` : 'Precio manual'"
            >
                Manual
                <template v-if="info.porcentaje !== null && info.diferencia !== 0">
                    {{ info.diferencia > 0 ? "+" : "−" }}{{ Math.abs(info.porcentaje).toFixed(1) }}%
                </template>
            </span>
            <span
                v-else-if="info.lista && info.lista !== 'P.Venta'"
                class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"
                title="Lista de precio usada"
            >
                {{ info.lista }}
            </span>
        </div>
        <p
            v-if="info.manual && info.motivo && !compacto"
            class="max-w-[16rem] text-xs italic text-amber-700/80"
        >
            {{ info.motivo }}
        </p>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { infoPrecioLinea } from "@/helpers/precioLinea";

const props = defineProps({
    detalle: { type: Object, required: true },
    // compacto: el motivo solo va en el tooltip del chip
    compacto: { type: Boolean, default: false },
});

const info = computed(() => infoPrecioLinea(props.detalle));

const mxn = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const fmt = (v) => mxn.format(+v || 0);
</script>
