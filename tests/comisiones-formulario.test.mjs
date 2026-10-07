import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { ref, computed, watch, nextTick } from 'vue';

function reporte() {
    const source = readFileSync(new URL('../resources/js/pages/comisiones/ComisionesReporte.vue', import.meta.url), 'utf8');
    const script = source.split('<script setup>')[1].split('</script>')[0].replace(/^import[\s\S]*?;\s*$/gm, '');
    const pendientes = [];
    const http = { get: () => new Promise((resolve, reject) => pendientes.push({ resolve, reject })) };
    const setup = new Function('ref', 'computed', 'http', 'toastError', 'useAuthStore',
        script + '\nreturn {cambiarPeriodo, abrirDetalle, cerrarDetalle, detalle, empleados, errorReporte};');
    return { ...setup(ref, computed, http, () => {}, () => ({})), pendientes };
}

test('el reporte ignora respuestas antiguas y no reabre un detalle cerrado', async () => {
    const r = reporte();
    r.cambiarPeriodo({ desde: '2026-10-01', hasta: '2026-10-15' });
    r.cambiarPeriodo({ desde: '2026-10-16', hasta: '2026-10-31' });
    r.pendientes[1].resolve({ data: { empleados: [{ nombre: 'Actual' }], totales: {}, sucursales: [] } });
    await Promise.resolve();
    r.pendientes[0].resolve({ data: { empleados: [{ nombre: 'Anterior' }], totales: {}, sucursales: [] } });
    await Promise.resolve();
    assert.equal(r.empleados.value[0].nombre, 'Actual');
    r.abrirDetalle({ user_id: 1, nombre: 'Ana' });
    r.cerrarDetalle();
    r.pendientes[2].resolve({ data: { empleado: { name: 'Ana' } } });
    await Promise.resolve();
    assert.equal(r.detalle.value, null);
});

test('un error del reporte no conserva resultados del periodo previo', async () => {
    const r = reporte();
    r.cambiarPeriodo({ desde: '2026-10-01', hasta: '2026-10-15' });
    r.pendientes[0].resolve({ data: { empleados: [{ nombre: 'Ana' }], totales: {}, sucursales: [] } });
    await Promise.resolve();
    r.cambiarPeriodo({ desde: '2026-10-16', hasta: '2026-10-31' });
    r.pendientes[1].reject(new Error('Sin conexión'));
    await Promise.resolve();
    assert.equal(r.empleados.value.length, 0);
    assert.ok(r.errorReporte.value);
});

test('abrir una regla conserva el cero explícito por lista', async () => {
    const source = readFileSync(new URL('../resources/js/components/comisiones/GrupoModal.vue', import.meta.url), 'utf8');
    const script = source.split('<script setup>')[1].split('</script>')[0]
        .replace(/^import[\s\S]*?;\s*$/gm, '');
    const props = { open: false, grupo: {
        referencia_id: null, base: 'venta', tipo: 'porcentaje', forma: 'unico', valor: 5,
        rangos: [], hasta_final: null, fuera_de_rango: 'usar_siguiente',
        precios: [{ lista: 'P1', activo: true, tipo: 'porcentaje', valor: 0 }],
    }};
    const { reactive } = await import('vue');
    const reactiveProps = reactive(props);
    const setup = new Function('ref', 'computed', 'watch', 'defineProps', 'defineEmits', 'LISTAS_PRECIO', 'Percent', 'Tag',
        script + '\nreturn {precios, payload};');
    const form = setup(ref, computed, watch, () => reactiveProps, () => () => {}, ['P1', 'P2'], {}, {});
    reactiveProps.open = true;
    await nextTick();
    assert.equal(form.precios.value[0].valor, 0);
    assert.equal(form.precios.value[1].valor, 5);
    assert.equal(form.payload().precios[0].valor, 0);
});
