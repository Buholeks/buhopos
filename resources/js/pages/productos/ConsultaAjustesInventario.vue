<template>
  <main class="min-h-screen bg-slate-50 p-3 text-slate-900 sm:p-6">
    <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
      <div class="flex items-center gap-3">
        <div class="grid h-11 w-11 place-items-center rounded-xl bg-slate-100 ring-1 ring-slate-200"><ListFilter class="h-5 w-5" /></div>
        <div>
          <h1 class="text-xl font-semibold text-slate-950">Consulta de ajustes de inventario</h1>
          <p class="text-sm text-slate-500">Folios y productos ajustados en la sucursal activa</p>
        </div>
      </div>
      <RouterLink :to="{ name: 'ajuste-rapido-inventario' }" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">
        <PackagePlus class="h-4 w-4" /> Nuevo ajuste
      </RouterLink>
    </header>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(300px,1fr)_180px_180px_130px] xl:items-end">
        <BaseInput v-model="filtros.q" label="Buscar producto o folio" placeholder="Folio, nombre, código, SKU o barras" @keyup.enter="consultar(1)">
          <template #icon><Search class="h-4 w-4" /></template>
        </BaseInput>
        <BaseInput v-model="filtros.desde" label="Desde" type="date" />
        <BaseInput v-model="filtros.hasta" label="Hasta" type="date" />
        <button type="button" class="inline-flex h-[42px] items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800" @click="consultar(1)">
          <Search class="h-4 w-4" /> Consultar
        </button>
      </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-200 p-4">
        <h2 class="font-semibold">Resultados</h2>
        <span class="text-xs text-slate-500">{{ meta.total }} folio(s)</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
            <tr><th class="px-4 py-3">Folio</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Motivo</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3 text-right">Productos</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="item in ajustes" :key="item.id" class="cursor-pointer transition hover:bg-emerald-50/60" @click="abrirDetalle(item.id)">
              <td class="px-4 py-3 font-bold text-emerald-700">{{ item.folio }}</td>
              <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ fecha(item.created_at) }}</td>
              <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="tipoClass(item.tipo)">{{ tipoLabel(item.tipo) }}</span></td>
              <td class="max-w-sm truncate px-4 py-3 text-slate-700">{{ item.motivo }}</td>
              <td class="px-4 py-3 text-slate-600">{{ item.user?.name || "Sin usuario" }}</td>
              <td class="px-4 py-3 text-right font-bold">{{ item.detalles_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="cargando" class="p-10 text-center text-sm text-slate-500"><Loader2 class="mx-auto mb-2 h-5 w-5 animate-spin" />Cargando ajustes...</div>
      <div v-else-if="!ajustes.length" class="p-10 text-center text-sm text-slate-500"><PackageSearch class="mx-auto mb-2 h-8 w-8 text-slate-300" />No hay ajustes con los filtros seleccionados.</div>

      <div v-if="meta.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3">
        <span class="text-xs text-slate-500">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <div class="flex gap-2">
          <button type="button" :disabled="meta.current_page === 1" class="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:opacity-40" @click="consultar(meta.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button>
          <button type="button" :disabled="meta.current_page === meta.last_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:opacity-40" @click="consultar(meta.current_page + 1)"><ChevronRight class="h-4 w-4" /></button>
        </div>
      </div>
    </section>

    <div v-if="detalle" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4" @click.self="cerrarDetalle">
      <section class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-start justify-between border-b border-slate-200 p-5">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-xl font-black">{{ detalle.folio }}</h2>
              <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="tipoClass(detalle.tipo)">{{ tipoLabel(detalle.tipo) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ fecha(detalle.created_at) }} · {{ detalle.usuario }}</p>
            <p class="mt-2 text-sm"><strong>Motivo:</strong> {{ detalle.motivo }}</p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="cerrarDetalle"><X class="h-5 w-5" /></button>
        </header>
        <div class="max-h-[68vh] overflow-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
              <tr><th class="px-4 py-3">Producto</th><th class="px-4 py-3 text-right">Anterior</th><th class="px-4 py-3 text-right">Movimiento</th><th class="px-4 py-3 text-right">Nueva existencia</th><th class="px-4 py-3">Series</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="partida in detalle.detalles" :key="partida.id">
                <td class="px-4 py-3"><strong class="block">{{ partida.nombre }}</strong><span class="text-xs text-slate-500">{{ partida.variante || partida.sku || partida.codigo }}</span></td>
                <td class="px-4 py-3 text-right">{{ numero(partida.stock_antes) }}</td>
                <td class="px-4 py-3 text-right"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="tipoClass(partida.tipo)">{{ partida.tipo === "entrada" ? "+" : "−" }}{{ numero(partida.cantidad) }}</span></td>
                <td class="px-4 py-3 text-right font-black">{{ numero(partida.stock_despues) }}</td>
                <td class="px-4 py-3"><div v-if="partida.series.length" class="flex max-w-xs flex-wrap gap-1"><span v-for="serie in partida.series" :key="serie.id" class="rounded bg-slate-100 px-2 py-1 font-mono text-xs">{{ serie.identificador }}</span></div><span v-else class="text-slate-400">—</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { ChevronLeft, ChevronRight, ListFilter, Loader2, PackagePlus, PackageSearch, Search, X } from "lucide-vue-next";

const hoy = new Date();
const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
const isoLocal = fecha => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, "0")}-${String(fecha.getDate()).padStart(2, "0")}`;
const filtros = reactive({ q: "", desde: isoLocal(inicioMes), hasta: isoLocal(hoy) });
const ajustes = ref([]);
const detalle = ref(null);
const cargando = ref(false);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });

async function consultar(page = 1) {
  cargando.value = true;
  try {
    const { data } = await http.get("/api/inventario-ajustes-rapidos/consulta", { params: { ...filtros, page, por_pagina: 20 } });
    ajustes.value = data.data;
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudieron consultar los ajustes.");
  } finally { cargando.value = false; }
}
async function abrirDetalle(id) {
  try {
    const { data } = await http.get(`/api/inventario-ajustes-rapidos/${id}`);
    detalle.value = data;
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo abrir el ajuste.");
  }
}
function cerrarDetalle() { detalle.value = null; }
const numero = valor => Number(valor || 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
const fecha = valor => valor ? new Date(valor).toLocaleString("es-MX", { dateStyle: "short", timeStyle: "short" }) : "Sin fecha";
const tipoLabel = tipo => ({ entrada: "Entrada", salida: "Salida", mixto: "Mixto" })[tipo] || tipo;
const tipoClass = tipo => tipo === "entrada" ? "bg-emerald-100 text-emerald-700" : tipo === "salida" ? "bg-rose-100 text-rose-700" : "bg-violet-100 text-violet-700";
onMounted(() => consultar(1));
</script>
