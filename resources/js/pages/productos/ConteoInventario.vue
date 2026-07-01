<template>
  <main class="min-h-screen bg-slate-50 p-3 text-slate-900 sm:p-6">
    <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <div class="flex items-center gap-3">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200">
            <ClipboardCheck class="h-5 w-5" />
          </div>
          <div>
            <h1 class="text-xl font-semibold text-slate-950">Conteo fisico</h1>
            <p class="text-sm text-slate-500">Sucursal activa: {{ auth.sucursalNombre || "Sin sucursal" }}</p>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <RouterLink
          :to="{ name: 'conteos-inventario-consulta' }"
          class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
          <ClipboardList class="h-4 w-4" />
          Consultas
        </RouterLink>
        <button
          v-if="auth.can('inventario.conteos.crear')"
          type="button"
          class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
          :disabled="creando"
          @click="nuevoConteo"
        >
          <Loader2 v-if="creando" class="h-4 w-4 animate-spin" />
          <Plus v-else class="h-4 w-4" />
          Nuevo conteo
        </button>
      </div>
    </header>

    <section class="mt-5">
      <section v-if="conteo" class="space-y-5">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-950">{{ conteo.folio }}</h2>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="estadoClass(conteo.estado)">
                  {{ estadoLabel(conteo.estado) }}
                </span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                  Modo ciego
                </span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                  {{ alcanceLabel(conteo) }}
                </span>
              </div>
              <p class="mt-1 text-sm text-slate-500">
                Responsable: {{ conteo.responsable || "Sin usuario" }} · Snapshot: {{ fecha(conteo.snapshot_at) }}
              </p>
              <p v-if="conteo.notas" class="mt-0.5 text-sm text-slate-500 italic">{{ conteo.notas }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                v-if="conteo.estado === 'en_conteo' && auth.can('inventario.conteos.revisar')"
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800"
                @click="cerrarConteo"
              >
                <LockKeyhole class="h-4 w-4" />
                Cerrar captura
              </button>
              <button
                v-if="conteo.estado === 'en_revision' && auth.can('inventario.conteos.revisar')"
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                @click="reabrirConteo"
              >
                <UnlockKeyhole class="h-4 w-4" />
                Reabrir
              </button>
              <button
                v-if="conteo.estado === 'en_revision' && auth.can('inventario.conteos.ajustar')"
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700"
                @click="aplicarAjustes"
              >
                <CheckCircle2 class="h-4 w-4" />
                Aplicar ajustes
              </button>
              <button
                v-if="['en_conteo', 'en_revision'].includes(conteo.estado) && auth.can('inventario.conteos.cancelar')"
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-rose-200 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                @click="cancelarConteo"
              >
                <Ban class="h-4 w-4" />
                Cancelar
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                :disabled="exportando"
                @click="exportarPdf"
              >
                <Loader2 v-if="exportando" class="h-4 w-4 animate-spin" />
                <FileText v-else class="h-4 w-4" />
                PDF
              </button>
            </div>
          </div>

          <div
            v-if="conteo.estado !== 'en_conteo' && conteo.movimientos_posteriores?.total > 0"
            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
          >
            Hubo {{ conteo.movimientos_posteriores.total }} movimiento(s) despues del snapshot:
            {{ movimientosTexto(conteo.movimientos_posteriores) }}.
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Metric
              label="Partidas contadas"
              :value="conteo.estado === 'en_conteo'
                ? `${conteo.resumen?.contadas ?? 0} / ${conteo.resumen?.total_snapshot ?? '?'}`
                : (conteo.resumen?.contadas ?? 0)"
            />
            <Metric label="Piezas fisicas" :value="fmt(conteo.resumen?.piezas_fisicas ?? 0)" />
            <Metric v-if="conteo.estado !== 'en_conteo'" label="Diferencias" :value="conteo.resumen?.diferencias ?? 0" tone="amber" />
            <Metric v-if="conteo.estado !== 'en_conteo'" label="Valor diferencia" :value="dinero(conteo.resumen?.valor_diferencia ?? 0)" tone="rose" />
          </div>
        </div>

        <div v-if="conteo.estado === 'en_conteo'" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Buscar o escanear</span>
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                ref="scanInput"
                v-model.trim="busqueda"
                type="text"
                class="h-12 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-28 text-base outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                placeholder="SKU, codigo, nombre, IMEI o serie"
                autocomplete="off"
                @keyup.enter="buscarYCapturar"
              />
              <button
                type="button"
                class="absolute right-1.5 top-1.5 inline-flex h-9 items-center gap-2 rounded-md bg-emerald-600 px-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                :disabled="buscando || !busqueda"
                @click="buscarYCapturar"
              >
                <Loader2 v-if="buscando" class="h-4 w-4 animate-spin" />
                <ScanLine v-else class="h-4 w-4" />
                Enter
              </button>
            </div>
          </label>

          <div v-if="resultados.length > 1" class="mt-3 overflow-hidden rounded-lg border border-slate-200">
            <button
              v-for="r in resultados"
              :key="`${r.producto_id}-${r.variante_id || 'base'}-${r.identificador || ''}`"
              type="button"
              class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50"
              @click="capturarResultado(r)"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ r.nombre }}</p>
                <p class="truncate text-xs text-slate-500">
                  {{ r.nombre_variante || "Producto base" }}
                  <span v-if="r.sku"> - SKU {{ r.sku }}</span>
                  <span v-if="r.codigo"> - Codigo {{ r.codigo }}</span>
                </p>
              </div>
              <Plus class="h-4 w-4 shrink-0 text-emerald-600" />
            </button>
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                {{ conteo.estado === 'en_conteo' ? 'Capturado' : 'Revision de diferencias' }}
              </h3>
              <p class="text-xs text-slate-500">
                {{ conteo.estado === 'en_conteo' ? 'No se muestran existencias del sistema hasta cerrar captura.' : 'Compara fisico contra sistema antes de ajustar.' }}
              </p>
            </div>

            <div v-if="conteo.estado !== 'en_conteo'" class="flex flex-wrap gap-2">
              <button
                v-for="f in filtrosRevision"
                :key="f.key"
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold ring-1"
                :class="filtro === f.key ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
                @click="filtro = f.key"
              >
                {{ f.label }}
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                  <th class="px-4 py-3">Producto</th>
                  <th class="px-4 py-3 text-right">Fisico</th>
                  <th v-if="conteo.estado !== 'en_conteo'" class="px-4 py-3 text-right">Sistema</th>
                  <th v-if="conteo.estado !== 'en_conteo'" class="px-4 py-3 text-right">Diferencia</th>
                  <th v-if="conteo.estado !== 'en_conteo'" class="px-4 py-3">Estado</th>
                  <th v-if="conteo.estado === 'en_conteo'" class="px-4 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="d in detallesFiltrados" :key="d.id" class="hover:bg-slate-50/70">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-900">{{ d.nombre }}</p>
                    <p class="text-xs text-slate-500">
                      {{ d.nombre_variante || "Producto base" }}
                      <span v-if="d.sku"> - {{ d.sku }}</span>
                      <span v-if="d.codigo"> - {{ d.codigo }}</span>
                    </p>
                    <div v-if="d.series_contadas?.length" class="mt-1 flex flex-wrap gap-1">
                      <span
                        v-for="s in d.series_contadas"
                        :key="s"
                        class="group inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600"
                      >
                        {{ s }}
                        <button
                          v-if="conteo.estado === 'en_conteo'"
                          type="button"
                          class="hidden group-hover:inline-flex text-rose-500 hover:text-rose-700"
                          title="Quitar serie"
                          @click.stop="quitarSerie(d, s)"
                        >x</button>
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <input
                      v-if="conteo.estado === 'en_conteo' && !d.series_contadas?.length"
                      :value="d.cantidad_fisica"
                      type="number"
                      min="0"
                      step="1"
                      class="h-9 w-24 rounded-lg border border-slate-200 px-2 text-right text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                      @change="reemplazarCantidad(d, $event.target.value)"
                    />
                    <span v-else class="font-semibold text-slate-900">{{ fmt(d.cantidad_fisica) }}</span>
                  </td>
                  <td v-if="conteo.estado !== 'en_conteo'" class="px-4 py-3 text-right text-slate-700">{{ fmt(d.stock_sistema) }}</td>
                  <td v-if="conteo.estado !== 'en_conteo'" class="px-4 py-3 text-right font-semibold" :class="diffClass(d.diferencia)">
                    {{ signo(d.diferencia) }}{{ fmt(Math.abs(d.diferencia || 0)) }}
                  </td>
                  <td v-if="conteo.estado !== 'en_conteo'" class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="detalleClass(d.estado)">
                      {{ detalleLabel(d.estado) }}
                    </span>
                  </td>
                  <td v-if="conteo.estado === 'en_conteo'" class="px-4 py-3 text-right">
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                      title="Eliminar línea"
                      @click="eliminarLinea(d)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="!detallesFiltrados.length" class="p-10 text-center text-sm text-slate-500">
            {{ conteo.estado === 'en_conteo' ? 'Escanea o busca productos para comenzar el conteo.' : 'No hay partidas con este filtro.' }}
          </div>
        </div>
      </section>

      <section v-else class="grid min-h-[420px] place-items-center rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
        <div>
          <ClipboardList class="mx-auto h-10 w-10 text-slate-400" />
          <h2 class="mt-3 text-base font-semibold text-slate-900">Sin conteo activo</h2>
          <p class="mt-1 text-sm text-slate-500">Crea un conteo para iniciar captura en la sucursal activa.</p>
        </div>
      </section>
    </section>
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import Swal from "sweetalert2";
import http from "@/lib/http";
import { confirm, toastError, toastSuccess, toastWarning } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import {
  Ban,
  CheckCircle2,
  ClipboardCheck,
  ClipboardList,
  FileText,
  Loader2,
  LockKeyhole,
  Plus,
  ScanLine,
  Search,
  Trash2,
  UnlockKeyhole,
} from "lucide-vue-next";

