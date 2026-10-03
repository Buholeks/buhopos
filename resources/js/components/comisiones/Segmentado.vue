<template>
    <div class="inline-flex w-full rounded-xl bg-slate-100 p-1" role="radiogroup" :aria-label="etiqueta">
        <button
            v-for="op in opciones"
            :key="op.valor"
            type="button"
            role="radio"
            :aria-checked="modelValue === op.valor"
            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
            :class="modelValue === op.valor ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
            @click="emit('update:modelValue', op.valor)"
        >
            <component :is="op.icono" v-if="op.icono" class="h-4 w-4" />
            {{ op.label }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    modelValue: { type: String, default: null },
    opciones: { type: Array, required: true }, // [{ valor, label, icono? }]
    etiqueta: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);
</script>
