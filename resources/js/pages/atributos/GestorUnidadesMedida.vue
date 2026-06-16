<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">

      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 ring-1 ring-emerald-100">
            <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h10M4 17h7" />
            </svg>
          </div>
          <div>
            <h1 class="text-xl font-semibold tracking-tight">Unidades de Medida</h1>
            <p class="mt-1 text-sm text-slate-500">
              <span class="font-semibold text-emerald-700">{{ unidadesFiltradas.length }}</span> visibles ·
              <span class="font-semibold text-emerald-700">{{ unidades.length }}</span> total ·
              <span class="font-semibold text-emerald-700">{{ tiposConUnidades.length }}</span> tipos
            </p>
          </div>
        </div>

        <button
          @click="abrirModal()"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:translate-y-px"
        >
          <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-width="2" d="M8 3v10M3 8h10" />
          </svg>
          Nueva unidad
        </button>
      </div>

      <!-- Toolbar -->
      <div class="mt-5 flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
          <!-- Search -->
          <div class="relative w-full sm:max-w-md">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor">
              <circle cx="8.5" cy="8.5" r="5" stroke-width="1.7" />
              <path stroke-linecap="round" stroke-width="1.7" d="M17 17l-3.5-3.5" />
            </svg>

            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar por nombre o abreviatura…"
              class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-10 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
            />

            <button
              v-if="busqueda"
              @click="busqueda=''"
              class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100"
              title="Limpiar"
            >
              <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-width="1.8" d="M4 4l8 8M12 4l-8 8" />
              </svg>
            </button>
          </div>

          <!-- Type filter -->
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-slate-600">Tipo</span>
            <select
              v-model="filtroTipo"
              class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
            >
              <option value="">Todos</option>
              <option v-for="t in TIPOS" :key="t.valor" :value="t.valor">{{ t.label }}</option>
            </select>
          </div>
        </div>

        <button
          @click="limpiarFiltros"
          :disabled="!busqueda && !filtroTipo"
          class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Limpiar
        </button>
      </div>

      <!-- Content -->
      <div class="mt-5">
        <!-- Empty: no data -->
        <div v-if="unidades.length === 0" class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 ring-1 ring-emerald-100">
            <svg class="h-7 w-7 text-emerald-600" viewBox="0 0 48 48" fill="none" stroke="currentColor">
              <rect x="10" y="10" width="28" height="28" rx="6" stroke-width="2"/>
              <path stroke-linecap="round" stroke-width="2" d="M16 24h16M24 16v16"/>
            </svg>
          </div>
          <h3 class="mt-4 text-base font-semibold">Sin unidades de medida</h3>
          <p class="mt-1 text-sm text-slate-500">Crea las unidades que usarás en tus productos (ej: Pieza, Caja, Kg, Litro).</p>
          <button
            @click="abrirModal()"
            class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
          >
            Crear unidad
          </button>
        </div>

        <!-- Empty: no results -->
        <div v-else-if="unidadesFiltradas.length === 0" class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 ring-1 ring-slate-200">
            <svg class="h-7 w-7 text-slate-600" viewBox="0 0 48 48" fill="none" stroke="currentColor">
              <circle cx="22" cy="22" r="14" stroke-width="2"/>
              <path stroke-linecap="round" stroke-width="2" d="M32 32l8 8M18 22h8"/>
            </svg>
          </div>
          <h3 class="mt-4 text-base font-semibold">Sin resultados</h3>
          <p class="mt-1 text-sm text-slate-500">No encontramos coincidencias para “{{ busqueda }}”.</p>
          <button
            @click="limpiarFiltros"
            class="mt-5 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Limpiar filtros
          </button>
        </div>

        <!-- Table -->
        <div v-else class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          <div class="max-h-[calc(100vh-290px)] overflow-auto">
            <table class="min-w-full border-separate border-spacing-0">
              <thead class="sticky top-0 z-10 bg-white">
                <tr>
                  <th class="border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Nombre</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Abreviatura</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Tipo</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                  <th class="border-b border-slate-200 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                </tr>
              </thead>

              <tbody>
                <template v-for="grupo in gruposFiltrados" :key="grupo.tipo">
                  <!-- Group row -->
                  <tr>
                    <td colspan="5" class="border-b border-slate-200 bg-slate-50 px-4 py-2">
                      <div class="flex items-center gap-2">
                        <span
                          class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ring-1"
                          :class="grupoPillClass(grupo.tipo)"
                        >
                          <span class="text-sm leading-none">{{ iconoTipo(grupo.tipo) }}</span>
                          {{ labelTipo(grupo.tipo) }}
                          <span class="rounded-full bg-white/70 px-2 py-0.5 font-bold">{{ grupo.items.length }}</span>
                        </span>
                      </div>
                    </td>
                  </tr>

                  <!-- Rows -->
                  <tr
                    v-for="u in grupo.items"
                    :key="u.id"
                    @dblclick="abrirModal(u)"
                    class="group border-b border-slate-200 hover:bg-slate-50"
                    :class="{ 'opacity-70': !u.activo }"
                  >
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-900">{{ u.nombre }}</span>
                        <span v-if="!u.activo" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                          Inactiva
                        </span>
                      </div>
                    </td>

                    <td class="px-4 py-3">
                      <span class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                        {{ (u.abreviatura ?? '').toLowerCase() }}
                      </span>
                    </td>

                    <td class="px-4 py-3">
                      <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                        :class="tipoPillClass(u.tipo)"
                      >
                        {{ labelTipo(u.tipo) }}
                      </span>
                    </td>

                    <td class="px-4 py-3 text-center">
                      <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                        :class="u.activo ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'"
                      >
                        {{ u.activo ? 'Activa' : 'Inactiva' }}
                      </span>
                    </td>

                    <td class="px-4 py-3 text-right">
                      <div class="inline-flex items-center gap-2">
                        <button
                          class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                          title="Editar"
                          @click="abrirModal(u)"
                        >
                          <svg class="h-4 w-4 text-amber-600" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                              d="M2 11.5V14h2.5l7-7L9 4.5l-7 7zm10.5-8.5a1.414 1.414 0 010 2L11 6.5 9.5 5l1.5-1.5a1.414 1.414 0 011 0z"/>
                          </svg>
                        </button>

                        <button
                          class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50"
                          title="Eliminar"
                          @click="confirmarEliminar(u)"
                        >
                          <svg class="h-4 w-4 text-rose-600" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                              d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10"/>
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            Tip: doble click en una fila para editar más rápido.
          </div>
        </div>
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
            @keydown.esc="cerrarModal"
            tabindex="0"
          >
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
              <!-- Head -->
              <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div class="flex items-start gap-3">
                  <div class="mt-0.5 h-3 w-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></div>
                  <div>
                    <h2 class="text-base font-semibold tracking-tight">
                      {{ modal.editando ? 'Editar unidad' : 'Nueva unidad de medida' }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">Define nombre, abreviatura y tipo.</p>
                  </div>
                </div>

                <button
                  class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                  @click="cerrarModal"
                  aria-label="Cerrar"
                >
                  <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-width="1.8" d="M4 4l8 8M12 4l-8 8"/>
                  </svg>
                </button>
              </div>

              <!-- Body -->
              <div class="space-y-4 px-5 py-4">
                <!-- Nombre -->
                <div>
                  <label class="text-sm font-semibold text-slate-700">
                    Nombre <span class="text-rose-600">*</span>
                  </label>
                  <input
                    v-model.trim="form.nombre"
                    class="mt-2 w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm outline-none transition focus:ring-4"
                    :class="errores.nombre
                      ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                      : 'border-slate-200 focus:border-emerald-400 focus:ring-emerald-100'"
                    placeholder="Ej. Pieza, Caja, Kilogramo…"
                    @keyup.enter="enviarForm"
                    autofocus
                  />
                  <p v-if="errores.nombre" class="mt-1 text-xs font-semibold text-rose-600">{{ errores.nombre }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <!-- Abreviatura -->
                  <div>
                    <label class="text-sm font-semibold text-slate-700">
                      Abreviatura <span class="text-rose-600">*</span>
                    </label>
                    <input
                      v-model.trim="form.abreviatura"
                      maxlength="20"
                      class="mt-2 w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm outline-none transition focus:ring-4"
                      :class="errores.abreviatura
                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                        : 'border-slate-200 focus:border-emerald-400 focus:ring-emerald-100'"
                      placeholder="pza, kg, lt…"
                      @keyup.enter="enviarForm"
                    />
                    <p v-if="errores.abreviatura" class="mt-1 text-xs font-semibold text-rose-600">{{ errores.abreviatura }}</p>
                  </div>

                  <!-- Tipo -->
                  <div>
                    <label class="text-sm font-semibold text-slate-700">
                      Tipo <span class="text-rose-600">*</span>
                    </label>
                    <select
                      v-model="form.tipo"
                      class="mt-2 w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm outline-none transition focus:ring-4"
                      :class="errores.tipo
                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                        : 'border-slate-200 focus:border-emerald-400 focus:ring-emerald-100'"
                    >
                      <option value="" disabled>Seleccionar…</option>
                      <option v-for="t in TIPOS" :key="t.valor" :value="t.valor">{{ t.label }}</option>
                    </select>
                    <p v-if="errores.tipo" class="mt-1 text-xs font-semibold text-rose-600">{{ errores.tipo }}</p>
                  </div>
                </div>

                <!-- Preview -->
                <div v-if="form.nombre && form.abreviatura" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Vista previa</div>
                  <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                      {{ form.nombre }}
                    </span>
                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                      {{ form.abreviatura.toLowerCase() }}
                    </span>
                    <span
                      v-if="form.tipo"
                      class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                      :class="tipoPillClass(form.tipo)"
                    >
                      {{ labelTipo(form.tipo) }}
                    </span>
                  </div>
                </div>

                <!-- Estado -->
                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                  <div>
                    <div class="text-sm font-semibold text-slate-700">Estado</div>
                    <div class="mt-1 text-sm text-slate-500">{{ form.activo ? 'La unidad estará activa.' : 'La unidad quedará inactiva.' }}</div>
                  </div>

                  <button
                    type="button"
                    class="relative inline-flex h-7 w-12 items-center rounded-full transition"
                    :class="form.activo ? 'bg-emerald-600' : 'bg-slate-300'"
                    @click="form.activo = !form.activo"
                  >
                    <span
                      class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                      :class="form.activo ? 'translate-x-6' : 'translate-x-1'"
                    />
                  </button>
                </div>
              </div>

              <!-- Footer -->
              <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button
                  class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                  @click="cerrarModal"
                >
                  Cancelar
                </button>

                <button
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
                  :disabled="cargando"
                  @click="enviarForm"
                >
                  <svg v-if="cargando" class="h-4 w-4 animate-spin" viewBox="0 0 20 20" fill="none">
                    <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="2.5" stroke-dasharray="32" stroke-dashoffset="12"/>
                  </svg>
                  {{ modal.editando ? 'Guardar cambios' : 'Crear unidad' }}
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
import { ref, reactive, computed, nextTick } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { ofrecerRecuperacion } from '@/helpers/recuperar'

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
})

