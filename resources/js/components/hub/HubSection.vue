<template>
    <section class="space-y-2">
        <div>
            <h2 class="text-sm font-black text-slate-900">
                {{ title }}
            </h2>
            <p v-if="description" class="text-[11px] leading-4 text-slate-500">
                {{ description }}
            </p>
        </div>

        <div
            class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
        >
            <HubCard
                v-for="item in visibleItems"
                :key="item.label"
                :label="item.label"
                :to="item.to"
                :icon="item.icon"
            />
        </div>
    </section>
</template>

<script setup>
import { computed } from "vue";
import HubCard from "./HubCard.vue";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: "" },
    items: { type: Array, required: true },
});

const auth = useAuthStore();

const visibleItems = computed(() =>
    props.items.filter(
        (item) =>
            (!item.permiso || auth.can(item.permiso)) &&
            (!item.superAdmin || auth.esSuperAdmin),
    )
);
</script>
