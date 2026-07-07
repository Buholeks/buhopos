<template>
  <div class="fixed inset-0 z-/[500] flex items-center justify-center bg-slate-900/35 backdrop-blur-sm" @click.self="$emit('cancel')">
    <div class="w-/[360px] rounded-2xl bg-white p-6 shadow-[0_24px_64px_rgba(0,0,0,0.15)]" @click.stop>
      <div class="mb-4 flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl border border-orange-200 bg-orange-50 text-orange-500">
          <AlertTriangle class="h-4 w-4" />
        </div>
        <div>
          <div class="text-[14px] font-semibold text-slate-900">Precio fuera de lista</div>
          <div class="text-[12px] text-slate-400">
            Nuevo precio:
            <strong class="font-mono text-orange-500">{{ formatPrecio(precioNuevo) }}</strong>
          </div>
        </div>
      </div>

      <label class="mb-1.5 block text-xs font-medium text-slate-600">
        Motivo <span class="text-red-500">*</span>
      </label>

      <textarea
        :value="motivo"
        rows="3"
        placeholder="Ej: Precio especial, promoción, etc."
        class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-[13px] text-slate-700 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-100"
        @input="$emit('update:motivo', $event.target.value)"
        @keydown.enter.ctrl.prevent="confirmarSiTieneMotivo"
      />

      <p class="mt-2 text-[11px] text-slate-400">Ctrl+Enter para confirmar</p>

      <div class="mt-4 flex justify-end gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[13px] font-medium text-slate-500 transition hover:bg-slate-50"
          @click="$emit('cancel')"
        >
          Cancelar
        </button>

        <button
          type="button"
          class="rounded-lg px-4 py-2 text-[13px] font-semibold text-white transition"
          :class="motivoTrim ? 'bg-orange-500 hover:brightness-105' : 'cursor-not-allowed bg-orange-200'"
          :disabled="!motivoTrim"
          @click="$emit('confirm')"
        >
          Aplicar precio
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { AlertTriangle } from "lucide-vue-next";

const props = defineProps({
  precioNuevo: { type: Number, default: 0 },
  motivo: { type: String, default: "" },
  formatPrecio: { type: Function, required: true },
});

const emit = defineEmits(["update:motivo", "cancel", "confirm"]);

const motivoTrim = computed(() => props.motivo.trim().length > 0);

function confirmarSiTieneMotivo() {
  if (!motivoTrim.value) return;
  emit("confirm");
}
</script>
