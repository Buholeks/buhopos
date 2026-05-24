<template>
  <BaseIconButton
    :icon="ChevronRight"
    :variant="variant"
    :size="size"
    :tooltip="tooltip"
    v-bind="$attrs"
  />
</template>

<script setup>
import BaseIconButton from './BaseIconButton.vue'
import { ChevronRight } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'outline' },
  size: { type: String, default: 'sm' },
  tooltip: { type: String, default: 'Siguiente' },
})
</script>
