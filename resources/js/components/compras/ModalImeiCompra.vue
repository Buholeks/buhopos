<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="mostrar"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      >
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">

          <!-- Header -->
          <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
              <Smartphone class="h-4 w-4" />
            </div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-slate-900">Captura de IMEI / Serie</p>
              <p class="text-xs text-slate-400">
                {{ item?.nombre }}
                <span v-if="item?.nombre_variante" class="text-violet-600"> — {{ item.nombre_variante }}</span>
              </p>
            </div>
            <div class="text-right">
              <span class="text-lg font-bold" :class="capturados >= cantidad ? 'text-emerald-600' : 'text-blue-600'">
                {{ capturados }}
              </span>
              <span class="text-sm text-slate-400"> / {{ cantidad }}</span>
            </div>
          </div>

          <!-- Barra de progreso -->
          <div class="h-1.5 bg-slate-100">
            <div
              class="h-full transition-all duration-300"
              :class="capturados >= cantidad ? 'bg-emerald-500' : 'bg-blue-500'"
              :style="{ width: `${Math.min((capturados / cantidad) * 100, 100)}%` }"
            />
          </div>

          <!-- Input captura -->
          <div class="px-5 pt-4 pb-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
              {{ capturados < cantidad ? `IMEI / Número de serie #${capturados + 1}` : '✓ Todos capturados' }}
            </label>
            <div class="flex gap-2">
              <input
                ref="inputImei"
                v-model="imeiActual"
                type="text"
                maxlength="20"
                autocomplete="off"
                :placeholder="capturados < cantidad ? 'Escanea o escribe el IMEI…' : 'Todos los IMEIs capturados'"
                :disabled="capturados >= cantidad || verificando"
                @keydown.enter="agregarImei"
                class="flex-1 rounded-xl border px-4 py-3 font-mono text-sm outline-none transition focus:ring-4"
                :class="errorImei
                  ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                  : 'border-slate-200 focus:border-blue-500 focus:ring-blue-100'"
              />
              <button
                @click="agregarImei"
                :disabled="capturados >= cantidad || verificando"
                class="rounded-xl bg-blue-600 px-4 text-white hover:bg-blue-700 disabled:opacity-40"
              >
                <Loader2 v-if="verificando" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
              </button>
            </div>
            <p v-if="errorImei" class="mt-1 text-xs text-red-500">{{ errorImei }}</p>
          </div>

          <!-- Lista IMEIs capturados -->
          <div class="max-h-52 overflow-y-auto px-5 pb-2">
            <div
              v-for="(imei, i) in imeis"
              :key="i"
              class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 mb-1.5"
            >
              <div class="flex items-center gap-2">
                <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                  {{ i + 1 }}
                </div>
                <span class="font-mono text-sm text-slate-800">{{ imei }}</span>
              </div>
              <button
                @click="quitarImei(i)"
                class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-500"
              >
                <X class="h-3.5 w-3.5" />
              </button>
            </div>

            <!-- Slots vacíos -->
            <div
              v-for="i in slotsVacios"
              :key="`vacio-${i}`"
              class="flex items-center gap-2 rounded-lg border border-dashed border-slate-200 px-3 py-2 mb-1.5"
            >
              <div class="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-slate-300 text-xs font-bold">
                {{ imeis.length + i }}
              </div>
              <span class="text-xs text-slate-300">Pendiente…</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
            <button
              @click="emit('atras')"
              class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
            >
              <ArrowLeft class="h-3.5 w-3.5" />
              Atrás
            </button>
            <button
              @click="confirmar"
              :disabled="capturados < cantidad"
              class="flex-1 rounded-xl py-2.5 text-sm font-medium text-white transition"
              :class="capturados >= cantidad ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-300 cursor-not-allowed'"
            >
              Confirmar {{ capturados }} IMEI{{ capturados !== 1 ? 's' : '' }}
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import axios from 'axios'
import { Smartphone, Plus, X, ArrowLeft, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  mostrar:       { type: Boolean, default: false },
  item:          { type: Object,  default: null  },
  cantidad:      { type: Number,  default: 1     },
  imeisExcluir:  { type: Array,   default: () => [] }, // IMEIs ya capturados en este carrito
})

const emit = defineEmits(['confirmar', 'atras'])

const inputImei  = ref(null)
const imeiActual = ref('')
const imeis      = ref([])
const errorImei  = ref('')
const verificando = ref(false)

const capturados  = computed(() => imeis.value.length)
const slotsVacios = computed(() => Math.max(0, props.cantidad - imeis.value.length))

watch(() => props.mostrar, (val) => {
  if (val) {
    imeis.value      = []
    imeiActual.value = ''
    errorImei.value  = ''
    nextTick(() => inputImei.value?.focus())
  }
})

async function agregarImei() {
  const val = imeiActual.value.trim()
  errorImei.value = ''

  if (!val) {
    errorImei.value = 'Ingresa un IMEI o número de serie.'
    return
  }
  if (imeis.value.includes(val)) {
    errorImei.value = 'Este IMEI ya fue capturado en esta compra.'
    return
  }
  // Verificar también contra otros detalles del carrito actual
  if (props.imeisExcluir.includes(val)) {
    errorImei.value = 'Este IMEI ya está en otro artículo de esta compra.'
    return
  }
  if (imeis.value.length >= props.cantidad) {
    errorImei.value = `Ya capturaste los ${props.cantidad} IMEI requeridos.`
    return
  }

  // ── Verificar contra la BD ─────────────────────────────────────────────
  verificando.value = true
  try {
    const { data } = await axios.get('/api/series/verificar-imei', { params: { imei: val } })
    if (!data.disponible) {
      errorImei.value = data.mensaje ?? 'Este IMEI ya existe en inventario.'
      return
    }
  } catch {
    errorImei.value = 'Error al verificar el IMEI. Intenta de nuevo.'
    return
  } finally {
    verificando.value = false
  }

  imeis.value.push(val)
  imeiActual.value = ''
  nextTick(() => inputImei.value?.focus())
}

function quitarImei(i) {
  imeis.value.splice(i, 1)
  nextTick(() => inputImei.value?.focus())
}

function confirmar() {
  if (imeis.value.length < props.cantidad) return
  emit('confirmar', [...imeis.value])
}
</script>