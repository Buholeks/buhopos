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
        v-if="item"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
        @mousedown.self="emit('cerrar')"
      >
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
              <img v-if="item.imagen_url" :src="item.imagen_url" alt="" class="h-full w-full object-cover" />
              <Package v-else class="h-5 w-5 text-slate-400" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-900">{{ item.nombre }}</p>
              <p class="truncate text-xs text-slate-500">{{ item.variante_nombre || item.sku || "Sin variante" }}</p>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="emit('cerrar')">
              <X class="h-4 w-4" />
            </button>
          </div>

          <div class="space-y-4 px-5 py-5">
            <div class="grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-3 text-xs">
              <div>
                <p class="text-slate-500">Disponible</p>
                <p class="font-bold text-slate-900">{{ fmt(stockDisponible) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Compra</p>
                <p class="font-bold text-slate-900">{{ money(item.precio_costo) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Venta</p>
                <p class="font-bold text-slate-900">{{ money(item.precio_venta) }}</p>
              </div>
            </div>

            <div v-if="item.tiene_series">
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Serie/IMEI
              </label>
              <select
                ref="serieRef"
                v-model.number="serieId"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                @keydown.enter.prevent="confirmar"
              >
                <option value="">Selecciona serie</option>
                <option v-for="serie in series" :key="serie.id" :value="serie.id">
                  {{ serie.identificador }}
                </option>
              </select>
            </div>

            <div v-else>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Cantidad
              </label>
              <input
                ref="cantidadRef"
                v-model.number="cantidad"
                type="number"
                min="0.001"
                step="0.001"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-2xl font-extrabold text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                @keydown.enter.prevent="confirmar"
              />
            </div>
          </div>

          <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="emit('cerrar')">
              Cancelar
            </button>
            <button
              type="button"
              class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
              :disabled="!puedeConfirmar"
              @click="confirmar"
            >
              Agregar
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { Package, X } from "lucide-vue-next";

const props = defineProps({
  item: { type: Object, default: null },
  series: { type: Array, default: () => [] },
  stockDisponible: { type: Number, default: 0 },
});

const emit = defineEmits(["confirmar", "cerrar"]);

const cantidad = ref(1);
const serieId = ref("");
const cantidadRef = ref(null);
const serieRef = ref(null);

const puedeConfirmar = computed(() => {
  if (!props.item) return false;
  if (props.item.tiene_series) return !!serieId.value;
  return Number(cantidad.value) > 0 && Number(cantidad.value) <= props.stockDisponible;
});

watch(
  () => props.item,
  async (item) => {
    if (!item) return;
    cantidad.value = Math.min(1, props.stockDisponible);
    serieId.value = "";
    await nextTick();
    if (item.tiene_series) serieRef.value?.focus();
    else cantidadRef.value?.select();
  }
);

function fmt(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
}

function money(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
}

function confirmar() {
  if (!puedeConfirmar.value) return;
  emit("confirmar", {
    cantidad: Number(cantidad.value),
    serieId: serieId.value ? Number(serieId.value) : null,
  });
}
</script>
