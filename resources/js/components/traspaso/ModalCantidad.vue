<template>
  <Teleport to="body">
    <div v-if="item" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @mousedown.self="emit('cerrar')" @keydown.esc.prevent="emit('cerrar')">
      <section ref="dialogRef" @keydown.tab="mantenerFoco" role="dialog" aria-modal="true" aria-labelledby="traspaso-modal-title" class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-start justify-between border-b border-slate-100 p-5">
          <div>
            <h2 id="traspaso-modal-title" class="font-semibold text-slate-900">{{ item.nombre }}</h2>
            <p class="text-sm text-slate-500">{{ item.variante_nombre || item.sku || 'Sin variante' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ paso === 1 ? 'Cantidad' : 'Seleccionar IMEI / series' }} · Disponible: {{ stockDisponible }}</p>
          </div>
          <button type="button" aria-label="Cerrar" class="rounded-lg p-2 hover:bg-slate-100" @click="emit('cerrar')"><X class="h-4 w-4" /></button>
        </header>
        <div class="space-y-4 overflow-y-auto p-5">
          <template v-if="paso === 1">
            <label for="traspaso-cantidad" class="block text-sm font-semibold">{{ editando ? 'Cantidad total' : 'Cantidad a agregar' }}</label>
            <input id="traspaso-cantidad" ref="cantidadRef" v-model.number="cantidad" type="number" :min="item.tiene_series ? 1 : 0.001" :step="item.tiene_series ? 1 : 0.001" :max="stockDisponible" class="w-full rounded-xl border border-slate-200 p-3 text-center text-2xl font-bold outline-none focus:border-emerald-500" @keydown.enter.prevent="continuar" />
            <p v-if="!editando" class="text-sm text-slate-500">Ya en el traspaso: {{ Math.max(0, Number(item.stock || 0) - stockDisponible) }}</p>
            <p v-if="!cantidadValida" role="status" class="text-sm text-amber-700">Ingresa una cantidad {{ item.tiene_series ? 'entera ' : '' }}mayor que cero y no mayor a {{ stockDisponible }}.</p>
          </template>
          <template v-else>
            <p class="font-semibold" aria-live="polite">Seleccionados: {{ seleccion.length }} de {{ cantidad }}</p>
            <label for="traspaso-serie" class="block text-sm font-medium">Buscar o escanear IMEI / número de serie</label>
            <input id="traspaso-serie" ref="buscarRef" v-model="buscar" autocomplete="off" placeholder="IMEI, IMEI secundario o serie" class="w-full rounded-xl border border-slate-200 p-3 outline-none focus:border-emerald-500" @keydown.enter.prevent="seleccionarExacta" @keydown.down.prevent="mover(1)" @keydown.up.prevent="mover(-1)" />
            <p v-if="!buscar.trim()" class="text-sm text-slate-500">Escribe para buscar entre los equipos disponibles.</p>
            <p v-if="cargando" role="status" class="text-sm text-slate-500">Buscando...</p>
            <div v-if="error" role="alert" class="text-sm text-red-600">{{ error }} <button type="button" class="underline" @click="consultar(false)">Reintentar</button></div>
            <p v-if="!cargando && consultado && !resultados.length && !error" class="text-sm text-slate-500">Sin coincidencias disponibles.</p>
            <div v-if="resultados.length" class="max-h-48 overflow-auto rounded-xl border border-slate-200">
              <button v-for="(serie, index) in resultados" :key="serie.id" type="button" :disabled="seleccion.length >= cantidad" class="flex w-full items-center justify-between gap-3 border-b border-slate-100 p-3 text-left text-sm disabled:opacity-40" :class="cursor === index ? 'bg-emerald-50' : 'hover:bg-slate-50'" @click="seleccionar(serie)">
                <span class="break-all">{{ serie.identificador }}<span v-if="serie.imei2" class="block text-xs text-slate-500">IMEI 2: {{ serie.imei2 }}</span></span><span class="text-emerald-700">Seleccionar</span>
              </button>
            </div>
            <button v-if="hayMas" type="button" :disabled="cargando" class="text-sm font-semibold text-emerald-700" @click="consultar(true)">Cargar más coincidencias</button>
            <div v-if="seleccion.length" class="space-y-2">
              <h3 class="text-sm font-semibold">Equipos seleccionados</h3>
              <div v-for="serie in seleccion" :key="serie.id" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-2 text-sm">
                <span class="break-all">{{ serie.identificador }}</span><button type="button" class="text-red-600" :aria-label="'Quitar ' + serie.identificador" @click="quitar(serie)">Quitar</button>
              </div>
            </div>
            <p v-if="seleccion.length > cantidad" role="alert" class="text-sm text-amber-700">Quita {{ seleccion.length - cantidad }} equipo(s) para completar la nueva cantidad.</p>
          </template>
        </div>
        <footer class="flex gap-2 border-t border-slate-100 p-5">
          <button type="button" class="flex-1 rounded-xl border border-slate-200 p-3 text-sm font-semibold" @click="paso === 2 ? volver() : emit('cerrar')">{{ paso === 2 ? 'Volver a cantidad' : 'Cancelar' }}</button>
          <button type="button" class="flex-1 rounded-xl bg-emerald-600 p-3 text-sm font-semibold text-white disabled:opacity-50" :disabled="!cantidadValida || (paso === 2 && seleccion.length !== cantidad)" @click="continuar">{{ paso === 1 && item.tiene_series ? 'Continuar a IMEI' : editando ? 'Guardar cambios' : `Agregar ${cantidadValida ? cantidad : ''} al traspaso` }}</button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';
