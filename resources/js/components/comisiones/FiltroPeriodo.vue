<template>
    <div class="flex flex-wrap items-center gap-2">
        <CalendarRange class="h-4 w-4 text-slate-400" />
        <label class="sr-only" for="periodo-desde">Desde</label>
        <input id="periodo-desde" v-model="desde" type="date" class="campo" :max="hasta || undefined" @change="emitir" />
        <span class="text-sm text-slate-400">a</span>
        <label class="sr-only" for="periodo-hasta">Hasta</label>
        <input id="periodo-hasta" v-model="hasta" type="date" class="campo" :min="desde || undefined" @change="emitir" />
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { CalendarRange } from "lucide-vue-next";

const emit = defineEmits(["cambio"]);

// Por omisión, el mes en curso (del día 1 al último día del mes).
const hoy = new Date();
const desde = ref(iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1)));
const hasta = ref(iso(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)));

function iso(d) {
    const p = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

function emitir() {
    if (desde.value && hasta.value && desde.value <= hasta.value) {
        emit("cambio", { desde: desde.value, hasta: hasta.value });
    }
}

onMounted(emitir);
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm tabular-nums outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
</style>
