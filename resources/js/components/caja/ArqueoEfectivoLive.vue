<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">
                    Arqueo de efectivo
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Se calcula en vivo. Guarda cuando termines.
                    <span v-if="autosave" class="text-slate-400"
                        >· Autosave cada 20s</span
                    >
                </p>
            </div>

            <div class="text-right">
                <p class="text-xs text-slate-500">Total calculado</p>
                <p class="mt-1 font-mono text-lg font-bold text-slate-900">
                    {{ fmt(totalLocal) }}
                </p>

                <p
                    class="mt-1 text-xs font-semibold"
                    :class="
                        difLocal >= 0 ? 'text-emerald-700' : 'text-rose-700'
                    "
                >
                    {{ difLocal >= 0 ? "Sobrante" : "Faltante" }}
                    {{ fmt(Math.abs(difLocal)) }}
                </p>

                <!-- Estado -->
                <p
                    v-if="saving"
                    class="mt-1 inline-flex items-center gap-2 text-xs text-slate-500"
                >
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                    ></span>
                    Guardando…
                </p>
                <p v-else-if="dirty" class="mt-1 text-xs text-amber-700">
                    Cambios sin guardar
                </p>
                <p v-else-if="lastSavedAt" class="mt-1 text-xs text-slate-400">
                    Guardado ✓ {{ lastSavedAt }}
                </p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <FilaDenom
                v-for="d in denoms"
                :key="d.key"
                :label="d.label"
                :value="form[d.key]"
                :multiplicador="d.value"
                @inc="setVal(d.key, form[d.key] + 1)"
                @dec="setVal(d.key, Math.max(0, form[d.key] - 1))"
                @input="(v) => setVal(d.key, v)"
            />
        </div>

        <!-- Acciones -->
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <label
                class="inline-flex items-center gap-2 text-sm text-slate-600"
            >
                <input
                    type="checkbox"
                    v-model="autosave"
                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200"
                />
                Autosave (borrador)
            </label>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold bg-slate-50 border border-slate-200 text-slate-700 hover:bg-white transition disabled:opacity-60"
                    :disabled="saving || !dirty"
                    @click="resetToSaved"
                >
                    Revertir
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-500 transition focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:opacity-60"
                    :disabled="saving || !dirty"
                    @click="guardar"
                >
                    Guardar
                </button>
            </div>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-medium text-rose-700"
        >
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import {
    computed,
    reactive,
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
} from "vue";
import axios from "axios";

const props = defineProps({
    corteId: { type: Number, required: true },
    esperadoEfectivo: { type: Number, default: 0 },
    initialDesglose: { type: Object, default: () => null },
});

const emit = defineEmits(["updated"]);

const denoms = [
    { key: "billetes_1000", label: "Billete $1000", value: 1000 },
    { key: "billetes_500", label: "Billete $500", value: 500 },
    { key: "billetes_200", label: "Billete $200", value: 200 },
    { key: "billetes_100", label: "Billete $100", value: 100 },
    { key: "billetes_50", label: "Billete $50", value: 50 },
    { key: "billetes_20", label: "Billete $20", value: 20 },
    { key: "monedas_20", label: "Moneda $20", value: 20 },
    { key: "monedas_10", label: "Moneda $10", value: 10 },
    { key: "monedas_5", label: "Moneda $5", value: 5 },
    { key: "monedas_2", label: "Moneda $2", value: 2 },
    { key: "monedas_1", label: "Moneda $1", value: 1 },
    { key: "monedas_050", label: "Moneda $0.50", value: 0.5 },
];

const form = reactive(Object.fromEntries(denoms.map((d) => [d.key, 0])));

const saving = ref(false);
const error = ref("");
const lastSavedAt = ref("");
const dirty = ref(false);
const autosave = ref(true);

let lastSavedSnapshot = null;

function loadInitial() {
    const d = props.initialDesglose || {};
    denoms.forEach(({ key }) => (form[key] = Number(d[key] ?? 0)));
    lastSavedSnapshot = JSON.parse(JSON.stringify({ ...form }));
    dirty.value = false;
    error.value = "";
}
loadInitial();

const totalLocal = computed(() =>
    denoms.reduce((acc, d) => acc + Number(form[d.key] || 0) * d.value, 0),
);

const difLocal = computed(
    () => totalLocal.value - Number(props.esperadoEfectivo || 0),
);

function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}

function setVal(key, v) {
    const n = Number.isFinite(Number(v))
        ? Math.max(0, Math.trunc(Number(v)))
        : 0;
    if (form[key] === n) return;
    form[key] = n;
    dirty.value = true;
    error.value = "";
}

function resetToSaved() {
    if (!lastSavedSnapshot) return;
    Object.keys(lastSavedSnapshot).forEach(
        (k) => (form[k] = Number(lastSavedSnapshot[k] ?? 0)),
    );
    dirty.value = false;
    error.value = "";
}

async function guardar() {
    if (saving.value) return;
    saving.value = true;

    try {
        const payload = {
            ...form,
            total_contado: totalLocal.value,
            diferencia: difLocal.value,
            status: "borrador",
        };

        const { data } = await axios.post(
            `/api/cortes-caja/${props.corteId}/desglose`,
            payload,
        );

        lastSavedAt.value = new Date().toLocaleTimeString("es-MX", {
            timeStyle: "short",
        });
        lastSavedSnapshot = JSON.parse(JSON.stringify({ ...form }));
        dirty.value = false;

        emit("updated", data);
    } catch (e) {
        error.value =
            e.response?.data?.message ?? "No se pudo guardar el arqueo.";
    } finally {
        saving.value = false;
    }
}

/* Autosave cada 20s SOLO si hay cambios */
let autosaveTimer = null;
onMounted(() => {
    autosaveTimer = setInterval(() => {
        if (!autosave.value) return;
        if (!dirty.value) return;
        if (saving.value) return;
        guardar();
    }, 20000);
});
onBeforeUnmount(() => {
    if (autosaveTimer) clearInterval(autosaveTimer);
});

/* Si el padre cambia desglose, sincroniza */
watch(
    () => props.initialDesglose,
    () => loadInitial(),
    { deep: true },
);

/* subcomponente */
const FilaDenom = {
    props: ["label", "value", "multiplicador"],
    emits: ["inc", "dec", "input"],
    template: `
    <div class="rounded-xl border border-slate-200 p-3">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-semibold text-slate-800">{{ label }}</p>
          <p class="text-xs text-slate-500">x {{ multiplicador }}</p>
        </div>
        <div class="font-mono text-sm font-semibold text-slate-900">
          {{ value }}
        </div>
      </div>

      <div class="mt-3 flex items-center gap-2">
        <button type="button" @click="$emit('dec')"
          class="h-9 w-9 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50">
          −
        </button>

        <input type="number" min="0" step="1"
          :value="value"
          @input="$emit('input', $event.target.value)"
          class="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm font-mono outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"/>

        <button type="button" @click="$emit('inc')"
          class="h-9 w-9 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50">
          +
        </button>
      </div>
    </div>
  `,
};
</script>
