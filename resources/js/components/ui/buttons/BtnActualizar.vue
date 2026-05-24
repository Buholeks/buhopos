<template>
  <BaseButton :variant="variant" :size="size" :icon="RefreshCw" v-bind="$attrs">
    <slot>Actualizar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { RefreshCw } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'secondary' },
  size: { type: String, default: 'md' },
})
</script>
