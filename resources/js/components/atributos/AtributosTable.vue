<template>
  <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <!-- Toolbar -->
    <div class="flex flex-col gap-2 border-b border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-sm text-slate-600">
        Mostrando
        <span class="font-semibold text-slate-900">{{ tiposFiltrados.length }}</span>
        de
        <span class="font-semibold text-slate-900">{{ tipos.length }}</span>
        tipos
        <span
          v-if="busqueda"
          class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100"
        >
          Búsqueda: “{{ busqueda }}”
        </span>
      </div>

      <div class="flex items-center gap-2 sm:hidden">
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
          @click="$emit('expandir')"
          :disabled="tiposFiltrados.length === 0"
        >
          Expandir
        </button>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
          @click="$emit('colapsar')"
          :disabled="tiposFiltrados.length === 0"
        >
          Colapsar
        </button>
      </div>
    </div>

    <div class="max-h-[calc(100vh-270px)] overflow-auto">
      <table class="min-w-full border-separate border-spacing-0">
        <thead class="sticky top-0 z-10 bg-white">
          <tr>
            <th class="w-12 border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500"></th>
            <th class="border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
              Tipo / Valor
            </th>
            <th class="border-b border-slate-200 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">
              Cantidad
            </th>
            <th class="border-b border-slate-200 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">
              Estado
            </th>
            <th class="border-b border-slate-200 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">
              Acciones
            </th>
          </tr>
        </thead>

        <tbody>
          <template v-for="tipo in tiposFiltrados" :key="tipo.id">
            <!-- Tipo row -->
            <tr
              class="border-b border-slate-200 hover:bg-slate-50"
              :class="tiposAbiertos.has(tipo.id) ? 'bg-emerald-50/40' : 'bg-white'"
            >
              <!-- Chevron -->
              <td class="px-4 py-3 align-middle">
                <button
                  type="button"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                  :class="tiposAbiertos.has(tipo.id) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ''"
                  @click="$emit('toggle-tipo', tipo.id)"
                  :aria-expanded="tiposAbiertos.has(tipo.id)"
                >
                  <svg
                    class="h-4 w-4 transition-transform"
                    :class="tiposAbiertos.has(tipo.id) ? 'rotate-90' : ''"
                    viewBox="0 0 16 16" fill="none" stroke="currentColor"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4l4 4-4 4" />
                  </svg>
                </button>
              </td>

              <!-- Name -->
              <td class="px-4 py-3 align-middle">
                <button type="button" class="flex w-full items-center gap-2 text-left focus:outline-none"
                  @click="$emit('toggle-tipo', tipo.id)">
                  <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                  <span class="font-semibold text-slate-900">{{ tipo.nombre }}</span>
                  <span class="ml-2 hidden text-xs text-slate-400 sm:inline">
                    (click para {{ tiposAbiertos.has(tipo.id) ? "colapsar" : "expandir" }})
                  </span>
                </button>
              </td>

              <!-- Count -->
              <td class="px-4 py-3 text-center align-middle">
                <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">
                  {{ getValoresFiltrados(tipo).length }}
                </span>
              </td>

              <!-- Estado -->
              <td class="px-4 py-3 text-center align-middle">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                  :class="tipo.activo ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'"
                >
                  {{ tipo.activo ? "Activo" : "Inactivo" }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 text-right align-middle">
                <div class="inline-flex items-center gap-2">
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-emerald-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                    title="Agregar valor"
                    @click="$emit('abrir-modal-valor', tipo)"
                  >
                    <svg class="h-4 w-4 text-emerald-700" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-width="2" d="M8 3v10M3 8h10" />
                    </svg>
                  </button>

                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                    title="Editar tipo"
                    @click="$emit('abrir-modal-tipo', tipo)"
                  >
                    <svg class="h-4 w-4 text-amber-600" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                        d="M2 11.5V14h2.5l7-7L9 4.5l-7 7zm10.5-8.5a1.414 1.414 0 010 2L11 6.5 9.5 5l1.5-1.5a1.414 1.414 0 011 0z" />
                    </svg>
                  </button>

                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100"
                    title="Eliminar tipo"
                    @click="$emit('eliminar-tipo', tipo)"
                  >
                    <svg class="h-4 w-4 text-rose-600" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                        d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Expanded values -->
            <template v-if="tiposAbiertos.has(tipo.id)">
              <tr
                v-for="atributo in getValoresFiltrados(tipo)"
                :key="atributo.id"
                class="border-b border-slate-200 bg-slate-50/60 hover:bg-emerald-50/30"
              >
                <td class="px-4 py-3"></td>

                <td class="px-4 py-3">
                  <div class="flex items-center gap-2 pl-2">
                    <span class="h-px w-4 bg-slate-300"></span>
                    <span class="inline-flex rounded-lg bg-white px-3 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">
                      {{ atributo.valor }}
                    </span>
                  </div>
                </td>

                <td class="px-4 py-3 text-center">
                  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="tipoPillClass(tipo.nombre)">
                    {{ tipo.nombre }}
                  </span>
                </td>

                <td class="px-4 py-3 text-center">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="atributo.activo ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'"
                  >
                    {{ atributo.activo ? "Activo" : "Inactivo" }}
                  </span>
                </td>

                <td class="px-4 py-3 text-right">
                  <div class="inline-flex items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                      title="Editar valor"
                      @click="$emit('abrir-modal-valor', tipo, atributo)"
                    >
                      <svg class="h-4 w-4 text-amber-600" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                          d="M2 11.5V14h2.5l7-7L9 4.5l-7 7zm10.5-8.5a1.414 1.414 0 010 2L11 6.5 9.5 5l1.5-1.5a1.414 1.414 0 011 0z" />
                      </svg>
                    </button>

                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100"
                      title="Eliminar valor"
                      @click="$emit('eliminar-valor', atributo)"
                    >
                      <svg class="h-4 w-4 text-rose-600" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                          d="M2 4h12M5 4V2h6v2M6 7v5M10 7v5M3 4l1 10h8l1-10" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Inline add row -->
              <tr class="border-b border-slate-200 bg-white hover:bg-emerald-50/40">
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3" colspan="4">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700"
                    @click="$emit('abrir-modal-valor', tipo)"
                  >
                    <svg class="h-4 w-4" viewBox="0 0 14 14" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-width="2" d="M7 2v10M2 7h10" />
                    </svg>
                    Agregar valor a {{ tipo.nombre }}
                  </button>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
      Tip: al buscar por valor, se expanden tipos automáticamente.
      <span class="ml-2 text-slate-400 hidden sm:inline">— Sí, esto ahorra clicks como si fueran impuestos.</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  tipos: { type: Array, required: true },
  tiposFiltrados: { type: Array, required: true },
  tiposAbiertos: { type: Object, required: true }, // Set reactivo
  busqueda: { type: String, default: "" },
  getValoresFiltrados: { type: Function, required: true },
  tipoPillClass: { type: Function, required: true },
});

defineEmits([
  "toggle-tipo",
  "abrir-modal-tipo",
  "abrir-modal-valor",
  "eliminar-tipo",
  "eliminar-valor",
  "expandir",
  "colapsar",
]);
</script>
