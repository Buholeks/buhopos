<template>
  <main>
    <header>
      <h1 class="text-xl font-semibold text-slate-900">{{ titulo }}</h1>
      <p class="mt-1 text-sm text-slate-500">Movimiento de mercancia entre sucursales.</p>
    </header>

    <section v-if="vista === 'nuevo'" class="mt-5 space-y-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_180px] md:items-end">
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Sucursal destino</span>
            <select
              v-model.number="form.destino_sucursal_id"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-emerald-500 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
            >
              <option value="">Selecciona destino</option>
              <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                {{ sucursal.nombre }}
              </option>
            </select>
          </label>

          <BaseInput v-model="form.notas" label="Notas" placeholder="Motivo o referencia">
            <template #icon><FileText class="h-4 w-4" /></template>
          </BaseInput>

          <button
            type="button"
            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
            :disabled="guardando || !puedeGuardar"
            @click="guardarTraspaso"
          >
            <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
            <Send v-else class="h-4 w-4" />
            {{ guardando ? "Guardando..." : "Enviar" }}
          </button>
        </div>
      </div>

      <BuscadorProducto ref="buscadorRef" @seleccionar="abrirModalItem" />

      <Carrito
        :items="carrito"
        @ajustar-cantidad="ajustarCantidadCarrito"
        @normalizar-cantidad="normalizarCantidadCarrito"
        @eliminar="carrito.splice($event, 1)"
      />

      <ModalCantidad
        :item="modalItem"
        :series="seriesModal"
        :stock-disponible="modalItem ? stockDisponible(modalItem) : 0"
        @confirmar="confirmarModalItem"
        @cerrar="cerrarModalItem"
      />
    </section>

    <section v-else class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div
        v-if="pendientesPorRecibir > 0"
        class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
      >
        Tienes {{ pendientesPorRecibir }} traspaso(s) pendiente(s) por recibir en esta sucursal.
      </div>

      <div class="space-y-3 border-b border-slate-200 p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_160px_160px_150px] xl:items-end">
          <BaseInput v-model="filtros.buscar" label="Buscar" placeholder="Folio, producto, SKU o IMEI" @keyup.enter="cargarTraspasos">
            <template #icon><Search class="h-4 w-4" /></template>
          </BaseInput>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Estado</span>
            <select v-model="filtros.estado" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
              <option value="">Todos</option>
              <option value="con_pendientes">
                {{ tipoConsulta === "entrada" ? "Por recibir" : "Pendientes de recepcion" }}
              </option>
              <option value="parcial">Parcialmente recibidos</option>
              <option value="recibido">
                {{ tipoConsulta === "entrada" ? "Ya recibidos" : "Recibidos por destino" }}
              </option>
              <option value="rechazado">Rechazados</option>
              <option value="cancelado">Cancelados</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">
              {{ tipoConsulta === "entrada" ? "Sucursal origen" : "Sucursal destino" }}
            </span>
            <select v-model="filtros.sucursal_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
              <option value="">Todas</option>
              <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                {{ sucursal.nombre }}
              </option>
            </select>
          </label>

          <BaseInput v-model="filtros.desde" label="Desde" type="date" />
          <BaseInput v-model="filtros.hasta" label="Hasta" type="date" />

          <button
            type="button"
            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100"
            @click="cargarTraspasos"
          >
            <Search class="h-4 w-4" />
            Consultar
          </button>
        </div>

        <button
          type="button"
          class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
          @click="limpiarFiltros"
        >
          Limpiar filtros
        </button>
      </div>

      <div class="divide-y divide-slate-100">
        <article v-for="t in traspasos" :key="t.id" class="p-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold text-slate-900">{{ t.folio }}</p>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="estadoClass(t.estado)">
                  {{ estadoLabel(t.estado) }}
                </span>
              </div>
              <p class="mt-1 text-xs text-slate-500">
                {{ t.origen?.nombre }} -> {{ t.destino?.nombre }} - {{ fmt(t.total_items) }} pieza(s)
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Envio: <span class="font-medium text-slate-700">{{ t.user?.name || "Sin usuario" }}</span>
                <span v-if="t.receptor"> - Recibio: <span class="font-medium text-slate-700">{{ t.receptor.name }}</span></span>
                <span v-if="t.rechazador"> - Rechazo: <span class="font-medium text-slate-700">{{ t.rechazador.name }}</span></span>
                <span v-if="t.cancelador"> - Cancelo: <span class="font-medium text-slate-700">{{ t.cancelador.name }}</span></span>
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="verDetalle(t.id)">
                Ver detalle
              </button>
              <button v-if="puedeRecibir(t)" type="button" class="rounded-xl border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" @click="recibirTraspaso(t)">
                Recibir todo
              </button>
              <button v-if="puedeRecibir(t)" type="button" class="rounded-xl border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50" @click="rechazarTraspaso(t)">
                Rechazar
              </button>
              <button v-if="puedeCancelar(t)" type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="cancelarTraspaso(t)">
                Cancelar
              </button>
            </div>
          </div>
        </article>

        <div v-if="!traspasos.length" class="p-8 text-center text-sm text-slate-500">
          No hay traspasos con los filtros seleccionados.
        </div>
      </div>

      <DetalleModal
        :traspaso="detalleAbierto"
        :puede-recibir="detalleAbierto ? puedeRecibir(detalleAbierto) : false"
        :seleccion="seleccionRecepcion"
        :seleccionados="detalleIdsSeleccionados"
        :operando="operando === detalleAbierto?.id"
        :exportando-pdf="exportandoPdf"
        @cerrar="cerrarDetalle"
        @seleccionar-pendientes="seleccionarPendientes"
        @recibir-seleccionados="recibirSeleccionados"
        @toggle-seleccion="toggleSeleccion"
        @exportar-pdf="exportarDetallePdf"
      />
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import { FileText, Loader2, Search, Send } from "lucide-vue-next";
import BuscadorProducto from "@/components/traspaso/BuscadorProducto.vue";
import Carrito from "@/components/traspaso/Carrito.vue";
import DetalleModal from "@/components/traspaso/DetalleModal.vue";
import ModalCantidad from "@/components/traspaso/ModalCantidad.vue";

