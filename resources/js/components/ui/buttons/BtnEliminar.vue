<template>
  <BaseButton :variant="variant" :size="size" :icon="Trash2" v-bind="$attrs">
    <slot>Eliminar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { Trash2 } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'danger' },
  size: { type: String, default: 'md' },
})
</script>
