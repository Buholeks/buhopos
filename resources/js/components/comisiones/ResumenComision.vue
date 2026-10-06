<template>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total del periodo</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ dinero(datos.total) }}</p>
            <p v-if="datos.descuentos" class="mt-0.5 text-xs text-slate-500">
                Incluye <span class="font-semibold text-red-600">{{ dinero(datos.descuentos) }}</span> por devoluciones
            </p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Pendiente de pagar</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-800">{{ dinero(datos.pendiente) }}</p>
            <p class="mt-0.5 text-xs text-emerald-700/80">Pagado: {{ dinero(datos.pagado) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                <UserRound class="h-3.5 w-3.5" /> Como vendedor
            </p>
            <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ dinero(datos.comision_vendedor) }}</p>
            <p v-if="datos.tickets_vendedor !== undefined" class="mt-0.5 text-xs text-slate-500">{{ datos.tickets_vendedor }} ticket(s)</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">
                <ShoppingBag class="h-3.5 w-3.5" /> Como cajero
            </p>
            <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ dinero(datos.comision_cajero) }}</p>
            <p v-if="datos.tickets_cajero !== undefined" class="mt-0.5 text-xs text-slate-500">{{ datos.tickets_cajero }} ticket(s)</p>
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
