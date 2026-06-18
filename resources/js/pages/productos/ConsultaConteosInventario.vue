<template>
  <main class="min-h-screen bg-slate-50 p-3 text-slate-900 sm:p-6">
    <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex items-center gap-3">
        <div class="grid h-11 w-11 place-items-center rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200">
          <ClipboardList class="h-5 w-5" />
        </div>
        <div>
          <h1 class="text-xl font-semibold text-slate-950">Consulta de conteos</h1>
          <p class="text-sm text-slate-500">Historial de conteos fisicos de la sucursal activa</p>
        </div>
      </div>

      <RouterLink
        :to="{ name: 'conteo-inventario' }"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
      >
        <ClipboardCheck class="h-4 w-4" />
        Ir a conteo
      </RouterLink>
    </header>

    <section class="mt-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_170px_160px_160px_130px] xl:items-end">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Buscar</span>
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model.trim="filtros.q"
              type="text"
              class="h-10 w-full rounded-lg border border-slate-200 pl-10 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              placeholder="Folio o notas"
              @keyup.enter="cargarConteos"
            />
          </div>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Estado</span>
          <select v-model="filtros.estado" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
            <option value="">Todos</option>
            <option value="en_conteo">En captura</option>
            <option value="en_revision">En revision</option>
            <option value="ajustado">Ajustado</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Desde</span>
          <input v-model="filtros.desde" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Hasta</span>
          <input v-model="filtros.hasta" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
        </label>

        <button
          type="button"
          class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
          @click="cargarConteos"
        >
          <Search class="h-4 w-4" />
          Consultar
        </button>
      </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.35fr)]">
      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 p-4">
          <h2 class="text-sm font-semibold text-slate-900">Resultados</h2>
          <span class="text-xs font-medium text-slate-500">{{ conteos.length }} conteo(s)</span>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Folio</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3">Fecha</th>
                <th class="px-4 py-3 text-right">Partidas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="item in conteos"
                :key="item.id"
                class="cursor-pointer hover:bg-slate-50"
                :class="detalle?.id === item.id ? 'bg-emerald-50/60' : ''"
                @click="abrirConteo(item.id)"
              >
                <td class="px-4 py-3">
                  <p class="font-semibold text-slate-900">{{ item.folio }}</p>
                  <p class="text-xs text-slate-500">{{ item.user?.name || "Sin usuario" }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="estadoClass(item.estado)">
                    {{ estadoLabel(item.estado) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ fecha(item.created_at) }}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-700">{{ item.detalles_count ?? 0 }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!conteos.length && !cargando" class="p-10 text-center text-sm text-slate-500">
          No hay conteos con los filtros seleccionados.
        </div>
        <div v-if="cargando" class="p-10 text-center text-sm text-slate-500">
          Cargando conteos...
        </div>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <template v-if="detalle">
          <div class="border-b border-slate-200 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-lg font-semibold text-slate-950">{{ detalle.folio }}</h2>
                  <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="estadoClass(detalle.estado)">
                    {{ estadoLabel(detalle.estado) }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                  Snapshot: {{ fecha(detalle.snapshot_at) }} · Responsable: {{ detalle.responsable || "Sin usuario" }}
                </p>
                <p class="mt-0.5 text-xs font-medium text-slate-500">{{ alcanceLabel(detalle) }}</p>
                <p v-if="detalle.notas" class="mt-0.5 text-xs text-slate-500 italic">{{ detalle.notas }}</p>
              </div>
            </div>

            <div
              v-if="detalle.movimientos_posteriores?.total > 0"
              class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
            >
              Hubo {{ detalle.movimientos_posteriores.total }} movimiento(s) despues del snapshot:
              {{ movimientosTexto(detalle.movimientos_posteriores) }}.
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Metric label="Partidas" :value="detalle.resumen?.lineas ?? 0" />
              <Metric label="Diferencias" :value="detalle.resumen?.diferencias ?? 0" tone="amber" />
              <Metric label="Faltantes" :value="detalle.resumen?.faltantes ?? 0" tone="rose" />
              <Metric label="Valor diferencia" :value="dinero(detalle.resumen?.valor_diferencia ?? 0)" tone="rose" />
            </div>
          </div>

          <div v-if="detalle.eventos?.length" class="border-b border-slate-200 p-4">
            <h3 class="text-sm font-semibold text-slate-900">Bitacora</h3>
            <div class="mt-3 max-h-48 space-y-2 overflow-y-auto">
              <div v-for="evento in detalle.eventos" :key="evento.id" class="flex items-start justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                <div>
                  <p class="font-semibold text-slate-800">{{ evento.descripcion }}</p>
                  <p class="text-slate-500">{{ evento.usuario || "Sistema" }}</p>
                </div>
                <span class="shrink-0 text-slate-500">{{ fecha(evento.created_at) }}</span>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                  <th class="px-4 py-3">Producto</th>
                  <th class="px-4 py-3 text-right">Sistema</th>
                  <th class="px-4 py-3 text-right">Fisico</th>
                  <th class="px-4 py-3 text-right">Dif.</th>
                  <th class="px-4 py-3">Estado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="d in detalle.detalles" :key="d.id" class="hover:bg-slate-50">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-900">{{ d.nombre }}</p>
                    <p class="text-xs text-slate-500">
                      {{ d.nombre_variante || "Producto base" }}
                      <span v-if="d.sku"> - {{ d.sku }}</span>
                      <span v-if="d.codigo"> - {{ d.codigo }}</span>
                    </p>
                  </td>
                  <td class="px-4 py-3 text-right text-slate-700">{{ fmt(d.stock_sistema) }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ fmt(d.cantidad_fisica) }}</td>
                  <td class="px-4 py-3 text-right font-semibold" :class="diffClass(d.diferencia)">
                    {{ signo(d.diferencia) }}{{ fmt(Math.abs(d.diferencia || 0)) }}
                  </td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="detalleClass(d.estado)">
                      {{ detalleLabel(d.estado) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <div v-else class="grid min-h-[420px] place-items-center p-8 text-center">
          <div>
            <FileSearch class="mx-auto h-10 w-10 text-slate-400" />
            <h2 class="mt-3 text-base font-semibold text-slate-900">Selecciona un conteo</h2>
            <p class="mt-1 text-sm text-slate-500">Aqui se vera el detalle para auditoria y reportes.</p>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import {
  ClipboardCheck,
  ClipboardList,
  FileSearch,
  Search,
} from "lucide-vue-next";

const conteos = ref([]);
const detalle = ref(null);
const cargando = ref(false);
const filtros = reactive({ q: "", estado: "", desde: "", hasta: "" });

onMounted(cargarConteos);

async function cargarConteos() {
  cargando.value = true;
  try {
    const { data } = await http.get("/api/inventario-conteos", { params: { ...filtros } });
    conteos.value = data;
    if (!data.some((item) => item.id === detalle.value?.id)) detalle.value = null;
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudieron cargar los conteos.");
  } finally {
    cargando.value = false;
  }
}

async function abrirConteo(id) {
  try {
    const { data } = await http.get(`/api/inventario-conteos/${id}`);
    detalle.value = data;
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo abrir el conteo.");
  }
}

function estadoLabel(estado) {
  return {
    en_conteo: "En captura",
    en_revision: "En revision",
    ajustado: "Ajustado",
    cancelado: "Cancelado",
  }[estado] || estado;
}

function estadoClass(estado) {
  return {
    en_conteo: "bg-sky-50 text-sky-700 ring-sky-200",
    en_revision: "bg-amber-50 text-amber-700 ring-amber-200",
    ajustado: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    cancelado: "bg-rose-50 text-rose-700 ring-rose-200",
  }[estado] || "bg-slate-50 text-slate-700 ring-slate-200";
}

function detalleLabel(estado) {
  return {
    no_contado: "No contado",
    completo: "Completo",
    faltante: "Faltante",
    sobrante: "Sobrante",
    nuevo_encontrado: "Nuevo",
    contado: "Contado",
  }[estado] || estado;
}

function detalleClass(estado) {
  return {
    no_contado: "bg-slate-50 text-slate-700 ring-slate-200",
    completo: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    faltante: "bg-rose-50 text-rose-700 ring-rose-200",
    sobrante: "bg-amber-50 text-amber-700 ring-amber-200",
    nuevo_encontrado: "bg-violet-50 text-violet-700 ring-violet-200",
  }[estado] || "bg-slate-50 text-slate-700 ring-slate-200";
}

function diffClass(valor) {
  const n = Number(valor || 0);
  if (n > 0) return "text-amber-700";
  if (n < 0) return "text-rose-700";
  return "text-emerald-700";
}

function signo(valor) {
  return Number(valor || 0) > 0 ? "+" : Number(valor || 0) < 0 ? "-" : "";
}

function fmt(valor) {
  return new Intl.NumberFormat("es-MX", { maximumFractionDigits: 2 }).format(Number(valor || 0));
}

function dinero(valor) {
  return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(valor || 0));
}

function fecha(valor) {
  if (!valor) return "Sin fecha";
  return new Intl.DateTimeFormat("es-MX", { dateStyle: "short", timeStyle: "short" }).format(new Date(valor));
}

function alcanceLabel(c) {
  if (!c || c.alcance_tipo === "total") return "Alcance: inventario completo";
  return `Alcance: ${c.alcance_tipo === "categoria" ? "categoria" : "marca"} ${c.alcance_nombre || c.alcance_id}`;
}

function movimientosTexto(m) {
  return [
    ["ventas", "ventas"],
    ["compras", "compras"],
    ["devoluciones_cliente", "devoluciones de cliente"],
    ["devoluciones_proveedor", "devoluciones a proveedor"],
    ["traspasos", "traspasos"],
    ["ajustes", "ajustes"],
  ]
    .filter(([key]) => Number(m?.[key] || 0) > 0)
    .map(([key, label]) => `${m[key]} ${label}`)
    .join(", ");
}
</script>

<script>
export default {
  components: {
    Metric: {
      props: {
        label: { type: String, required: true },
        value: { type: [String, Number], required: true },
        tone: { type: String, default: "slate" },
      },
      computed: {
        cls() {
          return {
            slate: "bg-slate-50 text-slate-900 ring-slate-200",
            amber: "bg-amber-50 text-amber-800 ring-amber-200",
            rose: "bg-rose-50 text-rose-800 ring-rose-200",
          }[this.tone] || "bg-slate-50 text-slate-900 ring-slate-200";
        },
      },
      template: `
        <div class="rounded-lg px-4 py-3 ring-1" :class="cls">
          <p class="text-xs font-semibold uppercase text-slate-500">{{ label }}</p>
          <p class="mt-1 text-xl font-semibold">{{ value }}</p>
        </div>
      `,
    },
  },
};
</script>
