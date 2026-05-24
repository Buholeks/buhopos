<template>
    <div class="relative" :class="rootClass">
        <!-- Label -->
        <label
            v-if="label"
            class="mb-1 block text-sm font-medium text-slate-700"
        >
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <!-- Control -->
        <div
            class="flex items-center gap-2 rounded-lg border bg-white px-3 transition"
            :class="[
                disabled ? 'pointer-events-none opacity-60' : '',
                error
                    ? 'border-red-300 ring-4 ring-red-100'
                    : isFocused
                      ? 'border-emerald-500 ring-4 ring-emerald-100'
                      : 'border-slate-200 hover:border-emerald-500',
            ]"
            @mousedown.prevent="focusInput"
        >
            <!-- Icon (optional) -->
            <span v-if="$slots.icon" class="shrink-0 text-slate-400">
                <slot name="icon" />
            </span>

            <!-- Prefix (optional) -->
            <span v-if="prefix" class="shrink-0 text-sm text-slate-500">
                {{ prefix }}
            </span>

            <!-- Input -->
            <input
                ref="inputEl"
                v-bind="$attrs"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                class="flex-1 py-2.5 text-sm outline-none placeholder:text-slate-400"
                :class="inputClass"
                @focus="isFocused = true"
                @blur="isFocused = false"
                @input="$emit('update:modelValue', $event.target.value)"
            />

            <!-- Suffix (optional) -->
            <span v-if="suffix" class="shrink-0 text-sm text-slate-500">
                {{ suffix }}
            </span>

            <!-- Suffix slot (optional) -->
            <span v-if="$slots.suffix" class="shrink-0">
                <slot name="suffix" />
            </span>
        </div>

        <!-- Hint / Error -->
        <p v-if="hint && !error" class="mt-1 text-xs text-slate-400">
            {{ hint }}
        </p>
        <p v-if="error" class="mt-1 text-xs text-red-600">
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import { ref } from "vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    label: { type: String, default: "" },
    placeholder: { type: String, default: "" },
    type: { type: String, default: "text" },
    error: { type: String, default: "" },
    hint: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },

    // estilos extra
    rootClass: { type: String, default: "" },
    inputClass: { type: String, default: "" },

    // opcionales
    prefix: { type: String, default: "" },
    suffix: { type: String, default: "" },
});

defineEmits(["update:modelValue"]);

const inputEl = ref(null);
const isFocused = ref(false);

function focusInput() {
    inputEl.value?.focus();
}
</script>
