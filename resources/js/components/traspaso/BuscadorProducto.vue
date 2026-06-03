<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div ref="root" class="relative">
      <BaseInput
        v-model="buscar"
        label="Buscar mercancia en sucursal actual"
        placeholder="Producto, codigo, SKU o codigo de barras"
        @focus="dropdown = resultados.length > 0"
        @keydown.down.prevent="moverCursor(1)"
        @keydown.up.prevent="moverCursor(-1)"
        @keydown.enter.prevent="seleccionarCursor"
        @keydown.esc.prevent="cerrar"
      >
        <template #icon><Search class="h-4 w-4" /></template>
        <template #suffix>
          <button
            type="button"
            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
            @click="buscarInventario"
          >
            Buscar
          </button>
        </template>
      </BaseInput>

      <div
        v-if="dropdown"
        class="absolute z-30 mt-2 max-h-[420px] w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-xl"
      >
        <button
          v-for="(item, idx) in resultados"
          :key="itemKey(item)"
          type="button"
          class="grid w-full gap-3 border-b border-slate-100 p-3 text-left transition last:border-b-0 sm:grid-cols-[48px_minmax(0,1fr)_150px]"
          :class="cursor === idx ? 'bg-emerald-50' : 'hover:bg-emerald-50'"
          @mouseenter="cursor = idx"
          @click="seleccionar(item)"
        >
          <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
            <img v-if="item.imagen_url" :src="item.imagen_url" alt="" class="h-full w-full object-cover" />
            <Package v-else class="h-5 w-5 text-slate-400" />
          </div>
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="truncate text-sm font-semibold text-slate-900">{{ item.nombre }}</p>
              <span
                v-if="item.tiene_series"
                class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-100"
              >
                Serie/IMEI
              </span>
            </div>
            <p v-if="item.variante_nombre || item.sku" class="mt-0.5 truncate text-xs text-slate-600">
              {{ item.variante_nombre || item.sku }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              {{ item.codigo || "Sin codigo" }}
              <span v-if="item.codigo_barras"> - {{ item.codigo_barras }}</span>
            </p>
          </div>
          <div class="text-xs text-slate-600 sm:text-right">
            <p class="font-semibold text-slate-900">Stock: {{ fmt(item.stock) }}</p>
            <p>Costo: {{ money(item.precio_costo) }}</p>
            <p>Venta: {{ money(item.precio_venta) }}</p>
          </div>
        </button>

        <div v-if="buscando" class="p-4 text-center text-sm text-slate-500">Buscando...</div>
        <div v-if="!buscando && !resultados.length" class="p-4 text-center text-sm text-slate-500">
          Sin resultados.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import { Package, Search } from "lucide-vue-next";

const emit = defineEmits(["seleccionar"]);

const root = ref(null);
const buscar = ref("");
const resultados = ref([]);
const buscando = ref(false);
const dropdown = ref(false);
const cursor = ref(0);
let timer = null;

watch(buscar, (value) => {
  clearTimeout(timer);
  if (!String(value || "").trim()) {
    resultados.value = [];
    dropdown.value = false;
    return;
  }
  timer = setTimeout(() => buscarInventario(), 260);
});

onMounted(() => document.addEventListener("click", cerrarExterno));
onBeforeUnmount(() => document.removeEventListener("click", cerrarExterno));

function itemKey(item) {
  return `${item.producto_id}:${item.variante_id ?? "null"}`;
}

function fmt(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
}

function money(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
}

async function buscarInventario() {
  buscando.value = true;
  try {
    const { data } = await http.get("/api/traspasos/inventario", { params: { buscar: buscar.value } });
    resultados.value = data;
    cursor.value = 0;
    dropdown.value = true;
  } finally {
    buscando.value = false;
  }
}

function moverCursor(delta) {
  if (!dropdown.value || !resultados.value.length) return;
  cursor.value = (cursor.value + delta + resultados.value.length) % resultados.value.length;
}

function seleccionarCursor() {
  if (!dropdown.value || !resultados.value.length) {
    buscarInventario();
    return;
  }
  seleccionar(resultados.value[cursor.value]);
}

function seleccionar(item) {
  emit("seleccionar", item);
  buscar.value = "";
  dropdown.value = false;
}

function cerrar() {
  dropdown.value = false;
}

function cerrarExterno(e) {
  if (!dropdown.value || !root.value) return;
  if (!root.value.contains(e.target)) cerrar();
}

defineExpose({ buscarInventario });
</script>
