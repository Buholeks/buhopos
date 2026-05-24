<template>
  <BaseButton :variant="variant" :size="size" :icon="Search" v-bind="$attrs">
    <slot>Buscar</slot>
  </BaseButton>
</template>

<script setup>
import BaseButton from './BaseButton.vue'
import { Search } from 'lucide-vue-next'

defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
})
</script>