const auth = useAuthStore();
const conteo = ref(null);
const creando = ref(false);
const exportando = ref(false);
const buscando = ref(false);
const busqueda = ref("");
const resultados = ref([]);
const filtro = ref("diferencias");
const scanInput = ref(null);
const alcances = ref({ categorias: [], marcas: [] });

const filtrosRevision = [
  { key: "todos", label: "Todos" },
  { key: "diferencias", label: "Diferencias" },
  { key: "faltante", label: "Faltantes" },
  { key: "sobrante", label: "Sobrantes" },
  { key: "no_contado", label: "No contados" },
];

const detallesFiltrados = computed(() => {
  const detalles = conteo.value?.detalles || [];
  if (conteo.value?.estado === "en_conteo" || filtro.value === "todos") return detalles;
  if (filtro.value === "diferencias") return detalles.filter((d) => Number(d.diferencia || 0) !== 0);
  return detalles.filter((d) => d.estado === filtro.value);
});

onMounted(async () => {
  await cargarAlcances();
  await cargarConteos();
  await nextTick();
  scanInput.value?.focus();
});

async function cargarAlcances() {
  try {
    const { data } = await http.get("/api/inventario-conteos/alcances");
    alcances.value = data;
  } catch {
    alcances.value = { categorias: [], marcas: [] };
  }
}

