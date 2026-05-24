<template>
  <BaseButton :variant="variant" :size="size" v-bind="$attrs">
    <slot>Cancelar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'

defineProps({
  variant: { type: String, default: 'outline' },
  size: { type: String, default: 'md' },
})
</script>
