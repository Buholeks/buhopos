<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5">
    <h3 class="text-sm font-semibold text-slate-900 mb-4">Desglose</h3>

    <div class="overflow-hidden rounded-xl border border-slate-200">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500">
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider">Concepto</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider">Monto</th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-t border-slate-100">
            <td class="px-4 py-3 font-medium text-slate-700">Ventas efectivo</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ fmt(corte?.ventas_efectivo) }}</td>
          </tr>

          <tr class="border-t border-slate-100">
            <td class="px-4 py-3 font-medium text-slate-700">Ventas crédito</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ fmt(ventasCredito) }}</td>
          </tr>

          <tr class="border-t border-slate-100">
            <td class="px-4 py-3 font-medium text-slate-700">Movimientos efectivo</td>
            <td class="px-4 py-3 text-right font-mono"
                :class="Number(corte?.movs_efectivo ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
              {{ Number(corte?.movs_efectivo ?? 0) >= 0 ? '+' : '' }}{{ fmt(corte?.movs_efectivo) }}
            </td>
          </tr>

          <tr class="border-t border-slate-100 bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">Esperado efectivo</td>
            <td class="px-4 py-3 text-right font-mono font-bold text-cyan-700">{{ fmt(corte?.esperado_efectivo) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">
      Nota: el arqueo aplica sobre <span class="font-medium">efectivo</span>. Crédito se muestra como informativo.
    </p>
  </div>
</template>

<script setup>
const props = defineProps({
  corte: { type: Object, default: null },
});

function fmt(v) {
  return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(v ?? 0));
}

const ventasCredito = Number(props.corte?.ventas_credito ?? 0);
</script>