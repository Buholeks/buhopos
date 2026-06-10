<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">

      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 ring-1 ring-indigo-100">
            <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h12M3 17h6"/>
            </svg>
          </div>
          <div>
            <h1 class="text-xl font-semibold tracking-tight">Gestión de Categorías</h1>
            <p class="mt-1 text-sm text-slate-500">Organiza tus categorías en niveles ilimitados</p>
          </div>
        </div>

        <button
          @click="abrirModalCrear(null)"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:translate-y-px"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Nueva categoría raíz
        </button>
      </div>

      <!-- Card -->
      <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <!-- Empty -->
        <div
          v-if="categorias.length === 0"
          class="flex flex-col items-center justify-center px-6 py-14 text-center"
        >
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 ring-1 ring-slate-200">
            <svg class="h-7 w-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7h18M3 12h12M3 17h6"/>
            </svg>
          </div>
          <p class="mt-4 text-sm text-slate-600">No hay categorías aún. Crea la primera para comenzar.</p>
          <button
            class="mt-5 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            @click="abrirModalCrear(null)"
          >
            Crear categoría
          </button>
        </div>

        <!-- Tree list -->
        <ul v-else class="divide-y divide-slate-100">
          <CategoriasNode
            v-for="cat in categorias"
            :key="cat.id"
            :categoria="cat"
            :profundidad="0"
            @agregar="abrirModalCrear"
            @editar="abrirModalEditar"
            @eliminar="confirmarEliminar"
          />
        </ul>
      </div>

      <!-- Modal -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition ease-out duration-150"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition ease-in duration-120"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-if="modal.mostrar"
            class="fixed inset-0 z-120 flex items-center justify-center bg-black/55 p-4"
            @mousedown.self="cerrarModal"
          >
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">

              <!-- Modal header -->
              <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                  <h2 class="text-base font-semibold tracking-tight">
                    {{ modal.editando ? 'Editar categoría' : 'Nueva categoría' }}
                  </h2>

                  <div class="mt-1 text-sm text-slate-500">
                    <template v-if="modal.nombrePadre">
                      Subcategoría de:
                      <span class="font-semibold text-indigo-700">{{ modal.nombrePadre }}</span>
                    </template>
                    <template v-else>
                      Categoría raíz (sin padre)
                    </template>
                  </div>
                </div>

                <button
                  @click="cerrarModal"
                  class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                  type="button"
                  title="Cerrar"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <!-- Modal body -->
              <div class="space-y-4 px-5 py-4">
                <!-- Nombre -->
                <div>
                  <!-- Si quieres usar BaseInput, úsalo como Nombre (no como buscar) -->
                  <BaseInput
                    v-model.trim="form.nombre"
                    label="Nombre"
                    :required="true"
                    placeholder="Ej. Electrónica"
                    :error="errores.nombre"
                    @keyup.enter="enviarFormulario"
                  />
                </div>

                <!-- Descripción -->
                <div>
                  <label class="text-sm font-semibold text-slate-700">Descripción</label>
                  <textarea
                    v-model.trim="form.descripcion"
                    rows="3"
                    placeholder="Descripción opcional…"
                    class="mt-2 w-full resize-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                  />
                </div>

                <!-- Estado -->
                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                  <div>
                    <div class="text-sm font-semibold text-slate-700">Estado</div>
                    <div class="mt-1 text-sm text-slate-500">{{ form.activo ? 'Activa' : 'Inactiva' }}</div>
                  </div>

                  <button
                    type="button"
                    @click="form.activo = !form.activo"
                    class="relative inline-flex h-7 w-12 items-center rounded-full transition"
                    :class="form.activo ? 'bg-indigo-600' : 'bg-slate-300'"
                  >
                    <span
                      class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                      :class="form.activo ? 'translate-x-6' : 'translate-x-1'"
                    />
                  </button>
                </div>
              </div>

              <!-- Modal footer -->
              <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button
                  @click="cerrarModal"
                  class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                  type="button"
                >
                  Cancelar
                </button>

                <button
                  @click="enviarFormulario"
                  :disabled="cargando"
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                  type="button"
                >
                  <svg v-if="cargando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                  {{ modal.editando ? 'Guardar cambios' : 'Crear categoría' }}
                </button>
              </div>

            </div>
          </div>
        </Transition>
      </Teleport>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, defineComponent, h } from "vue";
import axios from "axios";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import BaseInput from "../../components/ui/BaseInput.vue";

// ─── Toast ────────────────────────────────────────────────────────────────────
const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
});

// ─── Estado ───────────────────────────────────────────────────────────────────
const categorias = ref([]);
const cargando = ref(false);

const modal = reactive({
  mostrar: false,
  editando: false,
  idEditando: null,
  padreId: null,
  nombrePadre: null,
});