const props = defineProps({
  modo: { type: String, default: "nuevo" },
  tipo: { type: String, default: "" },
});

const router = useRouter();
const auth = useAuthStore();
const vista = ref(props.modo === "consulta" ? "consulta" : "nuevo");
const sucursales = ref([]);
const carrito = ref([]);
const guardando = ref(false);
const traspasos = ref([]);
const detalleAbierto = ref(null);
const seleccionRecepcion = reactive({});
const pendientesPorRecibir = ref(0);
const operando = ref(null);
const exportandoPdf = ref(false);
const buscadorRef = ref(null);
const modalItem = ref(null);
const seriesPorItem = reactive({});

const form = reactive({ destino_sucursal_id: "", notas: "" });
const filtros = reactive({ buscar: "", estado: "", sucursal_id: "", desde: "", hasta: "" });

const puedeGuardar = computed(() => !!form.destino_sucursal_id && carrito.value.length > 0);
const tipoConsulta = computed(() => (props.tipo === "salida" ? "salida" : "entrada"));
const titulo = computed(() => {
  if (vista.value === "nuevo") return "Nuevo traspaso";
  return tipoConsulta.value === "salida" ? "Traspasos de salida" : "Traspasos de entrada";
});
const detalleIdsSeleccionados = computed(() =>
  Object.entries(seleccionRecepcion)
    .filter(([, selected]) => selected)
    .map(([id]) => Number(id))
);
const seriesModal = computed(() => {
  if (!modalItem.value) return [];
  return seriesPorItem[itemKey(modalItem.value)] ?? [];
});

watch(
  () => props.modo,
  async (modo) => {
    vista.value = modo === "consulta" ? "consulta" : "nuevo";
    detalleAbierto.value = null;
    await cargarSucursales();
    if (vista.value === "consulta") await cargarTraspasos();
  }
);

watch(
  () => props.tipo,
  async () => {
    detalleAbierto.value = null;
    if (vista.value === "consulta") await cargarTraspasos();
  }
);

onMounted(async () => {
  aplicarMesActual();
  await cargarSucursales();
  await cargarPendientes();
  if (vista.value === "consulta") await cargarTraspasos();
});

function itemKey(item) {
  return `${item.producto_id}:${item.variante_id ?? "null"}`;
}

function fmt(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
}

function estadoLabel(estado) {
  return { pendiente: "Pendiente", recibido: "Recibido", rechazado: "Rechazado", cancelado: "Cancelado" }[estado] ?? estado;
}

