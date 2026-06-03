<template>
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 p-4">
      <h2 class="text-sm font-semibold text-slate-900">Mercancia agregada</h2>
      <p class="mt-1 text-xs text-slate-500">{{ items.length }} partida(s) listas para enviar</p>
    </div>

    <div class="overflow-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
          <tr>
            <th class="px-3 py-2 text-left">Cant.</th>
            <th class="px-3 py-2 text-left">Descripcion</th>
            <th class="px-3 py-2 text-left">Variante</th>
            <th class="px-3 py-2 text-right">Compra</th>
            <th class="px-3 py-2 text-right">Venta</th>
            <th class="px-3 py-2 text-right">Total compra</th>
            <th class="px-3 py-2 text-right">Total venta</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(item, idx) in items" :key="idx">
            <td class="px-3 py-2">
              <div class="flex w-32 items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                <button
                  type="button"
                  class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                  :disabled="item.serie_id || Number(item.cantidad) <= 0.001"
                  @click="emit('ajustar-cantidad', item, -1)"
                >
                  -
                </button>
                <input
                  v-model.number="item.cantidad"
                  type="number"
                  min="0.001"
                  step="0.001"
                  class="h-9 min-w-0 flex-1 border-x border-slate-200 text-center text-sm font-semibold text-slate-900 outline-none"
                  :readonly="!!item.serie_id"
                  @blur="emit('normalizar-cantidad', item)"
                />
                <button
                  type="button"
                  class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                  :disabled="!!item.serie_id"
                  @click="emit('ajustar-cantidad', item, 1)"
                >
                  +
                </button>
              </div>
            </td>
            <td class="px-3 py-2 text-slate-700">
              {{ item.nombre }}
              <span v-if="item.serie_identificador" class="block text-xs text-slate-500">
                {{ item.serie_identificador }}
              </span>
            </td>
            <td class="px-3 py-2 text-slate-600">{{ item.variante_nombre || item.sku || "-" }}</td>
            <td class="px-3 py-2 text-right text-slate-700">{{ money(item.precio_costo) }}</td>
            <td class="px-3 py-2 text-right text-slate-700">{{ money(item.precio_venta) }}</td>
            <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(item.cantidad * item.precio_costo) }}</td>
            <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(item.cantidad * item.precio_venta) }}</td>
            <td class="px-3 py-2 text-right">
              <button type="button" class="rounded-lg p-2 text-rose-600 hover:bg-rose-50" @click="emit('eliminar', idx)">
                <Trash2 class="h-4 w-4" />
              </button>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">Sin partidas agregadas.</td>
          </tr>
        </tbody>
        <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
          <tr>
            <td class="px-3 py-3 font-bold text-slate-900">{{ fmt(totalPiezas) }}</td>
            <td colspan="4" class="px-3 py-3 text-right font-semibold text-slate-700">Totales</td>
            <td class="px-3 py-3 text-right font-bold text-slate-900">{{ money(totalCompra) }}</td>
            <td class="px-3 py-3 text-right font-bold text-slate-900">{{ money(totalVenta) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Trash2 } from "lucide-vue-next";

const props = defineProps({
  items: { type: Array, default: () => [] },
});

const emit = defineEmits(["ajustar-cantidad", "normalizar-cantidad", "eliminar"]);

const totalPiezas = computed(() => props.items.reduce((sum, item) => sum + Number(item.cantidad || 0), 0));
const totalCompra = computed(() => props.items.reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_costo || 0), 0));
const totalVenta = computed(() => props.items.reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_venta || 0), 0));

function fmt(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
}

function money(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
}
</script>