const form = reactive({ nombre: "", descripcion: "", activo: true });
const errores = reactive({ nombre: "" });

const API = "/api/categorias";

// ─── Carga inicial ────────────────────────────────────────────────────────────
async function cargarCategorias() {
  try {
    const { data } = await axios.get(API);
    categorias.value = data;
  } catch {
    Toast.fire({ icon: "error", title: "Error al cargar categorías" });
  }
}
cargarCategorias();

// ─── Modal ────────────────────────────────────────────────────────────────────
function abrirModalCrear(categoriaPadre) {
  resetForm();
  modal.editando = false;
  modal.idEditando = null;
  modal.padreId = categoriaPadre?.id ?? null;
  modal.nombrePadre = categoriaPadre?.nombre ?? null;
  modal.mostrar = true;
}

function abrirModalEditar(categoria) {
  resetForm();
  modal.editando = true;
  modal.idEditando = categoria.id;
  modal.padreId = categoria.categoria_padre_id ?? null;
  modal.nombrePadre = categoria.padre?.nombre ?? null;
  form.nombre = categoria.nombre;
  form.descripcion = categoria.descripcion ?? "";
  form.activo = categoria.activo ?? true;
  modal.mostrar = true;
}

function cerrarModal() {
  modal.mostrar = false;
  resetForm();
}

function resetForm() {
  form.nombre = "";
  form.descripcion = "";
  form.activo = true;
  errores.nombre = "";
}

// ─── Validación ───────────────────────────────────────────────────────────────
function validar() {
  errores.nombre = "";
  if (!form.nombre) {
    errores.nombre = "El nombre es obligatorio.";
    return false;
  }
  if (form.nombre.length < 2) {
    errores.nombre = "Mínimo 2 caracteres.";
    return false;
  }
  return true;
}

// ─── Envío ────────────────────────────────────────────────────────────────────
async function enviarFormulario() {
  if (!validar()) return;
  cargando.value = true;

  try {
    const payload = {
      nombre: form.nombre,
      descripcion: form.descripcion,
      activo: form.activo,
      categoria_padre_id: modal.padreId,
    };

    if (modal.editando) {
      await axios.put(`${API}/${modal.idEditando}`, payload);
      Toast.fire({ icon: "success", title: "Categoría actualizada" });
    } else {
      await axios.post(API, payload);
      Toast.fire({ icon: "success", title: "Categoría creada" });
    }

    cerrarModal();
    await cargarCategorias();
  } catch (err) {
    const msg = err.response?.data?.message ?? "Ocurrió un error";
    Toast.fire({ icon: "error", title: msg });

    const laravel = err.response?.data?.errors;
    if (laravel?.nombre) errores.nombre = laravel.nombre[0];
  } finally {
    cargando.value = false;
  }
}

