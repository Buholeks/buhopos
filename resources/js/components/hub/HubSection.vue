<template>
    <section class="space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">
                {{ title }}
            </h2>
        </div>

        <div
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <HubCard
                v-for="item in items"
                :key="item.label"
                :label="item.label"
                :to="item.to"
                :icon="item.icon"
            />
        </div>
    </section>
</template>

<script setup>
import HubCard from "./HubCard.vue";

defineProps({
    title: { type: String, required: true },
    items: { type: Array, required: true },
});
</script>