function estadoClass(estado) {
  return {
    pendiente: "bg-amber-50 text-amber-700 ring-amber-100",
    recibido: "bg-emerald-50 text-emerald-700 ring-emerald-100",
    rechazado: "bg-slate-100 text-slate-700 ring-slate-200",
    cancelado: "bg-rose-50 text-rose-700 ring-rose-100",
  }[estado] ?? "bg-slate-100 text-slate-700 ring-slate-200";
}

async function cargarSucursales() {
  const { data } = await http.get("/api/traspasos/sucursales", {
    params: { solo_destino: vista.value === "nuevo" ? 1 : undefined },
  });
  sucursales.value = data;
}

async function cargarPendientes() {
  const { data } = await http.get("/api/traspasos/resumen-pendientes");
  pendientesPorRecibir.value = Number(data.por_recibir ?? 0);
}

async function abrirModalItem(item) {
  modalItem.value = item;
  if (item.tiene_series) await cargarSeries(item);
}

function cerrarModalItem() {
  modalItem.value = null;
}

function confirmarModalItem({ cantidad, serieId }) {
  if (!modalItem.value) return;
  agregarItem(modalItem.value, { cantidad, serieId });
  cerrarModalItem();
}

async function cargarSeries(item) {
  const key = itemKey(item);
  const { data } = await http.get("/api/traspasos/series-disponibles", {
    params: { producto_id: item.producto_id, variante_id: item.variante_id },
  });
  seriesPorItem[key] = data.filter((serie) => !carrito.value.some((row) => Number(row.serie_id) === Number(serie.id)));
}

function stockDisponible(item) {
  const usado = carrito.value
    .filter((row) => row.producto_id === item.producto_id && row.variante_id === item.variante_id)
    .reduce((sum, row) => sum + Number(row.cantidad || 0), 0);
  return Math.max(0, Number(item.stock || 0) - usado);
}

function agregarItem(item, opciones = {}) {
  if (item.tiene_series) {
    const key = itemKey(item);
    const serie = (seriesPorItem[key] ?? []).find((s) => Number(s.id) === Number(opciones.serieId));
    if (!serie || carrito.value.some((row) => Number(row.serie_id) === Number(serie.id))) return;
    carrito.value.push(rowDesdeItem(item, 1, serie.id, serie.identificador));
    cargarSeries(item);
    return;
  }

  const cantidad = Number(opciones.cantidad ?? 1);
  if (cantidad <= 0 || cantidad > stockDisponible(item)) return;

  const existente = carrito.value.find((row) => !row.serie_id && row.producto_id === item.producto_id && row.variante_id === item.variante_id);
  if (existente) existente.cantidad = Number(existente.cantidad) + cantidad;
  else carrito.value.push(rowDesdeItem(item, cantidad));
}

function ajustarCantidadCarrito(item, delta) {
  const siguiente = Number(item.cantidad || 0) + delta;
  item.cantidad = Math.max(0.001, Math.min(stockTotalItem(item), siguiente));
}

function normalizarCantidadCarrito(item) {
  const cantidad = Number(item.cantidad || 0);
  item.cantidad = Math.max(0.001, Math.min(stockTotalItem(item), cantidad || 0.001));
}

function stockTotalItem(item) {
  return Number(item.stock ?? item.cantidad ?? 0);
}

function rowDesdeItem(item, cantidad, serieId = null, serieIdentificador = null) {
  return {
    producto_id: item.producto_id,
    variante_id: item.variante_id,
    cantidad,
    serie_id: serieId,
    serie_identificador: serieIdentificador,
    nombre: item.nombre,
    variante_nombre: item.variante_nombre,
    sku: item.sku,
    stock: Number(item.stock || 0),
    precio_costo: Number(item.precio_costo || 0),
    precio_venta: Number(item.precio_venta || 0),
  };
}

async function guardarTraspaso() {
  guardando.value = true;
  try {
    await http.post("/api/traspasos", {
      destino_sucursal_id: form.destino_sucursal_id,
      notas: form.notas,
      detalles: carrito.value.map((item) => ({
        producto_id: item.producto_id,
        variante_id: item.variante_id,
        cantidad: item.cantidad,
        serie_id: item.serie_id,
      })),
    });

    toastSuccess("Traspaso enviado. Queda pendiente de recepcion.");
    carrito.value = [];
    form.notas = "";
    buscadorRef.value?.buscarInventario();
    router.push({ name: "traspasos-salida" });
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo registrar el traspaso.");
  } finally {
    guardando.value = false;
  }
}

