<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    @click.self="$emit('close')"
  >
    <div
      class="my-8 w-full max-w-6xl max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl"
    >
      <!-- TOP BAR -->
      <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="flex items-start justify-between gap-4 px-6 py-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Cerrar caja</h3>
            <p class="mt-1 text-xs text-slate-500">
              Arqueo de efectivo (en vivo) o conteo manual y confirmación de cierre.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <!-- RESUMEN (DESKTOP) -->
            <div class="hidden sm:flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
              <div class="text-right">
                <p class="text-[11px] font-medium text-slate-500">Total contado</p>
                <p class="mt-0.5 font-mono text-base font-bold text-slate-900">
                  {{ fmt(totalContado) }}
                </p>

                <p class="mt-0.5 text-[11px] text-slate-500">
                  Esperado:
                  <span class="font-mono font-semibold text-slate-700">
                    {{ fmt(corte?.esperado_efectivo) }}
                  </span>
                </p>

                <p
                  class="mt-0.5 text-[11px] font-semibold"
                  :class="difLocal >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                >
                  {{ difLocal >= 0 ? "Sobrante" : "Faltante" }}
                  {{ fmt(Math.abs(difLocal)) }}
                </p>
              </div>

              <div class="pl-1">
                <span
                  v-if="saving"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600"
                >
                  <Loader2 class="h-3.5 w-3.5 animate-spin" />
                  Guardando…
                </span>

                <span
                  v-else-if="lastSavedAt && modo === 'arqueo'"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-700"
                >
                  <Check class="h-3.5 w-3.5" />
                  Guardado
                </span>

                <span
                  v-else-if="modo === 'manual'"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600"
                >
                  <Hand class="h-3.5 w-3.5" />
                  Manual
                </span>
              </div>
            </div>

            <button
              type="button"
              class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              @click="$emit('close')"
              title="Cerrar"
            >
              <X class="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="space-y-6 px-6 py-6">
        <!-- TOGGLE MODO -->
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="modo = 'arqueo'"
            :class="modo === 'arqueo'
              ? 'bg-cyan-600 text-white ring-cyan-200'
              : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'"
            class="rounded-xl px-3 py-2 text-xs font-semibold ring-1"
          >
            Arqueo
          </button>

          <button
            type="button"
            @click="modo = 'manual'"
            :class="modo === 'manual'
              ? 'bg-cyan-600 text-white ring-cyan-200'
              : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'"
            class="rounded-xl px-3 py-2 text-xs font-semibold ring-1"
          >
            Manual
          </button>

          <span class="ml-2 text-xs text-slate-500">
            {{ modo === 'arqueo' ? 'Se guarda por denominación.' : 'Ingresa el total directamente.' }}
          </span>
        </div>

        <!-- BLOQUE EFECTIVO -->
        <div class="rounded-2xl border border-slate-200 bg-white">
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
              <h4 class="text-sm font-semibold text-slate-900">Efectivo</h4>
              <p class="mt-1 text-xs text-slate-500">
                {{ modo === 'arqueo'
                  ? 'Conteo por denominación (billetes/monedas).'
                  : 'Captura manual del efectivo contado.' }}
              </p>
            </div>

            <div class="flex items-center gap-2">
              <button
                v-if="modo === 'arqueo'"
                type="button"
                @click="resetArqueo"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                title="Poner todo en 0"
              >
                <RotateCcw class="h-4 w-4" />
                Reset
              </button>
            </div>
          </div>

          <!-- MODO MANUAL -->
          <div v-if="modo === 'manual'" class="px-5 py-5">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
              <div class="lg:col-span-1 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Efectivo contado (manual)</p>

                <div class="mt-3 flex overflow-hidden rounded-xl border border-slate-200 bg-white focus-within:border-cyan-500 focus-within:ring-4 focus-within:ring-cyan-100">
                  <span class="border-r border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">$</span>
                  <input
                    v-model.number="contadoManual"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 font-mono text-sm outline-none"
                    placeholder="0.00"
                  />
                </div>

                <p class="mt-3 text-xs text-slate-500">
                  Esperado:
                  <span class="font-mono font-semibold text-slate-700">{{ fmt(corte?.esperado_efectivo) }}</span>
                </p>

                <p class="mt-1 text-xs font-semibold" :class="difLocal >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                  {{ difLocal >= 0 ? "Sobrante" : "Faltante" }} {{ fmt(Math.abs(difLocal)) }}
                </p>
              </div>
            </div>
          </div>

          <!-- MODO ARQUEO -->
          <div v-else class="px-5 py-5 space-y-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
              <div
                v-for="d in denoms"
                :key="d.key"
                class="group rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300 hover:bg-slate-50/40"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ d.label }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                      Valor:
                      <span class="font-mono font-semibold text-slate-700">{{ fmt(d.value) }}</span>
                    </p>
                  </div>

                  <div class="text-right">
                    <p class="text-[10px] text-slate-400">Subtotal</p>
                    <p class="mt-0.5 font-mono text-sm font-bold text-slate-900">
                      {{ fmt(Number(form[d.key] || 0) * d.value) }}
                    </p>
                  </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                  <button
                    type="button"
                    @click="setVal(d.key, Math.max(0, form[d.key] - 1))"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-[0.98]"
                    aria-label="Restar 1"
                  >
                    <Minus class="h-4 w-4" />
                  </button>

                  <input
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    :value="form[d.key]"
                    @focus="focusedKey = d.key"
                    @input="setVal(d.key, $event.target.value)"
                    class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2 text-center font-mono text-sm font-semibold text-slate-900 outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                  />

                  <button
                    type="button"
                    @click="setVal(d.key, form[d.key] + 1)"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-[0.98]"
                    aria-label="Sumar 1"
                  >
                    <Plus class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>

            <p
              v-if="errorArqueo"
              class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-medium text-rose-700"
            >
              {{ errorArqueo }}
            </p>
          </div>
        </div>

        <!-- OTRAS FORMAS -->
        <div class="rounded-2xl border border-slate-200 bg-white">
          <div class="border-b border-slate-100 px-5 py-4">
            <h4 class="text-sm font-semibold text-slate-900">Otras formas (opcional)</h4>
            <p class="mt-1 text-xs text-slate-500">
              Si tu negocio registra reportes de terminal/banco, llena estos campos.
            </p>
          </div>

          <div class="px-5 py-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tarjeta reportada</label>
                <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-cyan-500 focus-within:ring-4 focus-within:ring-cyan-100">
                  <span class="border-r border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">$</span>
                  <input
                    v-model.number="formCierre.contado_tarjeta"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 font-mono text-sm outline-none"
                    placeholder="0.00"
                  />
                </div>
                <p class="mt-2 text-xs text-slate-500">
                  Esperado:
                  <span class="font-mono font-semibold text-slate-700">{{ fmt(corte?.esperado_tarjeta) }}</span>
                </p>
              </div>

              <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Transferencia reportada</label>
                <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-cyan-500 focus-within:ring-4 focus-within:ring-cyan-100">
                  <span class="border-r border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">$</span>
                  <input
                    v-model.number="formCierre.contado_transferencia"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 font-mono text-sm outline-none"
                    placeholder="0.00"
                  />
                </div>
                <p class="mt-2 text-xs text-slate-500">
                  Esperado:
                  <span class="font-mono font-semibold text-slate-700">{{ fmt(corte?.esperado_transferencia) }}</span>
                </p>
              </div>
            </div>

            <div class="mt-4">
              <label class="text-sm font-medium text-slate-700">
                Notas <span class="text-xs text-slate-400">(opcional)</span>
              </label>
              <textarea
                v-model="formCierre.notas_cierre"
                rows="2"
                class="mt-2 w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                placeholder="Observaciones…"
              />
            </div>

            <div class="mt-6 flex justify-end gap-2">
              <button
                type="button"
                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                @click="$emit('close')"
              >
                Cancelar
              </button>

              <button
                type="button"
                :disabled="loading || (modo === 'manual' && !manualValido)"
                class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                @click="submit"
              >
                {{ loading ? "Cerrando..." : "Cerrar caja" }}
              </button>
            </div>

            <p v-if="modo === 'manual' && !manualValido" class="mt-3 text-xs font-medium text-rose-700">
              En modo manual debes capturar el efectivo contado.
            </p>
          </div>
        </div>
      </div>
      <!-- /CONTENT -->
    </div>
  </div>
