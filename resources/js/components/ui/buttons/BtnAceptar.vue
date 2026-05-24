<template>
  <BaseButton :variant="variant" :size="size" :icon="Check" v-bind="$attrs">
    <slot>Aceptar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { Check } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
})
</script>
