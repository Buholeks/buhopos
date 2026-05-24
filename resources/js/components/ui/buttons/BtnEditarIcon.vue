<template>
  <BaseIconButton
    :icon="Pencil"
    :variant="variant"
    :size="size"
    :tooltip="tooltip"
    v-bind="$attrs"
  />
</template>

<script setup>
import BaseIconButton from './BaseIconButton.vue'
import { Pencil } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'ghost' },
  size: { type: String, default: 'md' },
  tooltip: { type: String, default: 'Editar' },
})
</script>
