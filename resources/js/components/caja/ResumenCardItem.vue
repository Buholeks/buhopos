<template>
    <div class="rounded-2xl border p-5" :class="box">
        <p
            class="text-xs font-semibold uppercase tracking-wider"
            :class="title"
        >
            {{ titulo }}
        </p>

        <p class="mt-2 font-mono text-2xl font-bold text-slate-900">
            {{ valor }}
        </p>

        <p class="mt-1 text-xs text-slate-500">
            {{ sub }}
        </p>
    </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    titulo: { type: String, default: "" },
    valor: { type: String, default: "" },
    sub: { type: String, default: "" },
    tone: { type: String, default: "slate" }, // emerald | indigo | amber | cyan | slate
});

const box = computed(() => {
    const t = props.tone;
    if (t === "emerald") return "border-emerald-200 bg-emerald-50";
    if (t === "indigo") return "border-indigo-200 bg-indigo-50";
    if (t === "amber") return "border-amber-200 bg-amber-50";
    if (t === "cyan") return "border-cyan-200 bg-cyan-50";
    return "border-slate-200 bg-white";
});

const title = computed(() => {
    const t = props.tone;
    if (t === "emerald") return "text-emerald-700";
    if (t === "indigo") return "text-indigo-700";
    if (t === "amber") return "text-amber-700";
    if (t === "cyan") return "text-cyan-700";
    return "text-slate-700";
});
</script>