async function cargarConteos() {
  try {
    const { data } = await http.get("/api/inventario-conteos");
    const operativo = data.find((item) => ["en_conteo", "en_revision"].includes(item.estado));
    if (!conteo.value && operativo) await abrirConteo(operativo.id);
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudieron cargar los conteos.");
  }
}

async function abrirConteo(id) {
  try {
    const { data } = await http.get(`/api/inventario-conteos/${id}`);
    conteo.value = data;
    resultados.value = [];
    if (data.estado !== "en_conteo") filtro.value = "diferencias";
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo abrir el conteo.");
  }
}

async function refrescarActual() {
  if (conteo.value?.id) await abrirConteo(conteo.value.id);
  await cargarConteos();
}

async function exportarPdf() {
  if (!conteo.value) return;
  exportando.value = true;
  try {
    const resp = await http.get(`/api/inventario-conteos/${conteo.value.id}/exportar-pdf`, { responseType: "blob" });
    const url = URL.createObjectURL(new Blob([resp.data]));
    const a = document.createElement("a");
    a.href = url;
    a.download = `conteo_${conteo.value.folio}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    toastError("No se pudo generar el PDF.");
  } finally {
    exportando.value = false;
  }
}

async function nuevoConteo() {
  const categoriaOptions = alcances.value.categorias.map((item) => `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`).join("");
  const marcaOptions = alcances.value.marcas.map((item) => `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`).join("");
  const res = await Swal.fire({
    title: "Nuevo conteo fisico",
    html: `
      <div class="space-y-3 text-left">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Alcance</span>
          <select id="conteo-alcance-tipo" class="swal2-input" style="width:100%;margin:0">
            <option value="total">Inventario completo</option>
            <option value="categoria">Por categoria</option>
            <option value="marca">Por marca</option>
          </select>
        </label>
        <label id="conteo-alcance-categoria-wrap" class="block" style="display:none">
          <span class="mb-1 block text-sm font-medium text-slate-700">Categoria</span>
          <select id="conteo-alcance-categoria" class="swal2-input" style="width:100%;margin:0">
            <option value="">Selecciona categoria</option>
            ${categoriaOptions}
          </select>
        </label>
        <label id="conteo-alcance-marca-wrap" class="block" style="display:none">
          <span class="mb-1 block text-sm font-medium text-slate-700">Marca</span>
          <select id="conteo-alcance-marca" class="swal2-input" style="width:100%;margin:0">
            <option value="">Selecciona marca</option>
            ${marcaOptions}
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Notas</span>
          <textarea id="conteo-notas" class="swal2-textarea" style="width:100%;margin:0" placeholder="Area, responsable o referencia"></textarea>
        </label>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: "Crear",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    didOpen: () => {
      const tipo = document.getElementById("conteo-alcance-tipo");
      const categoria = document.getElementById("conteo-alcance-categoria-wrap");
      const marca = document.getElementById("conteo-alcance-marca-wrap");
      const sync = () => {
        categoria.style.display = tipo.value === "categoria" ? "block" : "none";
        marca.style.display = tipo.value === "marca" ? "block" : "none";
      };
      tipo.addEventListener("change", sync);
      sync();
    },
    preConfirm: () => {
      const tipo = document.getElementById("conteo-alcance-tipo").value;
      const categoria = document.getElementById("conteo-alcance-categoria").value;
      const marca = document.getElementById("conteo-alcance-marca").value;
      if (tipo === "categoria" && !categoria) {
        Swal.showValidationMessage("Selecciona una categoria.");
        return false;
      }
      if (tipo === "marca" && !marca) {
        Swal.showValidationMessage("Selecciona una marca.");
        return false;
      }
      return {
        alcance_tipo: tipo,
        alcance_id: tipo === "categoria" ? categoria : tipo === "marca" ? marca : null,
        notas: document.getElementById("conteo-notas").value || null,
      };
    },
  });
  if (!res.isConfirmed) return;

  creando.value = true;
  try {
    const { data } = await http.post("/api/inventario-conteos", res.value);
    conteo.value = data;
    toastSuccess("Conteo creado.");
    await cargarConteos();
    await nextTick();
    scanInput.value?.focus();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo crear el conteo.");
  } finally {
    creando.value = false;
  }
}