</template>

<script setup>
import { reactive, computed, watch, ref, onBeforeUnmount } from "vue";
import { X, Loader2, Check, RotateCcw, Minus, Plus, Hand } from "lucide-vue-next";

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  corte: { type: Object, default: null },
});

const emit = defineEmits(["close", "submit", "update-corte"]);

const modo = ref("arqueo"); // 'arqueo' | 'manual'
const contadoManual = ref(0);

const denoms = [
  { key: "billetes_1000", label: "Billete $1000", value: 1000 },
  { key: "billetes_500",  label: "Billete $500",  value: 500  },
  { key: "billetes_200",  label: "Billete $200",  value: 200  },
  { key: "billetes_100",  label: "Billete $100",  value: 100  },
  { key: "billetes_50",   label: "Billete $50",   value: 50   },
  { key: "billetes_20",   label: "Billete $20",   value: 20   },
  { key: "monedas_20",    label: "Moneda $20",    value: 20   },
  { key: "monedas_10",    label: "Moneda $10",    value: 10   },
  { key: "monedas_5",     label: "Moneda $5",     value: 5    },
  { key: "monedas_2",     label: "Moneda $2",     value: 2    },
  { key: "monedas_1",     label: "Moneda $1",     value: 1    },
  { key: "monedas_050",   label: "Moneda $0.50",  value: 0.5  },
];

