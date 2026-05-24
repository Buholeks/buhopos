<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="flex items-start gap-3">
      <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 ring-1 ring-indigo-100">
        <List class="h-5 w-5 text-indigo-600" />
      </div>
      <div>
        <h1 class="text-xl font-semibold tracking-tight">Gestión de Marcas y Modelos</h1>
        <p class="mt-1 text-sm text-slate-500">Administra marcas y sus modelos por empresa</p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button
        type="button"
        @click="$emit('nueva-marca')"
        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:translate-y-px"
      >
        <Plus class="h-4 w-4" />
        Nueva marca
      </button>
    </div>
  </div>
</template>

<script setup>
import { List, Plus } from "lucide-vue-next";
defineEmits(["nueva-marca"]);
</script>