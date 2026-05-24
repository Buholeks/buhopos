<template>
  <BaseButton :variant="variant" :size="size" :icon="Save" v-bind="$attrs">
    <slot>Guardar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { Save } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
})
</script>
