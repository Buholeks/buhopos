<template>
  <div class="relative inline-flex group">
    <button
      v-bind="attrs"
      :type="type"
      :disabled="isDisabled"
      class="inline-flex items-center justify-center rounded-lg transition-all duration-150
             focus:outline-none focus:ring-2 focus:ring-offset-1
             active:scale-95 disabled:active:scale-100"
      :class="[
        sizeClass,
        variantClass,
        microClass,
        isDisabled ? 'opacity-60 cursor-not-allowed' : ''
      ]"
      @click="handleClick"
      @mouseenter="onEnter"
      @mouseleave="onLeave"
      @focus="onEnter"
      @blur="onLeave"
    >
      <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
        <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>

      <component v-else :is="icon" class="h-4 w-4" />
    </button>

    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-120 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div
        v-if="showTooltip && tooltip"
        class="pointer-events-none absolute z-50 whitespace-nowrap rounded-md
               bg-slate-900 text-white text-xs px-2 py-1 shadow-lg"
        :class="tooltipPositionClass"
      >
        {{ tooltip }}
        <span class="absolute h-2 w-2 rotate-45 bg-slate-900" :class="tooltipArrowClass" />
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, ref, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  type: { type: String, default: 'button' },
  variant: { type: String, default: 'ghost' }, // ghost | outline | primary | danger | success | warning | subtle
  size: { type: String, default: 'md' },       // xs | sm | md | lg
  icon: { type: [Object, Function], required: true },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },

  preventSpam: { type: Boolean, default: true },
  cooldownMs: { type: Number, default: 500 },

  tooltip: { type: String, default: '' },
  tooltipPosition: { type: String, default: 'top' }
})

const emit = defineEmits(['click'])
const attrs = useAttrs()
const locked = ref(false)

const isDisabled = computed(() => props.disabled || props.loading || (props.preventSpam && locked.value))

const handleClick = (e) => {
  if (isDisabled.value) return
  if (props.preventSpam) {
    locked.value = true
    window.setTimeout(() => (locked.value = false), props.cooldownMs)
  }
  emit('click', e)
}

const microClass = computed(() => (isDisabled.value ? '' : 'hover:-translate-y-[1px] hover:shadow-sm'))

const sizeClass = computed(() => ({
  xs: 'h-7 w-7',
  sm: 'h-8 w-8',
  md: 'h-9 w-9',
  lg: 'h-10 w-10'
}[props.size] ?? 'h-9 w-9'))

const variantClass = computed(() => ({
  ghost:   'text-slate-600 hover:bg-slate-100 focus:ring-slate-300',
  outline: 'border border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-300',
  primary: 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
  success: 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
  danger:  'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
  warning: 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-400',
  subtle:  'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-white focus:ring-slate-300'
}[props.variant] ?? 'text-slate-600 hover:bg-slate-100 focus:ring-slate-300'))

const showTooltip = ref(false)
const onEnter = () => { if (!isDisabled.value) showTooltip.value = true }
const onLeave = () => { showTooltip.value = false }

const tooltipPositionClass = computed(() => ({
  top:    'bottom-full left-1/2 -translate-x-1/2 mb-2',
  bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
  left:   'right-full top-1/2 -translate-y-1/2 mr-2',
  right:  'left-full top-1/2 -translate-y-1/2 ml-2'
}[props.tooltipPosition] ?? 'bottom-full left-1/2 -translate-x-1/2 mb-2'))

const tooltipArrowClass = computed(() => ({
  top:    'left-1/2 -translate-x-1/2 -bottom-1',
  bottom: 'left-1/2 -translate-x-1/2 -top-1',
  left:   'top-1/2 -translate-y-1/2 -right-1',
  right:  'top-1/2 -translate-y-1/2 -left-1'
}[props.tooltipPosition] ?? 'left-1/2 -translate-x-1/2 -bottom-1'))
</script>
