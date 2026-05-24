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
        v-if="mostrar"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
        @mousedown.self="emit('cancelar')"
      >
        <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <!-- Header -->
          <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
              <ShoppingCart class="h-4 w-4" />
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-900">
                {{ item?.nombre }}
              </p>
              <p v-if="item?.nombre_variante" class="truncate text-xs font-semibold text-emerald-700">
                {{ item.nombre_variante }}
              </p>
              <p v-else class="text-xs text-slate-400">
                {{ item?.codigo }}
              </p>
            </div>

            <button
              type="button"
              @click="emit('cancelar')"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"
              aria-label="Cerrar"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="space-y-4 px-5 py-5">
            <!-- Cantidad (big POS) -->
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Cantidad
              </label>

              <input
                ref="inputCantidad"
                v-model.number="cantidad"
                type="number"
                min="1"
                step="1"
                inputmode="numeric"
                @keydown.enter="confirmar"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-2xl font-extrabold text-slate-900 outline-none
                       focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              />

              <p class="mt-1 text-[11px] text-slate-400">Tip: Enter para confirmar.</p>
            </div>

            <!-- Precio compra -->
            <BaseInput
              v-model="precioCompraRaw"
              label="Precio de compra"
              type="text"
              inputmode="decimal"
              placeholder="0.00"
              prefix="$"
              :rootClass="'[&_.mb-1]:mb-1'"
              inputClass="text-right font-mono tabular-nums"
              @keydown.enter="confirmar"
              @blur="precioCompraRaw = toMoneyString(parseMoney(precioCompraRaw))"
            />

            <!-- Precio venta -->
            <BaseInput
              v-model="precioVentaRaw"
              label="Precio de venta"
              type="text"
              inputmode="decimal"
              placeholder="0.00"
              prefix="$"
              inputClass="text-right font-mono tabular-nums"
              @keydown.enter="confirmar"
              @blur="precioVentaRaw = toMoneyString(parseMoney(precioVentaRaw))"
            />

            <!-- Nota visual (opcional) -->
            <div class="-mt-1 flex items-center gap-2 text-xs text-slate-400">
              <Info class="h-4 w-4" />
              <span>Compra y venta se formatean a 2 decimales al salir del campo.</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
            <button
              type="button"
              @click="emit('cancelar')"
              class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Cancelar
            </button>

            <button
              type="button"
              @click="confirmar"
              :disabled="!cantidad || cantidad < 1"
              class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700
                     disabled:cursor-not-allowed disabled:opacity-50"
            >
              {{ item?.tiene_series ? "Siguiente →" : "Agregar" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";
import { ShoppingCart, X, Info } from "lucide-vue-next";
import BaseInput from "@/components/ui/BaseInput.vue";

const props = defineProps({
  mostrar: { type: Boolean, default: false },
  item: { type: Object, default: null },
});

const emit = defineEmits(["confirmar", "cancelar"]);

const inputCantidad = ref(null);
const cantidad = ref(1);

// strings para evitar líos de locale/type=number
const precioCompraRaw = ref("0.00");
const precioVentaRaw = ref("0.00");

watch(
  () => props.mostrar,
  (val) => {
    if (val && props.item) {
      cantidad.value = 1;
      precioCompraRaw.value = toMoneyString(props.item.precio_compra ?? 0);
      precioVentaRaw.value = toMoneyString(props.item.precio_venta ?? 0);
      nextTick(() => inputCantidad.value?.select());
    }
  }
);

function toMoneyString(v) {
  const n = Number(v);
  if (!Number.isFinite(n)) return "0.00";
  return n.toFixed(2);
}

function parseMoney(raw) {
  // permite 10,50 o 10.50 y limpia símbolos
  const s = String(raw ?? "")
    .trim()
    .replace(/[^\d.,-]/g, "")
    .replace(",", ".");
  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}

function confirmar() {
  if (!cantidad.value || cantidad.value < 1) return;

  emit("confirmar", {
    cantidad: Math.round(cantidad.value),
    precio_compra: parseMoney(precioCompraRaw.value),
    precio_venta: parseMoney(precioVentaRaw.value),
  });
}
</script>