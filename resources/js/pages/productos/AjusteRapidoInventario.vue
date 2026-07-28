<template>
  <main class="space-y-6 p-2 sm:p-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="text-2xl font-black text-slate-900">Ajuste rápido de inventario</h1>
      <p class="mt-1 text-sm text-slate-500">Indica la existencia física final de cada producto. El sistema calculará automáticamente la entrada o salida.</p>

      <BaseInput v-model="motivo" root-class="mt-5" label="Motivo del ajuste" placeholder="Describe por qué se realiza este ajuste" maxlength="160" required>
        <template #icon><FileText class="h-4 w-4" /></template>
        <template #suffix><span class="text-xs text-slate-400">{{ motivo.length }}/160</span></template>
      </BaseInput>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="font-black text-slate-900">Productos por ajustar</h2>
      <div ref="buscadorRoot" class="relative mt-3">
        <BaseInput
          v-model="busqueda"
          label="Buscar producto / variante"
          placeholder="Nombre, código, SKU o código de barras"
          autocomplete="off"
          @input="programarBusqueda"
          @keydown.down.prevent="moverCursor(1)"
          @keydown.up.prevent="moverCursor(-1)"
          @keydown.enter.prevent="buscarOSeleccionar"
          @keydown.escape.prevent="cerrarResultados"
        >
          <template #icon><Search class="h-4 w-4" /></template>
          <template #suffix>
            <Loader2 v-if="buscando" class="h-4 w-4 animate-spin text-emerald-500" />
            <button v-else-if="busqueda" type="button" class="text-slate-400 hover:text-slate-700" aria-label="Limpiar búsqueda" @click.stop="limpiarBusqueda"><X class="h-4 w-4" /></button>
          </template>
        </BaseInput>
        <Transition enter-active-class="transition duration-100 ease-out" enter-from-class="translate-y-1 opacity-0" leave-active-class="transition duration-75" leave-to-class="opacity-0">
          <div v-if="dropdown && resultados.length" class="absolute left-0 right-0 z-50 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
            <div class="max-h-72 overflow-y-auto">
              <button
                v-for="(item, index) in resultados"
                :key="item.inventario_id"
                type="button"
                class="flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3 text-left transition"
                :class="cursor === index ? 'bg-emerald-50' : 'hover:bg-slate-50'"
                @mouseenter="cursor = index"
                @click="abrirCantidad(item)"
              >
                <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                  <img v-if="item.imagen_url" :src="item.imagen_url" :alt="item.nombre" class="h-full w-full object-contain" />
                  <ImageOff v-else class="h-5 w-5 text-slate-300" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-bold text-slate-900">{{ item.nombre }}<span v-if="item.variante" class="text-violet-600"> · {{ item.variante }}</span></p>
                  <p class="mt-0.5 truncate font-mono text-xs text-slate-400">{{ item.codigo }}<span v-if="item.sku"> · {{ item.sku }}</span></p>
                </div>
                <div class="shrink-0 text-right"><span class="block text-xs text-slate-400">Existencia</span><strong>{{ numero(item.stock) }}</strong></div>
                <CornerDownLeft class="h-4 w-4 shrink-0" :class="cursor === index ? 'text-emerald-700' : 'text-slate-300'" />
              </button>
            </div>
            <div class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-400">↑↓ Navegar · Enter seleccionar · Esc cerrar</div>
          </div>
        </Transition>
        <div v-if="dropdown && !resultados.length && busqueda.length > 1 && !buscando" class="absolute left-0 right-0 z-50 mt-1 rounded-xl border border-slate-200 bg-white px-4 py-6 text-center shadow-xl">
          <p class="text-sm text-slate-500">Sin resultados para <strong>{{ busqueda }}</strong></p>
        </div>
      </div>

      <div v-if="!partidas.length" class="mt-5 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
        Busca un producto y captura su nueva existencia.
      </div>
      <div v-else class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[700px] text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr><th class="p-3">Producto</th><th class="p-3">Existencia actual</th><th class="p-3">Nueva existencia</th><th class="p-3">Movimiento</th><th class="p-3"></th></tr>
          </thead>
          <tbody>
            <tr v-for="(partida, index) in partidas" :key="partida.inventario_id" class="border-t border-slate-100">
              <td class="p-3"><strong class="block">{{ partida.nombre }}</strong><span class="text-xs text-slate-500">{{ partida.variante || partida.sku || partida.codigo }}</span></td>
              <td class="p-3 font-bold">{{ numero(partida.stock) }}</td>
              <td class="p-3 font-black">{{ numero(partida.nueva_existencia) }}</td>
              <td class="p-3">
                <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="movimiento(partida).tipo === 'entrada' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                  {{ movimiento(partida).texto }}
                </span>
                <span v-if="partida.tiene_series" class="ml-2 text-xs text-slate-500">{{ partida.serie_ids.length }} series</span>
              </td>
              <td class="p-3 text-right">
                <button type="button" class="mr-3 text-indigo-600" @click="abrirCantidad(partida)">Editar</button>
                <button type="button" class="text-rose-600" @click="partidas.splice(index, 1)">Quitar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-5 flex justify-end">
        <button type="button" :disabled="!puedeGuardar || guardando" class="rounded-xl bg-slate-900 px-5 py-3 font-bold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="guardar">
          {{ guardando ? "Aplicando..." : `Aplicar ajuste (${partidas.length})` }}
        </button>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="font-black text-slate-900">Ajustes recientes</h2>
      <div class="mt-3 divide-y divide-slate-100">
        <div v-for="item in historial" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
          <div><strong>{{ item.folio }}</strong><span class="ml-2 text-slate-500">{{ item.motivo }}</span></div>
          <div class="text-xs text-slate-500">{{ item.detalles_count }} productos · {{ etiquetaTipo(item.tipo) }} · {{ item.user?.name }} · {{ fecha(item.created_at) }}</div>
        </div>
      </div>
    </section>

    <div v-if="editor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="cerrarEditor">
      <section class="max-h-[88vh] w-full max-w-xl overflow-auto rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-xl font-black text-slate-900">{{ editor.nombre }}</h3>
        <p class="text-sm text-slate-500">{{ editor.variante || editor.sku || editor.codigo }}</p>

        <div class="mt-5 grid grid-cols-2 gap-4">
          <div class="rounded-xl bg-slate-100 p-4">
            <span class="block text-xs font-bold uppercase text-slate-500">Existencia actual</span>
            <strong class="mt-1 block text-3xl text-slate-900">{{ numero(editor.stock) }}</strong>
          </div>
          <div class="rounded-xl border-2 border-indigo-200 p-3">
            <BaseInput
              v-model="editor.nueva_existencia"
              label="Nueva existencia"
              type="number"
              min="0"
              :step="editor.tiene_series ? 1 : 0.001"
              input-class="text-2xl font-black"
              @input="cambioCantidad"
            >
              <template #icon><Package class="h-4 w-4 text-indigo-600" /></template>
            </BaseInput>
          </div>
        </div>

        <div v-if="editorValido" class="mt-4 rounded-xl p-3 text-center font-bold" :class="deltaEditor > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
          {{ deltaEditor > 0 ? `Entrada de ${numero(deltaEditor)}` : `Salida de ${numero(Math.abs(deltaEditor))}` }}
        </div>
        <p v-else-if="Number(editor.nueva_existencia) === Number(editor.stock)" class="mt-4 text-center text-sm text-amber-700">La nueva existencia debe ser diferente a la actual.</p>

        <div v-if="editor.tiene_series && deltaEditor > 0" class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
          Para aumentar este producto es necesario registrar los nuevos IMEI o números de serie desde el módulo de Series.
        </div>

        <div v-if="editor.tiene_series && deltaEditor < 0" class="mt-5">
          <div class="flex items-center justify-between">
            <h4 class="font-black">Series que saldrán</h4>
            <span class="text-xs font-bold">{{ editor.serie_ids.length }} / {{ Math.abs(deltaEditor) }}</span>
          </div>
          <div class="mt-2 max-h-64 space-y-2 overflow-auto">
            <label v-for="serie in seriesDisponibles" :key="serie.id" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
              <input v-model="editor.serie_ids" type="checkbox" :value="serie.id" :disabled="!editor.serie_ids.includes(serie.id) && editor.serie_ids.length >= Math.abs(deltaEditor)" />
              <span class="font-mono text-sm">{{ serie.identificador }}</span>
            </label>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 font-bold" @click="cerrarEditor">Cancelar</button>
          <button type="button" :disabled="!puedeAgregarEditor" class="rounded-xl bg-slate-900 px-4 py-2 font-bold text-white disabled:opacity-40" @click="confirmarEditor">
            {{ existeEnLista ? "Actualizar" : "Agregar" }}
          </button>
        </div>
      </section>
    </div>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import http from "@/lib/http";
