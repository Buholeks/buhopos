<template>
    <button
        type="button"
        class="relative flex h-full w-full items-start gap-3 rounded-xl border p-3 text-left transition focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100"
        :class="clases"
        :aria-pressed="activa"
        :disabled="bloqueada"
        @click="emit('elegir')"
    >
        <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition"
            :class="activa ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500'"
        >
            <component :is="icono" class="h-[18px] w-[18px]" />
        </span>
        <span class="min-w-0">
            <span class="block text-sm font-semibold" :class="activa ? 'text-emerald-800' : 'text-slate-800'">{{ titulo }}</span>
            <span v-if="texto" class="mt-0.5 block text-xs leading-snug text-slate-500">{{ texto }}</span>
        </span>
        <CircleCheck v-if="activa" class="absolute right-2.5 top-2.5 h-4 w-4 text-emerald-600" />
    </button>
</template>

<script setup>
import { computed } from "vue";
import { CircleCheck } from "lucide-vue-next";

const props = defineProps({
    activa: { type: Boolean, default: false },
    bloqueada: { type: Boolean, default: false },
    icono: { type: [Object, Function], required: true },
    titulo: { type: String, required: true },
    texto: { type: String, default: "" },
});

const emit = defineEmits(["elegir"]);

const clases = computed(() => {
    if (props.activa) return "border-emerald-500 bg-emerald-50/60 ring-1 ring-emerald-500";
    if (props.bloqueada) return "cursor-not-allowed border-slate-200 opacity-40";
    return "border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50";
});
</script>
