<template>
  <div ref="root" class="relative">
    <!-- Label -->
    <label v-if="label" class="mb-1 block text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Control -->
    <div
      class="flex items-center gap-2 rounded-lg border bg-white px-3 transition"
      :class="[
        disabled ? 'opacity-60 pointer-events-none' : '',
        error ? 'border-red-300 ring-4 ring-red-100' :
        open ? 'border-emerald-500 ring-4 ring-emerald-100' :
               'border-slate-200 hover:border-slate-300'
      ]"
      @click="focusInput"
    >
      <!-- Search icon -->
      <Search class="h-4 w-4 shrink-0 text-slate-400" />

      <!-- Input -->
      <input
        ref="input"
        v-model="query"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        class="flex-1 py-2.5 text-sm outline-none placeholder:text-slate-400"
        @focus="abrir()"
        @input="onInput"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="selectActive"
        @keydown.escape.prevent="cerrar"
        @keydown.tab="cerrar"
      />

      <!-- Loading -->
      <Loader2
        v-if="loading"
        class="h-4 w-4 shrink-0 animate-spin text-emerald-500"
      />

      <!-- Clear -->
      <button
        v-if="!loading && (query || selectedLabel)"
        type="button"
        class="text-slate-400 hover:text-slate-700"
        @click.stop="clear"
        title="Limpiar"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <!-- Hint / Error -->
    <p v-if="hint && !error" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      leave-active-class="transition duration-75"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="absolute left-0 right-0 z-50 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
      >
        <div class="max-h-72 overflow-y-auto">

          <!-- Empty -->
          <div v-if="!loading && shownItems.length === 0" class="px-4 py-6 text-center">
            <p class="text-sm text-slate-500">
              Sin resultados<span v-if="query"> para <strong>{{ query }}</strong></span>
            </p>
          </div>

          <!-- Items -->
          <button
            v-for="(it, i) in shownItems"
            :key="itemKey(it, i)"
            type="button"
            class="flex w-full items-center gap-3 px-4 py-3 text-left transition"
            :class="i === active ? 'bg-emerald-50' : 'hover:bg-slate-50'"
            @mouseenter="active = i"
            @click="select(it)"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-900">
                {{ getLabel(it) }}
              </p>
              <p v-if="getSubLabel(it)" class="mt-0.5 truncate text-xs text-slate-400">
                {{ getSubLabel(it) }}
              </p>
            </div>

            <div
              v-if="i === active"
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-emerald-700"
            >
              <CornerDownLeft class="h-3.5 w-3.5" />
            </div>
          </button>
        </div>

        <div class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-400">
          <span class="mr-3">↑↓ Navegar</span>
          <span class="mr-3">↵ Seleccionar</span>
          <span>Esc Cerrar</span>
        </div>
      </div>
    </Transition>
  </div>
</template>


<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  Search,
  Loader2,
  X,
  CornerDownLeft
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },

  // Lista local (opcional si usas fetch)
  items: { type: Array, default: () => [] },

  // Mapeos
  labelKey: { type: [String, Function], default: 'nombre' },
  subLabelKey: { type: [String, Function], default: '' },
  valueKey: { type: [String, Function], default: 'id' },

  // UI
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'Buscar…' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },

  // Search behavior
  minChars: { type: Number, default: 0 },
  limit: { type: Number, default: 30 },

  // Remote fetch (opcional): async (query) => items[]
  fetcher: { type: Function, default: null },
  debounceMs: { type: Number, default: 200 },
})

const emit = defineEmits(['update:modelValue', 'change', 'selected'])

const root = ref(null)
const input = ref(null)

const open = ref(false)
const loading = ref(false)
const query = ref('')
const active = ref(0)
const remoteItems = ref([])
let t = null

function getLabel(it) {
  return typeof props.labelKey === 'function' ? props.labelKey(it) : (it?.[props.labelKey] ?? '')
}
function getSubLabel(it) {
  if (!props.subLabelKey) return ''
  return typeof props.subLabelKey === 'function' ? props.subLabelKey(it) : (it?.[props.subLabelKey] ?? '')
}
function getValue(it) {
  return typeof props.valueKey === 'function' ? props.valueKey(it) : (it?.[props.valueKey] ?? null)
}
function itemKey(it, i) {
  const v = getValue(it)
  return v ?? i
}

const localFiltered = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = props.items ?? []
  if (!q) return list.slice(0, props.limit)

  return list
    .filter(it => {
      const a = (getLabel(it) || '').toLowerCase()
      const b = (getSubLabel(it) || '').toLowerCase()
      return a.includes(q) || b.includes(q)
    })
    .slice(0, props.limit)
})

const shownItems = computed(() => {
  return props.fetcher ? (remoteItems.value ?? []) : localFiltered.value
})

const selectedLabel = computed(() => {
  if (props.modelValue == null) return ''
  const list = props.fetcher ? remoteItems.value : props.items
  const found = (list || []).find(it => String(getValue(it)) === String(props.modelValue))
  return found ? getLabel(found) : ''
})

function abrir() {
  if (props.disabled) return
  open.value = true
  active.value = 0

  // Si ya hay texto, dispara búsqueda para remote
  if (props.fetcher) triggerRemote()
}

function cerrar() {
  open.value = false
  active.value = 0
}

function focusInput() {
  if (props.disabled) return
  nextTick(() => input.value?.focus())
}

function clear() {
  query.value = ''
  remoteItems.value = []
  emit('update:modelValue', null)
  emit('change', null)
  emit('selected', null)
  nextTick(() => input.value?.focus())
}

function move(dir) {
  if (!open.value) abrir()
  const n = shownItems.value.length
  if (n === 0) return
  active.value = Math.max(0, Math.min(n - 1, active.value + dir))
}

function selectActive() {
  const it = shownItems.value[active.value]
  if (it) select(it)
}

function select(it) {
  const v = getValue(it)
  emit('update:modelValue', v)
  emit('change', v)
  emit('selected', it)

  // Mostrar label seleccionado en input (opcional/UX)
  query.value = getLabel(it)

  cerrar()
  nextTick(() => input.value?.blur())
}

function onInput() {
  if (!open.value) open.value = true
  active.value = 0
  if (props.fetcher) triggerRemote()
}

async function triggerRemote() {
  clearTimeout(t)
  const q = query.value.trim()

  if (q.length < props.minChars) {
    remoteItems.value = []
    loading.value = false
    return
  }

  loading.value = true
  t = setTimeout(async () => {
    try {
      const res = await props.fetcher(q)
      remoteItems.value = Array.isArray(res) ? res.slice(0, props.limit) : []
      active.value = 0
    } finally {
      loading.value = false
    }
  }, props.debounceMs)
}

// Click afuera
function onDocClick(e) {
  if (!open.value) return
  if (!root.value) return
  if (!root.value.contains(e.target)) cerrar()
}

function syncQueryFromSelection() {
  if (props.modelValue == null || props.modelValue === '') {
    if (!open.value) query.value = ''
    return
  }

  if (!selectedLabel.value) return

  // Evita reemplazar lo que el usuario esta escribiendo mientras busca.
  if (open.value && query.value.trim() !== '' && query.value !== selectedLabel.value) return

  query.value = selectedLabel.value
}

onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))

// Si cambia el modelValue desde fuera, no lo pisamos siempre,
// pero si está vacío el query, intentamos reflejarlo.
watch(
  () => props.modelValue,
  () => syncQueryFromSelection(),
  { immediate: true }
)

watch(
  selectedLabel,
  () => syncQueryFromSelection(),
  { immediate: true }
)
</script>
