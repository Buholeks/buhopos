<template>
    <component
        :is="enabled ? 'RouterLink' : 'button'"
        :to="enabled ? to : undefined"
        type="button"
        class="group relative flex items-center gap-3 rounded-xl border bg-white px-4 py-3 transition focus:outline-none focus:ring-2 focus:ring-slate-200"
        :class="
            enabled
                ? 'border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/40'
                : 'border-slate-200 opacity-50 cursor-not-allowed'
        "
        @click="onDisabledClick"
    >
        <!-- Icon -->
        <div
            class="flex h-9 w-9 items-center justify-center rounded-lg border bg-slate-50 transition"
            :class="
                enabled
                    ? 'border-slate-200 text-slate-700 group-hover:border-emerald-200 group-hover:text-emerald-600'
                    : 'border-slate-200 text-slate-400'
            "
        >
            <component v-if="Icon" :is="Icon" class="h-4 w-4" />
            <Circle v-else class="h-4 w-4" />
        </div>

        <!-- Label -->
        <span class="flex-1 text-sm font-medium text-slate-800">
            {{ label }}
        </span>

        <!-- Arrow -->
        <ArrowRight
            v-if="enabled"
            class="h-4 w-4 text-slate-400 opacity-0 transition group-hover:opacity-100 group-hover:translate-x-0.5"
        />
    </component>
</template>

<script setup>
import { computed } from "vue";
import { toastWarning } from "@/lib/alert.js";
import { ArrowRight, Circle } from "lucide-vue-next";

const props = defineProps({
    label: { type: String, required: true },
    to: { type: [String, Object], default: null },
    icon: { type: [Object, Function], default: null },
});

const enabled = computed(() => !!props.to);
const Icon = computed(() => props.icon);

function onDisabledClick() {
    if (!enabled.value) toastWarning("Disponible próximamente");
}
</script>