const TIPOS = [
  { valor: 'cantidad', label: 'Cantidad', icono: '#' },
  { valor: 'peso',     label: 'Peso',     icono: '⚖' },
  { valor: 'volumen',  label: 'Volumen',  icono: '◌' },
  { valor: 'longitud', label: 'Longitud', icono: '↔' },
]

function labelTipo(valor) { return TIPOS.find(t => t.valor === valor)?.label ?? valor }
function iconoTipo(valor) { return TIPOS.find(t => t.valor === valor)?.icono ?? '·' }

function tipoPillClass(tipo) {
  switch (tipo) {
    case 'cantidad': return 'bg-blue-50 text-blue-700 ring-blue-200'
    case 'peso':     return 'bg-violet-50 text-violet-700 ring-violet-200'
    case 'volumen':  return 'bg-cyan-50 text-cyan-700 ring-cyan-200'
    case 'longitud': return 'bg-amber-50 text-amber-800 ring-amber-200'
    default:         return 'bg-slate-100 text-slate-700 ring-slate-200'
  }
}
function grupoPillClass(tipo) {
  // similar al de tipo, pero un pelín más marcado
  switch (tipo) {
    case 'cantidad': return 'bg-blue-100/60 text-blue-800 ring-blue-200'
    case 'peso':     return 'bg-violet-100/60 text-violet-800 ring-violet-200'
    case 'volumen':  return 'bg-cyan-100/60 text-cyan-800 ring-cyan-200'
    case 'longitud': return 'bg-amber-100/60 text-amber-900 ring-amber-200'
    default:         return 'bg-slate-100 text-slate-800 ring-slate-200'
  }
}

