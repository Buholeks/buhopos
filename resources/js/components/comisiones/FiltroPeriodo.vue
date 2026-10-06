<template>
    <div class="flex flex-wrap items-end gap-2">
        <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Periodo">
            <button
                v-for="p in PRESETS"
                :key="p.valor"
                type="button"
                role="radio"
                :aria-checked="preset === p.valor"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                :class="preset === p.valor ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                @click="elegir(p.valor)"
            >
                {{ p.label }}
            </button>
        </div>
        <div v-if="preset === 'personalizado'" class="flex items-center gap-2">
            <label class="sr-only" for="periodo-desde">Desde</label>
            <input id="periodo-desde" v-model="desde" type="date" class="campo" :max="hasta" @change="emitir" />
            <span class="text-sm text-slate-400">a</span>
            <label class="sr-only" for="periodo-hasta">Hasta</label>
            <input id="periodo-hasta" v-model="hasta" type="date" class="campo" :min="desde" @change="emitir" />
        </div>
        <p v-else class="px-1 pb-2 text-sm text-slate-500">{{ textoPeriodo }}</p>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";

const emit = defineEmits(["cambio"]);

const PRESETS = [
    { valor: "quincena", label: "Esta quincena" },
    { valor: "mes", label: "Este mes" },
    { valor: "mes_pasado", label: "Mes pasado" },
    { valor: "personalizado", label: "Otro periodo" },
];

const preset = ref("quincena");
const desde = ref("");
const hasta = ref("");

const fmtLargo = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric" });

const textoPeriodo = computed(() => {
    if (!desde.value || !hasta.value) return "";
    return `${fmtLargo.format(aFecha(desde.value))} – ${fmtLargo.format(aFecha(hasta.value))}`;
});

function iso(d) {
    const p = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

function aFecha(texto) {
    const [a, m, d] = texto.split("-").map(Number);
    return new Date(a, m - 1, d);
}

function rango(valor) {
    const hoy = new Date();
    const a = hoy.getFullYear();
    const m = hoy.getMonth();
    switch (valor) {
        case "quincena":
            return hoy.getDate() <= 15
                ? [new Date(a, m, 1), new Date(a, m, 15)]
                : [new Date(a, m, 16), new Date(a, m + 1, 0)];
        case "mes":
            return [new Date(a, m, 1), new Date(a, m + 1, 0)];
        case "mes_pasado":
            return [new Date(a, m - 1, 1), new Date(a, m, 0)];
        default:
            return null;
    }
}

function elegir(valor) {
    preset.value = valor;
    const r = rango(valor);
    if (r) {
        desde.value = iso(r[0]);
        hasta.value = iso(r[1]);
    }
    emitir();
}

function emitir() {
    if (desde.value && hasta.value && desde.value <= hasta.value) {
        emit("cambio", { desde: desde.value, hasta: hasta.value });
    }
}

onMounted(() => elegir("quincena"));
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
</style>
