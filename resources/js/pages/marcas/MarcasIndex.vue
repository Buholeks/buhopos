<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
      <MarcasHeader @nueva-marca="abrirModalMarca()" />

      <MarcasSearch
        v-model="busqueda"
        :count="marcasFiltradas.length"
        :hasBusqueda="!!busqueda.trim()"
      />

      <div class="mt-6 space-y-3">
        <!-- Empty: no marcas -->
        <div
          v-if="marcas.length === 0"
          class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"
        >
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 ring-1 ring-slate-200">
            <FileText class="h-7 w-7 text-slate-500" />
          </div>
          <p class="mt-4 text-sm text-slate-600">
            No hay marcas aún. Crea la primera para comenzar.
          </p>
          <button
            class="mt-5 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            @click="abrirModalMarca()"
            type="button"
          >
            Crear marca
          </button>
        </div>

        <!-- Empty: no results -->
        <div
          v-else-if="busqueda && marcasFiltradas.length === 0"
          class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"
        >
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 ring-1 ring-slate-200">
            <Search class="h-7 w-7 text-slate-500" />
          </div>
          <p class="mt-4 text-sm text-slate-600">
            No se encontró <span class="font-semibold text-slate-900">“{{ busqueda }}”</span>
          </p>
        </div>

        <!-- Lista -->
        <MarcaCard
          v-for="marca in marcasFiltradas"
          :key="marca.id"
          :marca="marca"
          :abierta="marcasAbiertas.has(marca.id)"
          :busqueda="busqueda"
          @toggle="toggleMarca"
          @nuevo-modelo="abrirModalModelo"
          @editar-marca="abrirModalMarca"
          @eliminar-marca="confirmarEliminarMarca"
          @editar-modelo="({ marca, modelo }) => abrirModalModelo(marca, modelo)"
          @eliminar-modelo="confirmarEliminarModelo"
        />
      </div>

      <!-- MODAL MARCA -->
      <MarcaModal
        :open="modalMarca.mostrar"
        :loading="cargando"
        :editando="modalMarca.editando"
        :form="formMarca"
        :errores="erroresMarca"
        @close="cerrarModalMarca"
        @submit="enviarFormMarca"
        @pick-logo="onLogoMarcaChange"
        @clear-logo="limpiarLogoMarca"
      />

      <!-- MODAL MODELO -->
      <ModeloModal
        :open="modalModelo.mostrar"
        :loading="cargando"
        :editando="modalModelo.editando"
        :nombreMarca="modalModelo.nombreMarca"
        :form="formModelo"
        :errores="erroresModelo"
        @close="cerrarModalModelo"
        @submit="enviarFormModelo"
        @pick-imagen="onImagenModeloChange"
        @clear-imagen="limpiarImagenModelo"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { ofrecerRecuperacion } from "@/helpers/recuperar";

import MarcasHeader from "@/components/marcas/MarcasHeader.vue";
import MarcasSearch from "@/components/marcas/MarcasSearch.vue";
import MarcaCard from "@/components/marcas/MarcaCard.vue";
import MarcaModal from "@/components/marcas/MarcaModal.vue";
import ModeloModal from "@/components/marcas/ModeloModal.vue";

import { FileText, Search } from "lucide-vue-next";

// ─── Toast ────────────────────────────────────────────────────────────────
const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
});

// ─── Estado ───────────────────────────────────────────────────────────────
const marcas = ref([]);
const marcasAbiertas = reactive(new Set());
const busqueda = ref("");

const cargando = ref(false);

const API_MARCAS = "/api/marcas";
const API_MODELOS = "/api/modelos";

// ─── Búsqueda ─────────────────────────────────────────────────────────────
const marcasFiltradas = computed(() => {
  const texto = busqueda.value.trim().toLowerCase();
  if (!texto) return marcas.value;

  return marcas.value.filter((marca) => {
    const nombreMarca = (marca.nombre ?? "").toLowerCase();
    const marcaCoincide = nombreMarca.includes(texto);

    const modelos = Array.isArray(marca.modelos) ? marca.modelos : [];
    const modeloCoincide = modelos.some((m) => (m.nombre ?? "").toLowerCase().includes(texto));

    if (modeloCoincide) marcasAbiertas.add(marca.id);
    return marcaCoincide || modeloCoincide;
  });
});

