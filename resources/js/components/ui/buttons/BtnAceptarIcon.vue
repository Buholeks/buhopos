<template>
  <BaseIconButton
    :icon="Check"
    :variant="variant"
    :size="size"
    :tooltip="tooltip"
    v-bind="$attrs"
  />
</template>

<script setup>
import BaseIconButton from './BaseIconButton.vue'
import { Check } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  tooltip: { type: String, default: 'Aceptar' },
})
</script>
