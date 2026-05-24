<template>
  <BaseButton :variant="variant" :size="size" :icon="ArrowLeft" v-bind="$attrs">
    <slot>Volver</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { ArrowLeft } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'outline' },
  size: { type: String, default: 'md' },
})
</script>