async function buscarYCapturar() {
  if (!conteo.value || conteo.value.estado !== "en_conteo" || !busqueda.value) return;

  buscando.value = true;
  resultados.value = [];
  const q = busqueda.value;
  try {
    const { data } = await http.get(`/api/inventario-conteos/${conteo.value.id}/escanear`, { params: { q } });

    if (data.tipo === "capturado") {
      busqueda.value = "";
      actualizarDetalleLocal(data.detalle);
      await nextTick();
      scanInput.value?.focus();
      return;
    }
    if (data.tipo === "no_encontrado" || !data.resultados?.length) {
      toastWarning("Producto no encontrado.");
      return;
    }
    if (data.resultados.length === 1) {
      await capturarResultado(data.resultados[0]);
      return;
    }
    resultados.value = data.resultados;
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo buscar el producto.");
  } finally {
    buscando.value = false;
  }
}

async function capturarResultado(item) {
  if (item.tiene_series && !item.identificador) {
    toastWarning("Escanea el IMEI o serie para productos seriados.");
    return;
  }

  try {
    const { data } = await http.post(`/api/inventario-conteos/${conteo.value.id}/capturar`, {
      producto_id: item.producto_id,
      variante_id: item.variante_id,
      cantidad: 1,
      modo: "sumar",
      serie_id: item.serie_id,
      identificador: item.identificador,
    });
    busqueda.value = "";
    resultados.value = [];
    actualizarDetalleLocal(data);
    await nextTick();
    scanInput.value?.focus();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo capturar.");
  }
}

async function reemplazarCantidad(detalle, valor) {
  const cantidad = Math.max(0, Number(valor || 0));
  try {
    const { data } = await http.post(`/api/inventario-conteos/${conteo.value.id}/capturar`, {
      producto_id: detalle.producto_id,
      variante_id: detalle.variante_id,
      cantidad,
      modo: "reemplazar",
    });
    actualizarDetalleLocal(data);
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo actualizar la cantidad.");
  }
}

