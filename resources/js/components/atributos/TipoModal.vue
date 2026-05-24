<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-120"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-120 flex items-center justify-center bg-black/55 p-4"
        @mousedown.self="$emit('close')"
        role="dialog"
        aria-modal="true"
      >
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
          <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 ring-1 ring-emerald-100">
                <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M6 6h.01M6 3h4l6 6-6 6H6V3z" />
                </svg>
              </div>
              <div>
                <h2 class="text-base font-semibold tracking-tight">
                  {{ editando ? "Editar tipo" : "Nuevo tipo de atributo" }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">Define cómo se llamará este grupo de valores.</p>
              </div>
            </div>

            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
              @click="$emit('close')"
              aria-label="Cerrar"
            >
              <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-width="1.8" d="M4 4l8 8M12 4l-8 8" />
              </svg>
            </button>
          </div>

          <div class="space-y-4 px-5 py-4">
            <BaseInput
              v-model="nombreProxy"
              label="Nombre"
              required
              :error="errors?.nombre || ''"
              placeholder="Ej. Color, Talla, Material, Temporada…"
              @keyup.enter="$emit('submit')"
            />

            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
              <div>
                <div class="text-sm font-semibold text-slate-700">Estado</div>
                <div class="mt-1 text-sm text-slate-500">{{ activoProxy ? "Activo" : "Inactivo" }}</div>
              </div>

              <button
                type="button"
                class="relative inline-flex h-7 w-12 items-center rounded-full transition"
                :class="activoProxy ? 'bg-emerald-600' : 'bg-slate-300'"
                @click="activoProxy = !activoProxy"
                :aria-pressed="activoProxy"
                aria-label="Cambiar estado"
              >
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                  :class="activoProxy ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
              @click="$emit('close')"
            >
              Cancelar
            </button>

            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="cargando"
              @click="$emit('submit')"
            >
              <svg v-if="cargando" class="h-4 w-4 animate-spin" viewBox="0 0 20 20" fill="none">
                <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="2.5" stroke-dasharray="32" stroke-dashoffset="12"/>
              </svg>
              {{ editando ? "Guardar" : "Crear tipo" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";

const props = defineProps({
  open: { type: Boolean, default: false },
  editando: { type: Boolean, default: false },
  cargando: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  nombre: { type: String, default: "" },
  activo: { type: Boolean, default: true },
});

const emit = defineEmits(["close", "submit", "update:nombre", "update:activo"]);

const nombreProxy = computed({
  get: () => props.nombre,
  set: (v) => emit("update:nombre", v),
});

const activoProxy = computed({
  get: () => props.activo,
  set: (v) => emit("update:activo", v),
});
</script>