function toggleMarca(id) {
  if (marcasAbiertas.has(id)) marcasAbiertas.delete(id);
  else marcasAbiertas.add(id);
}

// ─── Carga ────────────────────────────────────────────────────────────────
async function cargarMarcas() {
  try {
    const { data } = await axios.get(API_MARCAS);
    marcas.value = Array.isArray(data) ? data : [];
  } catch {
    Toast.fire({ icon: "error", title: "Error al cargar marcas" });
  }
}
cargarMarcas();

// ─── Modal Marca ──────────────────────────────────────────────────────────
const modalMarca = reactive({ mostrar: false, editando: false, idEditando: null });

const formMarca = reactive({
  nombre: "",
  activo: true,
  logoMedia: null,
  eliminarLogo: false,
});

const erroresMarca = reactive({ nombre: "" });

function abrirModalMarca(marca = null) {
  resetFormMarca();

  if (marca) {
    modalMarca.editando = true;
    modalMarca.idEditando = marca.id;
    formMarca.nombre = marca.nombre ?? "";
    formMarca.activo = !!marca.activo;
    formMarca.logoMedia = marca.logo_url ? { url: marca.logo_url } : null;
  } else {
    modalMarca.editando = false;
    modalMarca.idEditando = null;
  }

  modalMarca.mostrar = true;
}

function cerrarModalMarca() {
  modalMarca.mostrar = false;
  resetFormMarca();
}

function resetFormMarca() {
  formMarca.nombre = "";
  formMarca.activo = true;
  formMarca.logoMedia = null;
  formMarca.eliminarLogo = false;
  erroresMarca.nombre = "";
}

function onLogoMarcaChange(media) {
  formMarca.logoMedia = media;
  formMarca.eliminarLogo = false;
}

function limpiarLogoMarca() {
  formMarca.logoMedia = null;
  formMarca.eliminarLogo = true;
}

async function enviarFormMarca() {
  erroresMarca.nombre = "";

  if (!formMarca.nombre) {
    erroresMarca.nombre = "El nombre es obligatorio.";
    return;
  }
  if (formMarca.nombre.length < 2) {
    erroresMarca.nombre = "Mínimo 2 caracteres.";
    return;
  }

  cargando.value = true;
  try {
    const fd = new FormData();
    fd.append("nombre", formMarca.nombre);
    fd.append("activo", formMarca.activo ? "1" : "0");
    if (formMarca.logoMedia?.id) fd.append("logo_media_id", formMarca.logoMedia.id);
    if (formMarca.eliminarLogo) fd.append("eliminar_logo", "1");

    if (modalMarca.editando) {
      fd.append("_method", "PUT");
      await axios.post(`${API_MARCAS}/${modalMarca.idEditando}`, fd);
      Toast.fire({ icon: "success", title: "Marca actualizada" });
    } else {
      await axios.post(API_MARCAS, fd);
      Toast.fire({ icon: "success", title: "Marca creada" });
    }

    cerrarModalMarca();
    await cargarMarcas();
  } catch (err) {
    const handled = await ofrecerRecuperacion(err, "/api/marcas", async () => {
      cerrarModalMarca();
      await cargarMarcas();
    });
    if (!handled) {
      const msg = err.response?.data?.message ?? "Ocurrió un error";
      Toast.fire({ icon: "error", title: msg });
      const laravel = err.response?.data?.errors;
      if (laravel?.nombre) erroresMarca.nombre = laravel.nombre[0];
    }
  } finally {
    cargando.value = false;
  }
}