const unidades   = ref([])
const cargando   = ref(false)
const busqueda   = ref('')
const filtroTipo = ref('')

const modal   = reactive({ mostrar: false, editando: false, idEditando: null })
const form    = reactive({ nombre: '', abreviatura: '', tipo: '', activo: true })
const errores = reactive({ nombre: '', abreviatura: '', tipo: '' })

const API = '/api/unidades-medida'

const unidadesFiltradas = computed(() => {
  let lista = unidades.value

  if (filtroTipo.value) lista = lista.filter(u => u.tipo === filtroTipo.value)

  const q = busqueda.value.trim().toLowerCase()
  if (q) {
    lista = lista.filter(u => {
      const nombre = (u.nombre ?? '').toLowerCase()
      const abrev  = (u.abreviatura ?? '').toLowerCase()
      return nombre.includes(q) || abrev.includes(q)
    })
  }
  return lista
})

const gruposFiltrados = computed(() => {
  const mapa = {}
  unidadesFiltradas.value.forEach(u => {
    if (!mapa[u.tipo]) mapa[u.tipo] = []
    mapa[u.tipo].push(u)
  })
  return TIPOS.filter(t => mapa[t.valor]).map(t => ({ tipo: t.valor, items: mapa[t.valor] }))
})

const tiposConUnidades = computed(() =>
  TIPOS.filter(t => unidades.value.some(u => u.tipo === t.valor))
)

