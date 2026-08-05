<template>
    <main class="space-y-5 p-2 sm:p-4">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div v-if="mostrarBuscador" class="relative w-full lg:w-80">
                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <input
                    v-model.trim="busqueda"
                    type="search"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-9 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    :placeholder="searchPlaceholder"
                />
                <button
                    v-if="busqueda"
                    type="button"
                    class="absolute right-2 top-1.5 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    title="Limpiar búsqueda"
                    @click="busqueda = ''"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </header>

        <div v-if="gruposFiltrados.length" class="space-y-5">
            <HubSection
                v-for="grupo in gruposFiltrados"
                :key="grupo.title"
                :title="grupo.title"
                :description="grupo.description"
                :items="grupo.items"
            />
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <SearchX class="mx-auto h-8 w-8 text-slate-300" />
            <p class="mt-3 font-bold text-slate-700">No encontramos ese módulo</p>
            <p class="mt-1 text-sm text-slate-400">Prueba con otro nombre o limpia la búsqueda.</p>
        </div>
    </main>
</template>

<script setup>
import { computed, ref } from "vue";
import { Search, SearchX, X } from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import HubSection from "./HubSection.vue";

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: "" },
    groups: { type: Array, required: true },
    searchPlaceholder: { type: String, default: "Buscar módulo" },
});

const auth = useAuthStore();
const busqueda = ref("");

const gruposPermitidos = computed(() =>
    props.groups
        .map((grupo) => ({
            ...grupo,
            items: grupo.items.filter((item) => !item.permiso || auth.can(item.permiso)),
        }))
        .filter((grupo) => grupo.items.length),
);

const totalItems = computed(() =>
    gruposPermitidos.value.reduce((total, grupo) => total + grupo.items.length, 0),
);

const mostrarBuscador = computed(() => totalItems.value > 8);

const gruposFiltrados = computed(() => {
    const termino = normalizar(busqueda.value);
    if (!termino) return gruposPermitidos.value;

    return gruposPermitidos.value
        .map((grupo) => ({
            ...grupo,
            items: grupo.items.filter((item) => normalizar(item.label).includes(termino)),
        }))
        .filter((grupo) => grupo.items.length);
});

function normalizar(valor) {
    return String(valor || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLocaleLowerCase("es-MX");
}
</script>
