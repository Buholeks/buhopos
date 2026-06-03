<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="traspaso"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-3 sm:p-5"
        @mousedown.self="emit('cerrar')"
      >
        <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-semibold text-slate-900">{{ traspaso.folio }}</h2>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="estadoClass(traspaso.estado)">
                  {{ estadoLabel(traspaso.estado) }}
                </span>
              </div>
              <p class="mt-1 text-sm text-slate-500">
                {{ traspaso.origen?.nombre }} -> {{ traspaso.destino?.nombre }} - {{ fmt(traspaso.total_items) }} pieza(s)
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Envio: <span class="font-medium text-slate-700">{{ traspaso.user?.name || "Sin usuario" }}</span>
                <span v-if="traspaso.receptor"> - Recibio: <span class="font-medium text-slate-700">{{ traspaso.receptor.name }}</span></span>
                <span v-if="traspaso.rechazador"> - Rechazo: <span class="font-medium text-slate-700">{{ traspaso.rechazador.name }}</span></span>
                <span v-if="traspaso.cancelador"> - Cancelo: <span class="font-medium text-slate-700">{{ traspaso.cancelador.name }}</span></span>
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button v-if="puedeRecibir" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="emit('seleccionar-pendientes', true)">
                Seleccionar pendientes
              </button>
              <button v-if="puedeRecibir" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="emit('seleccionar-pendientes', false)">
                Limpiar
              </button>
              <button
                v-if="puedeRecibir"
                type="button"
                class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                :disabled="!seleccionados.length || operando"
                @click="emit('recibir-seleccionados', traspaso)"
              >
                Recibir seleccionados
              </button>
              <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="emit('cerrar')">
                <X class="h-4 w-4" />
              </button>
            </div>
          </div>

          <div class="grid gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-4">
            <div>
              <p class="text-xs text-slate-500">Partidas</p>
              <p class="font-bold text-slate-900">{{ fmt(traspaso.detalles?.length || 0) }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Pendientes</p>
              <p class="font-bold text-slate-900">{{ fmt(detallePendientes) }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Valor compra</p>
              <p class="font-bold text-slate-900">{{ money(totalDetalleCompra) }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Valor venta</p>
              <p class="font-bold text-slate-900">{{ money(totalDetalleVenta) }}</p>
            </div>
          </div>

          <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="sticky top-0 z-10 bg-white text-xs uppercase text-slate-500 shadow-sm">
                <tr>
                  <th v-if="puedeRecibir" class="w-10 px-3 py-2 text-left"></th>
                  <th class="px-3 py-2 text-left">Estado</th>
                  <th class="px-3 py-2 text-left">Cant.</th>
                  <th class="px-3 py-2 text-left">Descripcion</th>
                  <th class="px-3 py-2 text-left">Variante</th>
                  <th class="px-3 py-2 text-right">Compra</th>
                  <th class="px-3 py-2 text-right">Venta</th>
                  <th class="px-3 py-2 text-right">Total compra</th>
                  <th class="px-3 py-2 text-right">Total venta</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="d in traspaso.detalles" :key="d.id" :class="d.estado === 'recibido' ? 'bg-emerald-50/60' : ''">
                  <td v-if="puedeRecibir" class="px-3 py-2">
                    <input
                      :checked="!!seleccion[d.id]"
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                      :disabled="d.estado !== 'pendiente'"
                      @change="emit('toggle-seleccion', d.id, $event.target.checked)"
                    />
                  </td>
                  <td class="px-3 py-2">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="detalleEstadoClass(d.estado)">
                      {{ detalleEstadoLabel(d.estado) }}
                    </span>
                  </td>
                  <td class="px-3 py-2 font-semibold text-slate-900">{{ fmt(d.cantidad) }}</td>
                  <td class="px-3 py-2 text-slate-700">
                    {{ d.producto_nombre }}
                    <span v-if="d.serie_identificador" class="block text-xs text-slate-500">{{ d.serie_identificador }}</span>
                  </td>
                  <td class="px-3 py-2 text-slate-600">{{ d.variante_nombre || "-" }}</td>
                  <td class="px-3 py-2 text-right text-slate-700">{{ money(d.precio_costo) }}</td>
                  <td class="px-3 py-2 text-right text-slate-700">{{ money(d.precio_venta) }}</td>
                  <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(d.cantidad * d.precio_costo) }}</td>
                  <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(d.cantidad * d.precio_venta) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from "vue";
import { X } from "lucide-vue-next";

const props = defineProps({
  traspaso: { type: Object, default: null },
  puedeRecibir: { type: Boolean, default: false },
  seleccion: { type: Object, default: () => ({}) },
  seleccionados: { type: Array, default: () => [] },
  operando: { type: Boolean, default: false },
});

const emit = defineEmits([
  "cerrar",
  "seleccionar-pendientes",
  "recibir-seleccionados",
  "toggle-seleccion",
]);

const totalDetalleCompra = computed(() =>
  (props.traspaso?.detalles ?? []).reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_costo || 0), 0)
);
const totalDetalleVenta = computed(() =>
  (props.traspaso?.detalles ?? []).reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_venta || 0), 0)
);
const detallePendientes = computed(() =>
  (props.traspaso?.detalles ?? []).reduce((sum, item) => sum + (item.estado === "pendiente" ? Number(item.cantidad || 0) : 0), 0)
);

function fmt(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
}

function money(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
}

function estadoLabel(estado) {
  return { pendiente: "Pendiente", recibido: "Recibido", rechazado: "Rechazado", cancelado: "Cancelado" }[estado] ?? estado;
}

function estadoClass(estado) {
  return {
    pendiente: "bg-amber-50 text-amber-700 ring-amber-100",
    recibido: "bg-emerald-50 text-emerald-700 ring-emerald-100",
    rechazado: "bg-slate-100 text-slate-700 ring-slate-200",
    cancelado: "bg-rose-50 text-rose-700 ring-rose-100",
  }[estado] ?? "bg-slate-100 text-slate-700 ring-slate-200";
}

function detalleEstadoLabel(estado) {
  return { pendiente: "Pendiente", recibido: "Recibido", rechazado: "Rechazado" }[estado] ?? estado;
}

function detalleEstadoClass(estado) {
  return {
    pendiente: "bg-amber-50 text-amber-700 ring-amber-100",
    recibido: "bg-emerald-50 text-emerald-700 ring-emerald-100",
    rechazado: "bg-slate-100 text-slate-700 ring-slate-200",
  }[estado] ?? "bg-slate-100 text-slate-700 ring-slate-200";
}
</script>