// ─── Eliminar ─────────────────────────────────────────────────────────────────
async function confirmarEliminar(categoria) {
  const resultado = await Swal.fire({
    title: "¿Eliminar categoría?",
    html: `<span class="text-slate-600">Se eliminará <strong>${categoria.nombre}</strong> y todas sus subcategorías.</span>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    cancelButtonColor: "#6b7280",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    customClass: { popup: "rounded-2xl" },
  });

  if (!resultado.isConfirmed) return;

  try {
    await axios.delete(`${API}/${categoria.id}`);
    Toast.fire({ icon: "success", title: "Categoría eliminada" });
    await cargarCategorias();
  } catch (err) {
    const msg = err.response?.data?.message ?? "No se pudo eliminar";
    Swal.fire({
      icon: "error",
      title: "Error",
      text: msg,
      customClass: { popup: "rounded-2xl" },
    });
  }
}

// ─── Componente recursivo ─────────────────────────────────────────────────────
const CategoriasNode = defineComponent({
  name: "CategoriasNode",
  props: {
    categoria: { type: Object, required: true },
    profundidad: { type: Number, default: 0 },
  },
  emits: ["agregar", "editar", "eliminar"],

  setup(props, { emit }) {
    const abierto = ref(true);
    const auth = useAuthStore();

    const obtenerHijos = () =>
      Array.isArray(props.categoria.hijos_recursivos)
        ? props.categoria.hijos_recursivos
        : [];

    const tieneHijos = () => obtenerHijos().length > 0;

    const coloresBadge = [
      "bg-indigo-50 text-indigo-700 ring-indigo-200",
      "bg-violet-50 text-violet-700 ring-violet-200",
      "bg-sky-50 text-sky-700 ring-sky-200",
      "bg-teal-50 text-teal-700 ring-teal-200",
      "bg-amber-50 text-amber-700 ring-amber-200",
    ];
    const colorBadge = coloresBadge[props.profundidad % coloresBadge.length];

    return () => {
      const hijos = obtenerHijos();
      const indentPx = props.profundidad * 22;

      return h("li", { class: "group" }, [
        h(
          "div",
          {
            class: [
              "flex items-center gap-3 px-4 py-3",
              "hover:bg-slate-50 transition-colors",
            ].join(" "),
            style: { paddingLeft: `calc(1rem + ${indentPx}px)` },
          },
          [
            // Chevron
            h(
              "button",
              {
                class: [
                  "inline-flex h-8 w-8 items-center justify-center rounded-xl",
                  "border border-slate-200 bg-white text-slate-500",
                  "transition hover:bg-slate-50",
                  tieneHijos() ? "" : "invisible",
                ].join(" "),
                onClick: () => {
                  if (tieneHijos()) abierto.value = !abierto.value;
                },
                title: "Expandir/colapsar",
              },
              [
                h(
                  "svg",
                  {
                    class: [
                      "h-4 w-4 transition-transform",
                      abierto.value ? "rotate-90" : "",
                    ].join(" "),
                    fill: "none",
                    stroke: "currentColor",
                    viewBox: "0 0 24 24",
                  },
                  [
                    h("path", {
                      "stroke-linecap": "round",
                      "stroke-linejoin": "round",
                      "stroke-width": "2",
                      d: "M9 5l7 7-7 7",
                    }),
                  ],
                ),
              ],
            ),

            // Icon
            h(
              "div",
              { class: "flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200" },
              [
                h(
                  "svg",
                  {
                    class: "h-4 w-4 text-slate-500",
                    fill: "none",
                    stroke: "currentColor",
                    viewBox: "0 0 24 24",
                  },
                  [
                    h("path", {
                      "stroke-linecap": "round",
                      "stroke-linejoin": "round",
                      "stroke-width": "1.8",
                      d: "M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z",
                    }),
                  ],
                ),
              ],
            ),

            // Name
            h(
              "span",
              { class: "min-w-0 flex-1 truncate text-sm font-semibold text-slate-900" },
              props.categoria.nombre,
            ),

            // Level badge
            h(
              "span",
              {
                class: [
                  "hidden sm:inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1",
                  colorBadge,
                ].join(" "),
              },
              props.profundidad === 0 ? "Raíz" : `Nivel ${props.profundidad}`,
            ),

            // Status badge
            h(
              "span",
              {
                class: [
                  "inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1",
                  props.categoria.activo !== false
                    ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
                    : "bg-slate-100 text-slate-600 ring-slate-200",
                ].join(" "),
              },
              props.categoria.activo !== false ? "Activa" : "Inactiva",
            ),

            // Children count
            tieneHijos() &&
              h(
                "span",
                { class: "hidden sm:inline-flex text-xs font-semibold text-slate-400" },
                `${hijos.length} ${hijos.length === 1 ? "sub" : "subs"}`,
              ),

            // Actions
            h(
              "div",
              {
                class:
                  "inline-flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity",
              },
              [
                h(
                  "button",
                  {
                    title: "Agregar subcategoría",
                    class:
                      "inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-indigo-50",
                    onClick: () => emit("agregar", props.categoria),
                  },
                  [
                    h(
                      "svg",
                      { class: "h-4 w-4 text-indigo-600", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
                      [
                        h("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M12 4v16m8-8H4",
                        }),
                      ],
                    ),
                  ],
                ),

                auth.can('productos.editar') && h(
                  "button",
                  {
                    title: "Editar",
                    class:
                      "inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-amber-50",
                    onClick: () => emit("editar", props.categoria),
                  },
                  [
                    h(
                      "svg",
                      { class: "h-4 w-4 text-amber-600", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
                      [
                        h("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z",
                        }),
                      ],
                    ),
                  ],
                ),

                auth.can('productos.eliminar') && h(
                  "button",
                  {
                    title: "Eliminar",
                    class:
                      "inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-rose-50",
                    onClick: () => emit("eliminar", props.categoria),
                  },
                  [
                    h(
                      "svg",
                      { class: "h-4 w-4 text-rose-600", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
                      [
                        h("path", {
                          "stroke-linecap": "round",
                          "stroke-linejoin": "round",
                          "stroke-width": "2",
                          d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16",
                        }),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),

        // Children list
        tieneHijos() &&
          abierto.value &&
          h(
            "ul",
            { class: "ml-10 border-l border-slate-100 divide-y divide-slate-50" },
            hijos.map((hijo) =>
              h(CategoriasNode, {
                key: hijo.id,
                categoria: hijo,
                profundidad: props.profundidad + 1,
                onAgregar: (c) => emit("agregar", c),
                onEditar: (c) => emit("editar", c),
                onEliminar: (c) => emit("eliminar", c),
              }),
            ),
          ),
      ]);
    };
  },
});
</script>
