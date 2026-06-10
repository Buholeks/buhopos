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
        <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
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

            <!-- Productos GENÉRICOS: selección manual obligatoria -->
            <div v-if="item?.pedido_generico" class="rounded-xl border border-amber-200">
              <div class="border-b border-amber-100 bg-amber-50 px-3 py-2">
                <p class="text-xs font-semibold text-amber-800">Pedidos que llegaron en esta compra</p>
                <p class="text-[11px] text-amber-600">Producto de encargo — selecciona manualmente los pedidos que estás recibiendo.</p>
              </div>
              <div v-if="cargandoPedidos" class="px-3 py-4 text-center text-xs text-slate-400">
                Cargando pedidos pendientes...
              </div>
              <div v-else-if="pedidosPendientes.length === 0" class="px-3 py-4 text-center text-xs text-slate-400">
                No hay pedidos pendientes para este producto.
              </div>
              <div v-else class="max-h-48 divide-y divide-slate-100 overflow-y-auto">
                <label
                  v-for="pedido in pedidosPendientes"
                  :key="pedido.id"
                  class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-amber-50"
                >
                  <input v-model="pedidoDetalleIds" type="checkbox" :value="pedido.id" class="mt-1" />
                  <span class="min-w-0 flex-1">
                    <span class="block text-xs font-semibold text-slate-800">
                      {{ pedido.folio }} · {{ pedido.cliente || "Sin cliente" }}
                    </span>
                    <span class="block truncate text-[11px] text-slate-500">
                      {{ pedido.cantidad }} x {{ pedido.descripcion }} · {{ formatMoney(pedido.precio_acordado) }}
                    </span>
                  </span>
                </label>
              </div>
              <!-- Aviso: hay pendientes pero no seleccionó ninguno -->
              <div
                v-if="!cargandoPedidos && pedidosPendientes.length > 0 && pedidoDetalleIds.length === 0"
                class="flex items-start gap-2 border-t border-red-100 bg-red-50 px-3 py-2"
              >
                <Info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-red-600" />
                <p class="text-[11px] text-red-700">
                  Selecciona al menos uno de los <strong>{{ pedidosPendientes.length }} pedido(s)</strong> pendientes para vincular esta compra.
                </p>
              </div>
              <p v-if="cantidadPedidosSeleccionados > cantidad" class="px-3 py-2 text-xs font-semibold text-red-600">
                Los pedidos seleccionados requieren {{ cantidadPedidosSeleccionados }} piezas y la compra captura {{ cantidad }}.
              </p>
            </div>

            <!-- Productos NO genéricos: vinculación automática, solo informar si hay pedidos -->
            <div
              v-else-if="!cargandoPedidos && pedidosPendientes.length > 0"
              class="flex items-start gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5"
            >
              <Info class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
              <p class="text-xs text-emerald-800">
                Se vincularán automáticamente <strong>{{ pedidosPendientes.length }} pedido(s)</strong> pendientes de este producto al guardar la compra.
              </p>
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
              :disabled="!puedeConfirmar"
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
import { computed, ref, watch, nextTick } from "vue";
import { ShoppingCart, X, Info } from "lucide-vue-next";
import BaseInput from "@/components/ui/BaseInput.vue";

const props = defineProps({
  mostrar: { type: Boolean, default: false },
  item: { type: Object, default: null },
  pedidosPendientes: { type: Array, default: () => [] },
  cargandoPedidos: { type: Boolean, default: false },
});

const emit = defineEmits(["confirmar", "cancelar"]);

const inputCantidad = ref(null);
const cantidad = ref(1);
const pedidoDetalleIds = ref([]);
const cantidadPedidosSeleccionados = computed(() =>
  props.pedidosPendientes
    .filter((pedido) => pedidoDetalleIds.value.includes(pedido.id))
    .reduce((total, pedido) => total + Number(pedido.cantidad || 0), 0)
);
const requiereSeleccionPedido = computed(() =>
  Boolean(props.item?.pedido_generico) && props.pedidosPendientes.length > 0
);
const puedeConfirmar = computed(() =>
  !props.cargandoPedidos
  && cantidad.value >= 1
  && cantidadPedidosSeleccionados.value <= cantidad.value
  && (!requiereSeleccionPedido.value || pedidoDetalleIds.value.length > 0)
);

// strings para evitar líos de locale/type=number
const precioCompraRaw = ref("0.00");
const precioVentaRaw = ref("0.00");

watch(
  () => props.mostrar,
  (val) => {
    if (val && props.item) {
      cantidad.value = 1;
      pedidoDetalleIds.value = [];
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

function formatMoney(value) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(value || 0));
}

function confirmar() {
  if (!puedeConfirmar.value) return;

  emit("confirmar", {
    cantidad: Math.round(cantidad.value),
    precio_compra: parseMoney(precioCompraRaw.value),
    precio_venta: parseMoney(precioVentaRaw.value),
    pedido_detalle_ids: pedidoDetalleIds.value,
  });
}
</script>