async function cargarUnidades() {
  try {
    const { data } = await axios.get(API)
    unidades.value = data
  } catch {
    Toast.fire({ icon: 'error', title: 'Error al cargar unidades' })
  }
}
cargarUnidades()

function abrirModal(u = null) {
  form.nombre      = u?.nombre ?? ''
  form.abreviatura = u?.abreviatura ?? ''
  form.tipo        = u?.tipo ?? ''
  form.activo      = (u && u.activo !== undefined && u.activo !== null) ? !!u.activo : true

  errores.nombre = errores.abreviatura = errores.tipo = ''
  modal.editando = !!u
  modal.idEditando = u?.id ?? null
  modal.mostrar = true

  nextTick(() => {
    document.querySelector('[tabindex="0"]')?.focus()
  })
}

function cerrarModal() {
  modal.mostrar = false
  errores.nombre = errores.abreviatura = errores.tipo = ''
}

function limpiarFiltros() {
  busqueda.value = ''
  filtroTipo.value = ''
}

function validar() {
  errores.nombre = errores.abreviatura = errores.tipo = ''
  let ok = true

  if (!form.nombre) { errores.nombre = 'Obligatorio.'; ok = false }
  else if (form.nombre.length < 2) { errores.nombre = 'Mínimo 2 caracteres.'; ok = false }

  if (!form.abreviatura) { errores.abreviatura = 'Obligatorio.'; ok = false }
  if (!form.tipo) { errores.tipo = 'Selecciona un tipo.'; ok = false }

  return ok
}

async function enviarForm() {
  if (!validar()) return
  cargando.value = true

  try {
    const payload = {
      nombre: form.nombre,
      abreviatura: form.abreviatura,
      tipo: form.tipo,
      activo: form.activo,
    }

    if (modal.editando) {
      await axios.put(`${API}/${modal.idEditando}`, payload)
      Toast.fire({ icon: 'success', title: 'Unidad actualizada' })
    } else {
      await axios.post(API, payload)
      Toast.fire({ icon: 'success', title: 'Unidad creada' })
    }

    cerrarModal()
    await cargarUnidades()
  } catch (err) {
    const handled = await ofrecerRecuperacion(err, '/api/unidades-medida', async () => {
      cerrarModal()
      await cargarUnidades()
    })
    if (!handled) {
      Toast.fire({ icon: 'error', title: err.response?.data?.message ?? 'Error' })
      const e = err.response?.data?.errors ?? {}
      if (e.nombre) errores.nombre = e.nombre[0]
      if (e.abreviatura) errores.abreviatura = e.abreviatura[0]
      if (e.tipo) errores.tipo = e.tipo[0]
    }
  } finally {
    cargando.value = false
  }
}

async function confirmarEliminar(u) {
  const r = await Swal.fire({
    title: `Eliminar "${u.nombre}"`,
    html: `<p style="color:#475569;font-size:.9rem">Se eliminará <strong>${u.nombre} (${u.abreviatura})</strong>.</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  })

  if (!r.isConfirmed) return

  try {
    await axios.delete(`${API}/${u.id}`)
    Toast.fire({ icon: 'success', title: 'Unidad eliminada' })
    await cargarUnidades()
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: err.response?.data?.message ?? 'No se pudo eliminar',
    })
  }
}
</script>
