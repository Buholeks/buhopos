<template>
  <BaseButton :variant="variant" :size="size" :icon="Eraser" v-bind="$attrs">
    <slot>Limpiar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { Eraser } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'secondary' },
  size: { type: String, default: 'md' },
})
</script>
