<template>
  <BaseButton :variant="variant" :size="size" :icon="PlusCircle" v-bind="$attrs">
    <slot>Agregar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { PlusCircle } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
})
</script>
