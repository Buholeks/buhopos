<template>
  <div
    class="flex items-center gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3 pl-12 last:border-b-0 hover:bg-indigo-50/40"
  >
    <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
      <img
        v-if="modelo.imagen_url"
        :src="modelo.imagen_url"
        :alt="modelo.nombre"
        class="h-full w-full object-contain p-1"
      />
      <ImageOff v-else class="h-4 w-4 text-slate-300" />
    </div>

    <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ modelo.nombre }}</span>

    <span
      class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
      :class="modelo.activo
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
        : 'bg-slate-100 text-slate-600 ring-slate-200'"
    >
      {{ modelo.activo ? "Activo" : "Inactivo" }}
    </span>

    <div class="inline-flex items-center gap-2">
      <button
        type="button"
        @click="$emit('editar')"
        title="Editar modelo"
        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-amber-50"
      >
        <Pencil class="h-4 w-4 text-amber-600" />
      </button>

      <button
        type="button"
        @click="$emit('eliminar')"
        title="Eliminar modelo"
        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white transition hover:bg-rose-50"
      >
        <Trash2 class="h-4 w-4 text-rose-600" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ImageOff, Pencil, Trash2 } from "lucide-vue-next";
defineProps({ modelo: { type: Object, required: true } });
defineEmits(["editar", "eliminar"]);
</script>