import BaseInput from "@/components/ui/BaseInput.vue";
import Swal from "sweetalert2";
import { toastSuccess } from "@/lib/alert";
import { CornerDownLeft, FileText, ImageOff, Loader2, Package, Search, X } from "lucide-vue-next";

const motivo = ref("");
const busqueda = ref("");
const buscando = ref(false);
const resultados = ref([]);
const partidas = ref([]);
const historial = ref([]);
const guardando = ref(false);
const editor = ref(null);
const seriesDisponibles = ref([]);
const buscadorRoot = ref(null);
const dropdown = ref(false);
const cursor = ref(0);
let timer;
let requestSeq = 0;

const deltaEditor = computed(() => editor.value ? Number(editor.value.nueva_existencia) - Number(editor.value.stock) : 0);
const editorValido = computed(() => editor.value && Number(editor.value.nueva_existencia) >= 0 && deltaEditor.value !== 0);
const existeEnLista = computed(() => editor.value && partidas.value.some(p => p.inventario_id === editor.value.inventario_id));
const puedeAgregarEditor = computed(() => {
  if (!editorValido.value) return false;
  if (!editor.value.tiene_series) return true;
  if (deltaEditor.value > 0 || !Number.isInteger(Math.abs(deltaEditor.value))) return false;
  return editor.value.serie_ids.length === Math.abs(deltaEditor.value);
});
const puedeGuardar = computed(() => motivo.value.length > 0 && partidas.value.length > 0);
const numero = value => Number(value || 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
const fecha = value => new Date(value).toLocaleString("es-MX");
const etiquetaTipo = tipo => tipo === "mixto" ? "Entradas y salidas" : tipo === "entrada" ? "Entrada" : "Salida";
const movimiento = p => {
  const delta = Number(p.nueva_existencia) - Number(p.stock);
  return { tipo: delta > 0 ? "entrada" : "salida", texto: `${delta > 0 ? "Entrada" : "Salida"} de ${numero(Math.abs(delta))}` };
};

function programarBusqueda() {
  clearTimeout(timer);
  resultados.value = [];
  cursor.value = 0;
  dropdown.value = busqueda.value.length >= 2;
  if (busqueda.value.length >= 2) timer = setTimeout(buscar, 250);
}
async function buscar() {
  if (busqueda.value.length < 2) return;
  const seq = ++requestSeq;
  buscando.value = true;
  try {
    const { data } = await http.get("/api/inventario-ajustes-rapidos/buscar", { params: { q: busqueda.value } });
    if (seq !== requestSeq) return;
    resultados.value = data;
    cursor.value = Math.min(cursor.value, Math.max(data.length - 1, 0));
    dropdown.value = true;
  } finally {
    if (seq === requestSeq) buscando.value = false;
  }
}
function moverCursor(direccion) {
  if (!resultados.value.length) return;
  dropdown.value = true;
  cursor.value = (cursor.value + direccion + resultados.value.length) % resultados.value.length;
}
function buscarOSeleccionar() {
  if (dropdown.value && resultados.value[cursor.value]) abrirCantidad(resultados.value[cursor.value]);
  else buscar();
}
function cerrarResultados() {
  dropdown.value = false;
}
function limpiarBusqueda() {
  requestSeq++;
  busqueda.value = "";
  resultados.value = [];
  dropdown.value = false;
  buscando.value = false;
}
async function abrirCantidad(item) {
  const existente = partidas.value.find(p => p.inventario_id === item.inventario_id);
  editor.value = { ...(existente || item), nueva_existencia: existente?.nueva_existencia ?? item.stock, serie_ids: [...(existente?.serie_ids || [])] };
  limpiarBusqueda();
  if (editor.value.tiene_series) await cargarSeries();
}
async function cargarSeries() {
  const { data } = await http.get("/api/inventario-ajustes-rapidos/series", { params: { producto_id: editor.value.producto_id, variante_id: editor.value.variante_id } });
  seriesDisponibles.value = data;
}
function cambioCantidad() {
  if (!editor.value) return;
  if (editor.value.tiene_series) editor.value.nueva_existencia = Math.max(0, Math.trunc(Number(editor.value.nueva_existencia) || 0));
  editor.value.serie_ids = [];
}
function confirmarEditor() {
  const index = partidas.value.findIndex(p => p.inventario_id === editor.value.inventario_id);
  if (index >= 0) partidas.value[index] = { ...editor.value };
  else partidas.value.push({ ...editor.value });
  cerrarEditor();
}
function cerrarEditor() {
  editor.value = null;
  seriesDisponibles.value = [];
}
async function cargarHistorial() {
  const { data } = await http.get("/api/inventario-ajustes-rapidos/historial");
  historial.value = data;
}
async function guardar() {
  const resumen = partidas.value.map(p => `${p.nombre}: ${numero(p.stock)} → ${numero(p.nueva_existencia)}`).join("\n");
  const confirmacion = await Swal.fire({
    icon: "warning",
    title: "¿Aplicar ajuste de inventario?",
    html: `
      <div class="text-left">
        <p class="mb-3 text-sm"><strong>Motivo:</strong> ${escaparHtml(motivo.value)}</p>
        <div class="max-h-60 overflow-auto rounded-lg bg-slate-50 p-3 text-sm">
          ${partidas.value.map(p => `<div class="flex justify-between gap-4 border-b border-slate-200 py-2 last:border-0"><span>${escaparHtml(p.nombre)}</span><strong>${numero(p.stock)} → ${numero(p.nueva_existencia)}</strong></div>`).join("")}
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: "Sí, aplicar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });
  if (!confirmacion.isConfirmed) return;
  guardando.value = true;
  try {
    const { data } = await http.post("/api/inventario-ajustes-rapidos", {
      motivo: motivo.value,
      partidas: partidas.value.map(p => ({ producto_id: p.producto_id, variante_id: p.variante_id, nueva_existencia: p.nueva_existencia, serie_ids: p.serie_ids })),
    });
    toastSuccess(`${data.message} Folio: ${data.folio}`);
    partidas.value = [];
    motivo.value = "";
    await cargarHistorial();
  } finally { guardando.value = false; }
}
function escaparHtml(valor) {
  return String(valor ?? "").replace(/[&<>"']/g, caracter => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[caracter]);
}
function cerrarAlHacerClickFuera(evento) {
  if (buscadorRoot.value && !buscadorRoot.value.contains(evento.target)) cerrarResultados();
}
onMounted(() => {
  cargarHistorial();
  document.addEventListener("mousedown", cerrarAlHacerClickFuera);
});
onBeforeUnmount(() => {
  clearTimeout(timer);
  requestSeq++;
  document.removeEventListener("mousedown", cerrarAlHacerClickFuera);
});
</script>
