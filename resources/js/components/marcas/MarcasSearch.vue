<template>
  <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="w-full sm:max-w-md">
      <BaseInput
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        placeholder="Buscar marca o modelo…"
      />
      <div v-if="modelValue" class="mt-2 flex items-center justify-between text-xs text-slate-500">
        <span v-if="count === 0">Sin resultados</span>
        <span v-else>
          <span class="font-semibold text-indigo-700">{{ count }}</span> marca(s) encontrada(s)
        </span>

        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          @click="$emit('update:modelValue', '')"
          title="Limpiar"
        >
          <X class="h-4 w-4" />
          Limpiar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseInput from "@/components/ui/BaseInput.vue";
import { X } from "lucide-vue-next";

defineProps({
  modelValue: { type: String, default: "" },
  count: { type: Number, default: 0 },
  hasBusqueda: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>