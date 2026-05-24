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
        @mousedown.self="cerrar"
      >
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
          <!-- Header -->
          <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
              <Pencil class="h-4 w-4" />
            </div>

            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-slate-900">Editar IMEIs del artículo</p>
              <p class="truncate text-xs text-slate-400">{{ detalle?.nombre }}</p>
            </div>

            <button
              type="button"
              @click="cerrar"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"
              aria-label="Cerrar"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Cantidad -->
          <div class="border-b border-slate-100 px-5 py-3">
            <div class="flex items-center justify-between gap-4">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800">Cantidad de unidades</p>
                <p class="text-xs text-slate-400">Debe coincidir con la cantidad de IMEIs.</p>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="decrementarCantidad"
                  :disabled="cantidadLocal <= 1"
                  class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50
                         disabled:cursor-not-allowed disabled:opacity-40"
                >
                  <Minus class="h-4 w-4" />
                </button>

                <div class="min-w-[56px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-center">
                  <span class="font-mono text-sm font-extrabold text-slate-900 tabular-nums">
                    {{ cantidadLocal }}
                  </span>
                </div>

                <button
                  type="button"
                  @click="incrementarCantidad"
                  class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50"
                >
                  <Plus class="h-4 w-4" />
                </button>
              </div>
            </div>

            <!-- Aviso de desajuste -->
            <div
              v-if="imeis.length !== cantidadLocal"
              class="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2"
            >
              <AlertTriangle class="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-600" />
              <p class="text-xs text-amber-800">
                Tienes <b>{{ imeis.length }}</b> IMEI{{ imeis.length !== 1 ? "s" : "" }}
                pero la cantidad es <b>{{ cantidadLocal }}</b>.
                <span class="font-semibold">
                  {{ imeis.length < cantidadLocal ? "Agrega los faltantes." : "Elimina los sobrantes." }}
                </span>
              </p>
            </div>
          </div>

          <!-- Input nuevo IMEI -->
          <div v-if="imeis.length < cantidadLocal" class="px-5 pt-4 pb-2">
            <div class="flex items-end gap-2" @keydown.enter.prevent="agregarImei">
              <div class="flex-1">
                <BaseInput
                  ref="baseImei"
                  v-model="imeiActual"
                  label="Agregar IMEI"
                  type="text"
                  placeholder="Escanea o escribe el IMEI…"
                  :disabled="verificando"
                  :rootClass="'w-full'"
                  inputClass="font-mono"
                  :error="errorImei"
                >
                  <template #icon>
                    <ScanBarcode class="h-4 w-4" />
                  </template>
                </BaseInput>

                <p class="-mt-1 text-[11px] text-slate-400">
                  IMEI #{{ imeis.length + 1 }} de {{ cantidadLocal }}
                </p>
              </div>

              <button
                type="button"
                @click="agregarImei"
                :disabled="verificando"
                class="flex h-[42px] items-center justify-center rounded-xl bg-emerald-600 px-4 text-white hover:bg-emerald-700
                       disabled:cursor-not-allowed disabled:opacity-40"
                title="Agregar"
              >
                <Loader2 v-if="verificando" class="h-4 w-4 animate-spin" />
                <Plus v-else class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Lista IMEIs -->
          <div class="max-h-60 overflow-y-auto px-5 py-3">
            <div v-if="imeis.length === 0" class="py-4 text-center text-xs text-slate-400">
              Sin IMEIs capturados
            </div>

            <div
              v-for="(imei, i) in imeis"
              :key="i"
              class="mb-2 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2"
            >
              <div class="flex items-center gap-2">
                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-extrabold">
                  {{ i + 1 }}
                </div>
                <span class="font-mono text-sm text-slate-900">{{ imei }}</span>
              </div>

              <button
                type="button"
                @click="quitarImei(i)"
                class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                title="Eliminar"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>

            <div v-if="imeis.length > 0" class="mt-2 text-[11px] text-slate-400">
              Consejo: si escaneas rápido, Enter también agrega.
            </div>
          </div>

          <!-- Footer -->
          <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
            <button
              type="button"
              @click="cerrar"
              class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              Cancelar
            </button>

            <button
              type="button"
              @click="guardar"
              :disabled="imeis.length !== cantidadLocal"
              class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700
                     disabled:cursor-not-allowed disabled:opacity-40"
            >
              Guardar cambios
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";
import http from "@/lib/http";
import {
  Pencil,
  X,
  Plus,
  Minus,
  Trash2,
  AlertTriangle,
  Loader2,
  ScanBarcode,
} from "lucide-vue-next";
import BaseInput from "@/components/ui/BaseInput.vue";

const props = defineProps({
  mostrar: { type: Boolean, default: false },
  detalle: { type: Object, default: null },
  imeisExcluir: { type: Array, default: () => [] },
});

const emit = defineEmits(["guardar", "cerrar"]);

const baseImei = ref(null);
const imeiActual = ref("");
const imeis = ref([]);
const cantidadLocal = ref(1);
const errorImei = ref("");
const verificando = ref(false);

// Poblar al abrir
watch(
  () => props.mostrar,
  (val) => {
    if (val && props.detalle) {
      imeis.value = [...(props.detalle.imeis ?? [])];
      cantidadLocal.value = props.detalle.cantidad ?? 1;
      imeiActual.value = "";
      errorImei.value = "";
      nextTick(() => focusImei());
    }
  }
);

function focusImei() {
  // tu BaseInput expone input interno como ref="inputEl" (privado),
  // así que enfocamos con un query ligero al último input del modal:
  const modal = document.querySelector(".fixed.inset-0.z-50");
  modal?.querySelector('input[type="text"]')?.focus();
}

function incrementarCantidad() {
  cantidadLocal.value++;
  nextTick(() => focusImei());
}

function decrementarCantidad() {
  if (cantidadLocal.value <= 1) return;
  cantidadLocal.value--;

  if (imeis.value.length > cantidadLocal.value) {
    imeis.value = imeis.value.slice(0, cantidadLocal.value);
  }

  nextTick(() => focusImei());
}

async function agregarImei() {
  const val = imeiActual.value.trim();
  errorImei.value = "";

  if (!val) {
    errorImei.value = "Ingresa un IMEI o número de serie.";
    return;
  }
  if (imeis.value.includes(val)) {
    errorImei.value = "Este IMEI ya está en la lista.";
    return;
  }
  if (props.imeisExcluir.includes(val)) {
    errorImei.value = "Este IMEI ya está en otro artículo de esta compra.";
    return;
  }
  if (imeis.value.length >= cantidadLocal.value) {
    errorImei.value = "Ya alcanzaste la cantidad requerida.";
    return;
  }

  const imeiOriginal = props.detalle?.imeis?.includes(val);
  if (!imeiOriginal) {
    verificando.value = true;
    try {
      await http.get("/api/series/verificar-imei", { params: { imei: val } });
      if (!data.disponible) {
        errorImei.value = data.mensaje ?? "Este IMEI ya existe en inventario.";
        return;
      }
    } catch {
      errorImei.value = "Error al verificar el IMEI.";
      return;
    } finally {
      verificando.value = false;
    }
  }

  imeis.value.push(val);
  imeiActual.value = "";
  nextTick(() => focusImei());
}

function quitarImei(i) {
  imeis.value.splice(i, 1);
  nextTick(() => focusImei());
}

function guardar() {
  if (imeis.value.length !== cantidadLocal.value) return;
  emit("guardar", {
    imeis: [...imeis.value],
    cantidad: cantidadLocal.value,
  });
}

function cerrar() {
  emit("cerrar");
}
</script>