<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-slate-900">Desglose</h3>
      <span class="text-xs text-slate-400">Arqueo aplica solo a efectivo</span>
    </div>

    <!-- EFECTIVO: flujo arqueable -->
    <div class="overflow-hidden rounded-xl border border-slate-200">
      <table class="w-full text-sm">
        <tbody>
          <tr>
            <td class="w-2 bg-emerald-400"></td>
            <td class="px-4 py-3 font-medium text-slate-700">Ventas efectivo</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ fmt(corte?.ventas_efectivo) }}</td>
          </tr>

          <tr class="border-t border-slate-100">
            <td class="w-2 bg-amber-400"></td>
            <td class="px-4 py-3 font-medium text-slate-700">Movimientos efectivo</td>
            <td class="px-4 py-3 text-right font-mono"
                :class="Number(corte?.movs_efectivo ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
              {{ Number(corte?.movs_efectivo ?? 0) >= 0 ? '+' : '' }}{{ fmt(corte?.movs_efectivo) }}
            </td>
          </tr>

          <tr class="border-t border-slate-200 bg-cyan-50/60">
            <td class="w-2 bg-cyan-500"></td>
            <td class="px-4 py-3 font-semibold text-cyan-900">Esperado efectivo</td>
            <td class="px-4 py-3 text-right font-mono text-lg font-bold text-cyan-700">{{ fmt(corte?.esperado_efectivo) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OTRAS FORMAS: informativo -->
    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
      <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
        Otras formas (informativo)
      </p>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
          <span class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
            <span class="h-2 w-2 rounded-full bg-sky-400"></span>
            Tarjeta
          </span>
          <span class="font-mono text-sm font-semibold text-slate-700">{{ fmt(corte?.ventas_tarjeta) }}</span>
        </div>

        <div class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
          <span class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
            <span class="h-2 w-2 rounded-full bg-violet-400"></span>
            Transferencia
          </span>
          <span class="font-mono text-sm font-semibold text-slate-700">{{ fmt(corte?.ventas_transferencia) }}</span>
        </div>

        <div class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
          <span class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
            <span class="h-2 w-2 rounded-full bg-teal-400"></span>
            Saldo a favor
          </span>
          <span class="font-mono text-sm font-semibold text-slate-700">{{ fmt(corte?.ventas_saldo_favor) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  corte: { type: Object, default: null },
});

function fmt(v) {
  return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(v ?? 0));
}
</script>
