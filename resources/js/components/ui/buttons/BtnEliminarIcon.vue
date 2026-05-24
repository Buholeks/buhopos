<template>
  <BaseIconButton
    :icon="Trash2"
    :variant="variant"
    :size="size"
    :tooltip="tooltip"
    v-bind="$attrs"
  />
</template>

<script setup>
import BaseIconButton from './BaseIconButton.vue'
import { Trash2 } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'danger' },
  size: { type: String, default: 'md' },
  tooltip: { type: String, default: 'Eliminar' },
})
</script>
