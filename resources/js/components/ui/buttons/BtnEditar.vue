<template>
  <BaseButton :variant="variant" :size="size" :icon="Pencil" v-bind="$attrs">
    <slot>Editar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { Pencil } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'outline' },
  size: { type: String, default: 'md' },
})
</script>
