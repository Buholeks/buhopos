<template>
    <div>
        <div
            class="flex items-center overflow-hidden rounded-xl border bg-white transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
            :class="[error ? 'border-red-400' : 'border-slate-200', disabled ? 'bg-slate-100 opacity-60' : '']"
        >
            <span v-if="prefijo" class="pl-3 text-sm font-semibold text-slate-400">{{ prefijo }}</span>
            <input
                :id="id"
                :value="modelValue"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="!!error"
                class="w-full min-w-0 bg-transparent px-2 text-sm tabular-nums outline-none"
                :class="compacto ? 'py-1.5' : 'py-2.5'"
                @input="emit('update:modelValue', $event.target.value === '' ? '' : Number($event.target.value))"
            />
            <span v-if="sufijo" class="whitespace-nowrap pr-3 text-xs font-semibold text-slate-400">{{ sufijo }}</span>
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>

<script setup>
defineProps({
    modelValue: { type: [Number, String], default: "" },
    prefijo: { type: String, default: "" },
    sufijo: { type: String, default: "" },
    placeholder: { type: String, default: "" },
    error: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
    compacto: { type: Boolean, default: false },
    id: { type: String, default: undefined },
});

const emit = defineEmits(["update:modelValue"]);
</script>