import http from '@/lib/http';
const props = defineProps({
  item: { type: Object, default: null },
  stockDisponible: { type: Number, default: 0 },
  excluidas: { type: Array, default: () => [] },
  seleccionInicial: { type: Array, default: () => [] },
  editando: Boolean,
});
const emit = defineEmits(['confirmar', 'cerrar']);
const paso = ref(1), cantidad = ref(1), seleccion = ref([]), buscar = ref('');
const resultados = ref([]), cargando = ref(false), error = ref(''), hayMas = ref(false), consultado = ref(false), cursor = ref(-1);
const cantidadRef = ref(null), buscarRef = ref(null), dialogRef = ref(null);
let timer, controller, revision = 0, pagina = 0;
const cantidadValida = computed(() => Number.isFinite(cantidad.value) && cantidad.value > 0 && cantidad.value <= props.stockDisponible && (!props.item?.tiene_series || Number.isInteger(cantidad.value)));
function mantenerFoco(event) {
  const elementos = [...(dialogRef.value?.querySelectorAll('button:not([disabled]), input:not([disabled]), [tabindex="0"]') || [])];
  if (!elementos.length) return;
  const primero = elementos[0], ultimo = elementos[elementos.length - 1];
  if (event.shiftKey && event.target === primero) { event.preventDefault(); ultimo.focus(); }
  else if (!event.shiftKey && event.target === ultimo) { event.preventDefault(); primero.focus(); }
}
function cancelarConsulta() { clearTimeout(timer); revision++; controller?.abort(); cargando.value = false; }
watch(() => props.item, async item => {
  cancelarConsulta();
  paso.value = 1; buscar.value = ''; resultados.value = []; hayMas.value = false; error.value = ''; consultado.value = false;
  seleccion.value = props.seleccionInicial.map(s => ({ ...s }));
  cantidad.value = props.editando ? seleccion.value.length : Math.min(1, props.stockDisponible);
  if (item) { await nextTick(); cantidadRef.value?.select(); }
});
watch(buscar, () => {
  cancelarConsulta(); resultados.value = []; hayMas.value = false; consultado.value = false; error.value = ''; cursor.value = -1;
  if (buscar.value.trim()) timer = setTimeout(() => consultar(false), 260);
});
onBeforeUnmount(cancelarConsulta);
async function consultar(mas = false) {
  if (!props.item || !buscar.value.trim()) return;
  cancelarConsulta(); const actual = revision;
  controller = new AbortController(); cargando.value = true; error.value = '';
  const siguiente = mas ? pagina + 1 : 1;
  try {
    const { data } = await http.get('/api/traspasos/series-disponibles', { signal: controller.signal, params: {
      producto_id: props.item.producto_id, variante_id: props.item.variante_id || '', q: buscar.value.trim(), paginado: 1, page: siguiente,
      excluir: [...new Set([...props.excluidas, ...seleccion.value.map(s => Number(s.id))])],
    } });
    if (actual !== revision) return;
    const omitidas = new Set([...props.excluidas, ...seleccion.value.map(s => Number(s.id))]);
    const nuevas = data.data.filter(s => !omitidas.has(Number(s.id)));
    resultados.value = mas ? [...new Map([...resultados.value, ...nuevas].map(s => [s.id, s])).values()] : nuevas;
    hayMas.value = !!data.next_page_url; pagina = siguiente; consultado.value = true; cursor.value = -1;
  } catch (e) {
    if (actual === revision && e.code !== 'ERR_CANCELED') error.value = 'No se pudieron consultar las series.';
  } finally { if (actual === revision) cargando.value = false; }
}
function seleccionar(serie) {
  if (seleccion.value.length >= cantidad.value || seleccion.value.some(s => Number(s.id) === Number(serie.id)) || props.excluidas.includes(Number(serie.id))) return;
  seleccion.value.push(serie); buscar.value = ''; resultados.value = []; buscarRef.value?.focus();
}
async function seleccionarExacta() {
  if (cursor.value < 0) await consultar(false);
  if (cursor.value >= 0 && resultados.value[cursor.value]) return seleccionar(resultados.value[cursor.value]);
  const texto = buscar.value.trim().toLowerCase();
  const exactas = resultados.value.filter(s => [s.identificador, s.imei, s.imei2, s.serie].some(v => v && String(v).toLowerCase() === texto));
  if (exactas.length === 1) seleccionar(exactas[0]);
  else consultar(false);
}
function mover(delta) {
  if (resultados.value.length) cursor.value = (cursor.value + delta + resultados.value.length) % resultados.value.length;
}
function quitar(serie) {
  seleccion.value = seleccion.value.filter(s => Number(s.id) !== Number(serie.id));
  if (buscar.value.trim()) consultar(false);
}
async function volver() { cancelarConsulta(); paso.value = 1; await nextTick(); cantidadRef.value?.select(); }
async function continuar() {
  if (!cantidadValida.value) return;
  if (paso.value === 1 && props.item.tiene_series) { paso.value = 2; await nextTick(); buscarRef.value?.focus(); return; }
  if (props.item.tiene_series && seleccion.value.length !== cantidad.value) return;
  emit('confirmar', { cantidad: cantidad.value, series: [...seleccion.value] });
}
</script>