const form = reactive(Object.fromEntries(denoms.map((d) => [d.key, 0])));

const formCierre = reactive({
  contado_tarjeta: 0,
  contado_transferencia: 0,
  notas_cierre: "",
});

const saving = ref(false);         // (si tú guardas en vivo, úsalo)
const errorArqueo = ref("");
const lastSavedAt = ref("");
const focusedKey = ref(null);

function fmt(v) {
  return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" })
    .format(Number(v ?? 0));
}

const totalContado = computed(() => {
  if (modo.value === "manual") return Number(contadoManual.value || 0);
  return denoms.reduce((acc, d) => acc + Number(form[d.key] || 0) * d.value, 0);
});

const difLocal = computed(() =>
  totalContado.value - Number(props.corte?.esperado_efectivo ?? 0)
);

const manualValido = computed(() => {
  const n = Number(contadoManual.value);
  return Number.isFinite(n) && n >= 0;
});

function setVal(key, v) {
  const n = Math.trunc(Number(v));
  form[key] = Number.isFinite(n) ? Math.max(0, n) : 0;
}

function resetArqueo() {
  denoms.forEach(({ key }) => (form[key] = 0));
}

/**
 * ✅ AQUÍ ESTÁ EL CAMBIO CLAVE
 * Arqueo -> manda denominaciones
 * Manual -> manda contado_efectivo
 */
function submit() {
  emit("submit", {
    modo: modo.value,

    ...(modo.value === "arqueo"
      ? { ...form }
      : { contado_efectivo: Number(contadoManual.value || 0) }
    ),

    contado_tarjeta: Number(formCierre.contado_tarjeta || 0),
    contado_transferencia: Number(formCierre.contado_transferencia || 0),
    notas_cierre: formCierre.notas_cierre?.trim() || null,
  });
}

// Precarga al abrir
watch(
  () => props.open,
  (v) => {
    if (!v) return;

    modo.value = "arqueo";

    const d = props.corte?.desglose ?? {};
    denoms.forEach(({ key }) => (form[key] = Number(d[key] ?? 0)));

    contadoManual.value = Number(props.corte?.contado_efectivo ?? 0);

    Object.assign(formCierre, {
      contado_tarjeta: Number(props.corte?.contado_tarjeta ?? 0),
      contado_transferencia: Number(props.corte?.contado_transferencia ?? 0),
      notas_cierre: props.corte?.notas_cierre ?? "",
    });

    errorArqueo.value = "";
    lastSavedAt.value = "";
    focusedKey.value = null;
  }
);

// Atajos teclado (opcional)
function onKeydown(e) {
  if (!props.open) return;
  if (modo.value !== "arqueo") return;
  const key = focusedKey.value;
  if (!key) return;

  if (e.key === "ArrowUp") {
    e.preventDefault();
    setVal(key, (form[key] || 0) + 1);
  }
  if (e.key === "ArrowDown") {
    e.preventDefault();
    setVal(key, Math.max(0, (form[key] || 0) - 1));
  }
}

watch(
  () => props.open,
  (v) => {
    if (v) window.addEventListener("keydown", onKeydown);
    else window.removeEventListener("keydown", onKeydown);
  }
);

onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown));
</script>