async function cargarTraspasos() {
  const { data } = await http.get("/api/traspasos", {
    params: {
      buscar: filtros.buscar || undefined,
      estado: filtros.estado || undefined,
      sucursal_id: filtros.sucursal_id || undefined,
      tipo: tipoConsulta.value,
      desde: filtros.desde || undefined,
      hasta: filtros.hasta || undefined,
      per_page: 30,
    },
  });
  traspasos.value = data.data ?? [];
  await cargarPendientes();
}

function limpiarFiltros() {
  Object.assign(filtros, { buscar: "", estado: "", sucursal_id: "" });
  aplicarMesActual();
  cargarTraspasos();
}

function aplicarMesActual() {
  const hoy = new Date();
  filtros.desde = fechaInput(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
  filtros.hasta = fechaInput(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0));
}

function fechaInput(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

async function verDetalle(id) {
  const { data } = await http.get(`/api/traspasos/${id}`);
  detalleAbierto.value = data;
  resetSeleccionRecepcion();
}

function cerrarDetalle() {
  detalleAbierto.value = null;
  resetSeleccionRecepcion();
}

function resetSeleccionRecepcion() {
  Object.keys(seleccionRecepcion).forEach((key) => delete seleccionRecepcion[key]);
  (detalleAbierto.value?.detalles ?? []).forEach((detalle) => {
    seleccionRecepcion[detalle.id] = false;
  });
}

function seleccionarPendientes(valor) {
  (detalleAbierto.value?.detalles ?? []).forEach((detalle) => {
    if (detalle.estado === "pendiente") seleccionRecepcion[detalle.id] = valor;
  });
}

function toggleSeleccion(id, value) {
  seleccionRecepcion[id] = value;
}

function puedeRecibir(traspaso) {
  return traspaso.estado === "pendiente" && tipoConsulta.value === "entrada" && Number(traspaso.destino_sucursal_id) === Number(auth.sucursalId);
}

function puedeCancelar(traspaso) {
  return traspaso.estado === "pendiente" && tipoConsulta.value === "salida" && Number(traspaso.origen_sucursal_id) === Number(auth.sucursalId);
}

async function recibirTraspaso(traspaso) {
  try {
    operando.value = traspaso.id;
    await http.post(`/api/traspasos/${traspaso.id}/recibir`);
    toastSuccess("Traspaso recibido. Stock aplicado.");
    cerrarDetalle();
    await cargarTraspasos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo recibir el traspaso.");
  } finally {
    operando.value = null;
  }
}

async function recibirSeleccionados(traspaso) {
  try {
    operando.value = traspaso.id;
    await http.post(`/api/traspasos/${traspaso.id}/recibir`, { detalle_ids: detalleIdsSeleccionados.value });
    toastSuccess("Partidas recibidas. Stock aplicado.");
    await cargarTraspasos();
    if (detalleAbierto.value?.id === traspaso.id) await verDetalle(traspaso.id);
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo recibir la seleccion.");
  } finally {
    operando.value = null;
  }
}

async function rechazarTraspaso(traspaso) {
  const motivo = window.prompt(`Motivo de rechazo para ${traspaso.folio}`);
  if (motivo === null) return;

  try {
    await http.post(`/api/traspasos/${traspaso.id}/rechazar`, { motivo_rechazo: motivo });
    toastSuccess("Traspaso rechazado. Stock devuelto a origen.");
    cerrarDetalle();
    await cargarTraspasos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo rechazar el traspaso.");
  }
}

async function exportarDetallePdf(traspaso) {
  exportandoPdf.value = true;
  try {
    const resp = await http.get(`/api/traspasos/${traspaso.id}/exportar-pdf`, { responseType: "blob" });
    const url = URL.createObjectURL(new Blob([resp.data]));
    const a = document.createElement("a");
    a.href = url;
    a.download = `traspaso_${traspaso.folio}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (e) {
    toastError("No se pudo generar el PDF.");
  } finally {
    exportandoPdf.value = false;
  }
}

async function cancelarTraspaso(traspaso) {
  const motivo = window.prompt(`Motivo de cancelacion para ${traspaso.folio}`);
  if (motivo === null) return;

  try {
    await http.post(`/api/traspasos/${traspaso.id}/cancelar`, { motivo_cancelacion: motivo });
    toastSuccess("Traspaso cancelado. Stock devuelto a origen.");
    cerrarDetalle();
    await cargarTraspasos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo cancelar el traspaso.");
  }
}
</script>
