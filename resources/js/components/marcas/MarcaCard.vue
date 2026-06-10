<template>
  <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <!-- Marca row -->
    <div class="flex items-center gap-4 px-5 py-4">
      <!-- Logo -->
      <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200">
        <img
          v-if="marca.logo_url"
          :src="marca.logo_url"
          :alt="marca.nombre"
          class="h-full w-full object-contain p-1"
        />
        <ImageOff v-else class="h-6 w-6 text-slate-300" />
      </div>

      <!-- Text -->
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-900">{{ marca.nombre }}</p>
        <p class="mt-0.5 text-xs text-slate-500">
          {{ marca.modelos?.length || 0 }} {{ (marca.modelos?.length || 0) === 1 ? "modelo" : "modelos" }}
        </p>
      </div>

      <!-- Estado -->
      <span
        class="hidden sm:inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
        :class="marca.activo
          ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
          : 'bg-slate-100 text-slate-600 ring-slate-200'"
      >
        {{ marca.activo ? "Activa" : "Inactiva" }}
      </span>

      <!-- Actions desktop -->
      <div class="hidden items-center gap-2 sm:inline-flex">
        <button
          type="button"
          @click="$emit('nuevo-modelo', marca)"
          title="Agregar modelo"
          class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-indigo-50"
        >
          <Plus class="h-4 w-4 text-indigo-600" />
        </button>

        <button
          v-if="auth.can('productos.editar')"
          type="button"
          @click="$emit('editar-marca', marca)"
          title="Editar marca"
          class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-amber-50"
        >
          <Pencil class="h-4 w-4 text-amber-600" />
        </button>

        <button
          v-if="auth.can('productos.eliminar')"
          type="button"
          @click="$emit('eliminar-marca', marca)"
          title="Eliminar marca"
          class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-rose-50"
        >
          <Trash2 class="h-4 w-4 text-rose-600" />
        </button>
      </div>

      <!-- Toggle -->
      <button
        v-if="marca.modelos?.length"
        type="button"
        @click="$emit('toggle', marca.id)"
        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
        :class="abierta ? 'ring-2 ring-indigo-100' : ''"
        :aria-expanded="abierta"
        title="Mostrar modelos"
      >
        <ChevronRight class="h-4 w-4 transition-transform" :class="abierta ? 'rotate-90' : ''" />
      </button>
      <div v-else class="h-9 w-9"></div>
    </div>

    <!-- Mobile actions -->
    <div class="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:hidden">
      <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
        :class="marca.activo
          ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
          : 'bg-slate-100 text-slate-600 ring-slate-200'"
      >
        {{ marca.activo ? "Activa" : "Inactiva" }}
      </span>

      <div class="inline-flex items-center gap-2">
        <button
          type="button"
          @click="$emit('nuevo-modelo', marca)"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-indigo-50"
        >
          <Plus class="h-4 w-4 text-indigo-600" />
          Modelo
        </button>

        <button
          v-if="auth.can('productos.editar')"
          type="button"
          @click="$emit('editar-marca', marca)"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-amber-50"
          title="Editar"
        >
          <Pencil class="h-4 w-4 text-amber-600" />
        </button>

        <button
          v-if="auth.can('productos.eliminar')"
          type="button"
          @click="$emit('eliminar-marca', marca)"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-rose-50"
          title="Eliminar"
        >
          <Trash2 class="h-4 w-4 text-rose-600" />
        </button>
      </div>
    </div>

    <!-- Modelos -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-120 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="abierta && modelosFiltrados.length" class="border-t border-slate-200">
        <ModeloRow
          v-for="modelo in modelosFiltrados"
          :key="modelo.id"
          :modelo="modelo"
          @editar="$emit('editar-modelo', { marca, modelo })"
          @eliminar="$emit('eliminar-modelo', modelo)"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup>
import ModeloRow from "@/components/marcas/ModeloRow.vue";
import { ImageOff, Plus, Pencil, Trash2, ChevronRight } from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();

const props = defineProps({
  marca: { type: Object, required: true },
  abierta: { type: Boolean, default: false },
  busqueda: { type: String, default: "" },
});

defineEmits([
  "toggle",
  "nuevo-modelo",
  "editar-marca",
  "eliminar-marca",
  "editar-modelo",
  "eliminar-modelo",
]);

const modelosFiltrados = computed(() => {
  const texto = props.busqueda.trim().toLowerCase();
  const modelos = Array.isArray(props.marca.modelos) ? props.marca.modelos : [];

  if (!texto) return modelos;

  const nombreMarca = (props.marca.nombre ?? "").toLowerCase();
  if (nombreMarca.includes(texto)) return modelos;

  return modelos.filter((m) => (m.nombre ?? "").toLowerCase().includes(texto));
});

import { computed } from "vue";
</script>