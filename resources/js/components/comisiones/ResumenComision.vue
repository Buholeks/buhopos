<template>
    <div class="grid grid-cols-2 overflow-hidden rounded-2xl border border-slate-200 bg-white lg:grid-cols-4">
        <div class="px-4 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total del periodo</p>
            <p class="text-lg font-bold tabular-nums text-slate-900">{{ dinero(datos.total) }}</p>
            <p v-if="datos.descuentos || datos.ajustes" class="truncate text-[11px] text-slate-500">
                <span v-if="datos.descuentos">Devol./cancel. <span class="font-semibold text-red-600">{{ dinero(datos.descuentos) }}</span></span>
                <span v-if="datos.descuentos && datos.ajustes"> · </span>
                <span v-if="datos.ajustes">Ajustes {{ dinero(datos.ajustes) }}</span>
            </p>
        </div>
        <div class="border-l border-slate-100 bg-emerald-50/50 px-4 py-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700">Pendiente de pagar</p>
            <p class="text-lg font-bold tabular-nums text-emerald-800">{{ dinero(datos.pendiente) }}</p>
            <p class="text-[11px] text-emerald-700/80">Pagado {{ dinero(datos.pagado) }}</p>
        </div>
        <div class="border-t border-slate-100 px-4 py-2.5 lg:border-l lg:border-t-0">
            <p class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                <UserRound class="h-3 w-3" /> Como vendedor
            </p>
            <p class="text-lg font-bold tabular-nums text-slate-900">{{ dinero(datos.comision_vendedor) }}</p>
            <p v-if="datos.tickets_vendedor !== undefined" class="text-[11px] text-slate-500">{{ datos.tickets_vendedor }} ticket(s)</p>
        </div>
        <div class="border-l border-t border-slate-100 px-4 py-2.5 lg:border-t-0">
            <p class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                <ShoppingBag class="h-3 w-3" /> Como cajero
            </p>
            <p class="text-lg font-bold tabular-nums text-slate-900">{{ dinero(datos.comision_cajero) }}</p>
            <p v-if="datos.tickets_cajero !== undefined" class="text-[11px] text-slate-500">{{ datos.tickets_cajero }} ticket(s)</p>
        </div>
    </div>
</template>

<script setup>
import { ShoppingBag, UserRound } from "lucide-vue-next";
import { dinero } from "@/helpers/comisiones";

defineProps({
    datos: { type: Object, required: true },
});
</script>