function actualizarDetalleLocal(detalleActualizado) {
  const detalles = conteo.value.detalles ?? [];
  const idx = detalles.findIndex(
    (d) => d.producto_id === detalleActualizado.producto_id && d.variante_id === detalleActualizado.variante_id
  );
  if (idx >= 0) {
    detalles.splice(idx, 1, detalleActualizado);
  } else {
    detalles.unshift(detalleActualizado);
  }
  const contadas = detalles.filter((d) => Number(d.cantidad_fisica) > 0).length;
  const piezas = detalles.reduce((s, d) => s + Number(d.cantidad_fisica), 0);
  conteo.value.resumen = {
    ...conteo.value.resumen,
    contadas,
    piezas_fisicas: piezas,
  };
}

async function cerrarConteo() {
  const ok = await confirm({
    title: "Cerrar captura",
    text: "Se mostraran diferencias y existencias del sistema para revision.",
    confirmText: "Cerrar captura",
  });
  if (!ok) return;

  try {
    const { data } = await http.post(`/api/inventario-conteos/${conteo.value.id}/cerrar`);
    conteo.value = data;
    toastSuccess("Conteo enviado a revision.");
    await cargarConteos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo cerrar el conteo.");
  }
}

async function reabrirConteo() {
  const ok = await confirm({
    title: "Reabrir conteo",
    text: "Volvera a modo ciego para continuar capturando.",
    confirmText: "Reabrir",
  });
  if (!ok) return;

  try {
    const { data } = await http.post(`/api/inventario-conteos/${conteo.value.id}/reabrir`);
    conteo.value = data;
    toastSuccess("Conteo reabierto.");
    await cargarConteos();
    await nextTick();
    scanInput.value?.focus();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo reabrir el conteo.");
  }
}

async function aplicarAjustes() {
  if (conteo.value?.movimientos_posteriores?.total > 0) {
    await confirm({
      title: "Movimientos posteriores detectados",
      text: `Hay ${conteo.value.movimientos_posteriores.total} movimiento(s) despues del snapshot. Reabre o crea un nuevo conteo antes de aplicar ajustes.`,
      confirmText: "Entendido",
    });
    return;
  }

  const res = await Swal.fire({
    title: "Aplicar ajustes",
    text: "El stock de la sucursal activa quedara igual al conteo fisico.",
    input: "text",
    inputLabel: "Motivo",
    inputPlaceholder: "Ej. Conteo fisico mensual",
    showCancelButton: true,
    confirmButtonText: "Aplicar ajustes",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    inputValidator: (value) => (!value ? "Escribe un motivo." : undefined),
  });
  if (!res.isConfirmed) return;

  try {
    const { data } = await http.post(`/api/inventario-conteos/${conteo.value.id}/ajustar`, { motivo: res.value });
    conteo.value = data;
    toastSuccess("Ajustes aplicados.");
    await cargarConteos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudieron aplicar los ajustes.");
  }
}

async function cancelarConteo() {
  const ok = await confirm({
    title: "Cancelar conteo",
    text: "El conteo quedara cerrado sin mover inventario.",
    confirmText: "Cancelar conteo",
  });
  if (!ok) return;

  try {
    await http.post(`/api/inventario-conteos/${conteo.value.id}/cancelar`);
    toastSuccess("Conteo cancelado.");
    conteo.value = null;
    resultados.value = [];
    await cargarConteos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo cancelar el conteo.");
  }
}

async function eliminarLinea(detalle) {
  const ok = await confirm({
    title: "Eliminar línea",
    text: `¿Quitar "${detalle.nombre}" del conteo?`,
    confirmText: "Eliminar",
  });
  if (!ok) return;

  try {
    const { data } = await http.delete(`/api/inventario-conteos/${conteo.value.id}/linea`, {
      data: { producto_id: detalle.producto_id, variante_id: detalle.variante_id },
    });
    conteo.value = data;
    await nextTick();
    scanInput.value?.focus();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo eliminar la línea.");
  }
}

async function quitarSerie(detalle, identificador) {
  const ok = await confirm({
    title: "Quitar serie/IMEI",
    text: `¿Quitar "${identificador}" del conteo?`,
    confirmText: "Quitar",
  });
  if (!ok) return;

  try {
    const { data } = await http.delete(`/api/inventario-conteos/${conteo.value.id}/serie`, {
      data: { producto_id: detalle.producto_id, variante_id: detalle.variante_id, identificador },
    });
    conteo.value = data;
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo quitar la serie.");
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
  if (!c || c.alcance_tipo === "total") return "Inventario completo";
  return `${c.alcance_tipo === "categoria" ? "Categoria" : "Marca"}: ${c.alcance_nombre || c.alcance_id}`;
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

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
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
