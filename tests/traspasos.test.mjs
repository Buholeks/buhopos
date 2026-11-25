import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from 'vue/compiler-sfc';
import * as vue from 'vue';

// Execute the actual setup logic with reactive props and a controlled HTTP boundary.
function setup(file, props, names, http = {}) {
  const { descriptor } = parse(readFileSync(new URL('../resources/js/' + file, import.meta.url), 'utf8'));
  const source = descriptor.scriptSetup.content.replace(/^import .*?;$/gm, '');
  const events = [];
  const scope = vue.effectScope();
  const deps = { ...vue, onMounted() {}, onBeforeUnmount() {}, defineProps: () => props,
    defineEmits: () => (...args) => events.push(args), useRouter: () => ({}), useAuthStore: () => ({}), http };
  const result = scope.run(() => new Function(...Object.keys(deps), source + '\nreturn {' + names.join(',') + '};')(...Object.values(deps)));
  return { ...result, events, stop: () => scope.stop() };
}
const producto = { producto_id: 7, variante_id: null, stock: 10, tiene_series: false, nombre: 'Equipo' };
function pagina() {
  return setup('pages/productos/Traspasos.vue', { modo: 'nuevo' }, ['carrito', 'abrirModalItem', 'confirmarModalItem', 'stockDisponible', 'editarSeries', 'cerrarModalItem', 'seriesExcluidas', 'seleccionInicial']);
}
test('capturas repetidas suman aunque los IDs cambien entre texto, número, null y vacío', () => {
  const p = pagina();
  p.abrirModalItem(producto); p.confirmarModalItem({ cantidad: 3 });
  p.abrirModalItem({ ...producto, producto_id: '7', variante_id: '' }); p.confirmarModalItem({ cantidad: 2 });
  assert.equal(p.carrito.value.length, 1); assert.equal(p.carrito.value[0].cantidad, 5);
  assert.equal(p.stockDisponible(producto), 5);
  p.abrirModalItem(producto); p.confirmarModalItem({ cantidad: 6 });
  assert.equal(p.carrito.value[0].cantidad, 5);
  p.abrirModalItem({ ...producto, variante_id: 2 }); p.confirmarModalItem({ cantidad: 1 });
  assert.equal(p.carrito.value.length, 2); p.stop();
});
test('series requieren cantidad exacta y única; editar conserva el grupo hasta confirmar', () => {
  const p = pagina(); const item = { ...producto, tiene_series: true };
  const series = [{ id: 11, identificador: 'A' }, { id: 12, identificador: 'B' }];
  p.abrirModalItem(item); p.confirmarModalItem({ cantidad: 2, series: [series[0], series[0]] });
  assert.equal(p.carrito.value.length, 0);
  p.confirmarModalItem({ cantidad: 2, series }); assert.equal(p.carrito.value.length, 2);
  p.abrirModalItem(item); p.confirmarModalItem({ cantidad: 1, series: [series[0]] });
  assert.equal(p.carrito.value.length, 2);
  p.editarSeries(p.carrito.value[0]); assert.equal(p.stockDisponible(item), 10);
  p.cerrarModalItem(); assert.equal(p.carrito.value.length, 2);
  p.editarSeries(p.carrito.value[0]); p.confirmarModalItem({ cantidad: 1, series: [series[1]] });
  assert.equal(p.carrito.value.length, 1); assert.equal(p.carrito.value[0].serie_id, 12); p.stop();
});
test('modal pide cantidad antes de series y Enter busca el IMEI escaneado', async () => {
  const props = vue.reactive({ item: null, stockDisponible: 10, excluidas: [], seleccionInicial: [], editando: false });
  const requests = [];
  const m = setup('components/traspaso/ModalCantidad.vue', props,
    ['paso', 'cantidad', 'seleccion', 'buscar', 'continuar', 'seleccionarExacta', 'cancelarConsulta'],
    { async get(url, options) { requests.push(options.params); return { data: { data: [{ id: 11, identificador: '123', imei: '123' }], next_page_url: null } }; } });
  props.item = { ...producto, tiene_series: true }; await vue.nextTick();
  m.cantidad.value = 2; await m.continuar(); assert.equal(m.paso.value, 2); assert.equal(m.events.length, 0);
  m.buscar.value = '123'; await vue.nextTick(); await m.seleccionarExacta();
  assert.equal(requests[0].q, '123'); assert.equal(m.seleccion.value.length, 1);
  await m.continuar(); assert.equal(m.events.length, 0);
  m.seleccion.value.push({ id: 12, identificador: '456' }); await m.continuar();
  assert.equal(m.events[0][0], 'confirmar'); assert.equal(m.events[0][1].series.length, 2);
  m.cancelarConsulta(); m.stop();
});
test('las respuestas atrasadas no reemplazan una búsqueda nueva', async () => {
  const props = vue.reactive({ item: producto, stockDisponible: 10, excluidas: [], seleccionInicial: [], editando: false });
  const pending = [];
  const m = setup('components/traspaso/ModalCantidad.vue', props, ['buscar', 'consultar', 'resultados', 'cancelarConsulta'],
    { get() { return new Promise(resolve => pending.push(resolve)); } });
  m.buscar.value = 'vieja'; await vue.nextTick(); const vieja = m.consultar();
  m.buscar.value = 'nueva'; await vue.nextTick(); const nueva = m.consultar();
  pending[1]({ data: { data: [{ id: 2 }], next_page_url: null } }); await nueva;
  pending[0]({ data: { data: [{ id: 1 }], next_page_url: null } }); await vieja;
  assert.equal(m.resultados.value[0].id, 2); m.cancelarConsulta(); m.stop();
});

