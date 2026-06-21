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
      >
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
          <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold tracking-tight">
                {{ editando ? "Editar modelo" : "Nuevo modelo" }}
              </h2>
              <p class="mt-1 text-sm text-slate-500">
                Marca: <span class="font-semibold text-indigo-700">{{ nombreMarca }}</span>
              </p>
            </div>

            <button
              type="button"
              @click="$emit('close')"
              class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <div class="space-y-4 px-5 py-4">
            <!-- Nombre -->
            <BaseInput
              label="Nombre"
              required
              placeholder="Ej. Corolla"
              :modelValue="form.nombre"
              @update:modelValue="v => (form.nombre = v)"
              :error="errores.nombre"
              @keyup.enter="$emit('submit')"
            />

            <!-- Imagen -->
            <div>
              <label class="text-sm font-semibold text-slate-700">Imagen</label>
              <div class="mt-2">
                <MediaPicker
                  :model-value="form.imagenMedia"
                  carpeta-tipo="modelo"
                  @update:model-value="$emit('pick-imagen', $event)"
                  @clear="$emit('clear-imagen')"
                />
              </div>
            </div>

            <!-- Activo -->
            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
              <div>
                <div class="text-sm font-semibold text-slate-700">Estado</div>
                <div class="mt-1 text-sm text-slate-500">{{ form.activo ? "Activo" : "Inactivo" }}</div>
              </div>

              <button
                type="button"
                @click="form.activo = !form.activo"
                class="relative inline-flex h-7 w-12 items-center rounded-full transition"
                :class="form.activo ? 'bg-indigo-600' : 'bg-slate-300'"
              >
                <span
                  class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                  :class="form.activo ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button
              type="button"
              @click="$emit('close')"
              class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
              Cancelar
            </button>

            <button
              type="button"
              @click="$emit('submit')"
              :disabled="loading"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
            >
              <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
              {{ editando ? "Guardar cambios" : "Crear modelo" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import BaseInput from "@/components/ui/BaseInput.vue";
import MediaPicker from "@/components/media/MediaPicker.vue";
import { X, Loader2 } from "lucide-vue-next";

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  editando: { type: Boolean, default: false },
  nombreMarca: { type: String, default: "" },
  form: { type: Object, required: true },
  errores: { type: Object, required: true },
});

defineEmits([
  "close",
  "submit",
  "pick-imagen",
  "clear-imagen",
]);
</script>