async function confirmarEliminarMarca(marca) {
  const r = await Swal.fire({
    title: "¿Eliminar marca?",
    html: `<span class="text-gray-600">Se eliminará <strong>${marca.nombre}</strong> y todos sus modelos.</span>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    cancelButtonColor: "#6b7280",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    customClass: { popup: "rounded-2xl" },
  });
  if (!r.isConfirmed) return;

  try {
    await axios.delete(`${API_MARCAS}/${marca.id}`);
    Toast.fire({ icon: "success", title: "Marca eliminada" });
    await cargarMarcas();
  } catch (err) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: err.response?.data?.message ?? "No se pudo eliminar",
      customClass: { popup: "rounded-2xl" },
    });
  }
}

// ─── Modal Modelo ─────────────────────────────────────────────────────────
const modalModelo = reactive({
  mostrar: false,
  editando: false,
  idEditando: null,
  marcaId: null,
  nombreMarca: "",
});

const formModelo = reactive({
  nombre: "",
  activo: true,
  imagenMedia: null,
  eliminarImagen: false,
});

const erroresModelo = reactive({ nombre: "" });

function abrirModalModelo(marca, modelo = null) {
  resetFormModelo();
  modalModelo.marcaId = marca.id;
  modalModelo.nombreMarca = marca.nombre ?? "";

  if (modelo) {
    modalModelo.editando = true;
    modalModelo.idEditando = modelo.id;
    formModelo.nombre = modelo.nombre ?? "";
    formModelo.activo = !!modelo.activo;
    formModelo.imagenMedia = modelo.imagen_url ? { url: modelo.imagen_url } : null;
  } else {
    modalModelo.editando = false;
    modalModelo.idEditando = null;
  }

  modalModelo.mostrar = true;
}

function cerrarModalModelo() {
  modalModelo.mostrar = false;
  resetFormModelo();
}

function resetFormModelo() {
  formModelo.nombre = "";
  formModelo.activo = true;
  formModelo.imagenMedia = null;
  formModelo.eliminarImagen = false;
  erroresModelo.nombre = "";
}

function onImagenModeloChange(media) {
  formModelo.imagenMedia = media;
  formModelo.eliminarImagen = false;
}

function limpiarImagenModelo() {
  formModelo.imagenMedia = null;
  formModelo.eliminarImagen = true;
}

async function enviarFormModelo() {
  erroresModelo.nombre = "";

  if (!formModelo.nombre) {
    erroresModelo.nombre = "El nombre es obligatorio.";
    return;
  }
  if (formModelo.nombre.length < 2) {
    erroresModelo.nombre = "Mínimo 2 caracteres.";
    return;
  }

  cargando.value = true;
  try {
    const fd = new FormData();
    fd.append("nombre", formModelo.nombre);
    fd.append("marca_id", String(modalModelo.marcaId));
    fd.append("activo", formModelo.activo ? "1" : "0");
    if (formModelo.imagenMedia?.id) fd.append("imagen_media_id", formModelo.imagenMedia.id);
    if (formModelo.eliminarImagen) fd.append("eliminar_imagen", "1");

    if (modalModelo.editando) {
      fd.append("_method", "PUT");
      await axios.post(`${API_MODELOS}/${modalModelo.idEditando}`, fd);
      Toast.fire({ icon: "success", title: "Modelo actualizado" });
    } else {
      await axios.post(API_MODELOS, fd);
      Toast.fire({ icon: "success", title: "Modelo creado" });
    }

    cerrarModalModelo();
    await cargarMarcas();
  } catch (err) {
    const handled = await ofrecerRecuperacion(err, "/api/modelos", async () => {
      cerrarModalModelo();
      await cargarMarcas();
    });
    if (!handled) {
      const msg = err.response?.data?.message ?? "Ocurrió un error";
      Toast.fire({ icon: "error", title: msg });
      const laravel = err.response?.data?.errors;
      if (laravel?.nombre) erroresModelo.nombre = laravel.nombre[0];
    }
  } finally {
    cargando.value = false;
  }
}

async function confirmarEliminarModelo(modelo) {
  const r = await Swal.fire({
    title: "¿Eliminar modelo?",
    html: `<span class="text-gray-600">Se eliminará el modelo <strong>${modelo.nombre}</strong>.</span>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    cancelButtonColor: "#6b7280",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    customClass: { popup: "rounded-2xl" },
  });
  if (!r.isConfirmed) return;

  try {
    await axios.delete(`${API_MODELOS}/${modelo.id}`);
    Toast.fire({ icon: "success", title: "Modelo eliminado" });
    await cargarMarcas();
  } catch (err) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: err.response?.data?.message ?? "No se pudo eliminar",
      customClass: { popup: "rounded-2xl" },
    });
  }
}
</script>