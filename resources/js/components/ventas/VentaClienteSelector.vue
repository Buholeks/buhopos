
<template>
    <div ref="containerRef" class="relative inline-flex items-center">
        <!-- Sin cliente: botón icono compacto -->
        <button
            v-if="!asignado"
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-stone-200 bg-white text-stone-500 shadow-sm transition hover:border-stone-300 hover:bg-stone-50 hover:text-stone-700"
            title="Seleccionar cliente (opcional)"
            @click="toggleDropdown"
        >
            <UserRound class="h-3.5 w-3.5 shrink-0" />
        </button>

        <!-- Con cliente: chip compacto -->
        <div
            v-else
            class="flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs"
        >
            <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white">
                {{ iniciales }}
            </div>
            <button
                type="button"
                class="max-w-[120px] truncate font-medium text-emerald-800 hover:text-emerald-900"
                @click="toggleDropdown"
            >
                {{ nombreCliente }}
            </button>
            <button
                type="button"
                class="ml-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-emerald-600 hover:bg-emerald-200 hover:text-emerald-800"
                title="Quitar cliente"
                @click.stop="$emit('clear')"
            >
                <X class="h-3 w-3" />
            </button>
        </div>

        <!-- Dropdown de búsqueda -->
        <div
            v-if="open"
            class="absolute left-0 top-full z-50 mt-1 w-72 rounded-2xl border border-stone-200 bg-white p-3 shadow-xl"
            @click.stop
        >
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Buscar cliente</p>
            <BaseSearchSelect
                :model-value="cliente?.id ?? null"
                label=""
                placeholder="Nombre, teléfono..."
                :fetcher="buscarClientes"
                :min-chars="1"
                :debounce-ms="250"
                :label-key="(it) => it.nombre || it.name || 'Sin nombre'"
                :sub-label-key="(it) => it.telefono || it.phone || it.email || 'Sin referencia'"
                value-key="id"
                @selected="onSelected"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { UserRound, X } from "lucide-vue-next";
import http from "@/lib/http";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";

const props = defineProps({
    cliente: { type: Object, default: null },
});

const emit = defineEmits(["select", "clear"]);

const open = ref(false);
const containerRef = ref(null);

function onDocClick(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener("click", onDocClick));
onBeforeUnmount(() => document.removeEventListener("click", onDocClick));

const asignado = computed(() => !!props.cliente);

const nombreCliente = computed(
    () => props.cliente?.nombre || props.cliente?.name || ""
);

const iniciales = computed(() => {
    const n = nombreCliente.value;
    if (!n) return "?";
    const parts = n.split(" ").filter(Boolean);
    return ((parts[0]?.[0] || "") + (parts[1]?.[0] || "")).toUpperCase();
});

function toggleDropdown() {
    open.value = !open.value;
}

async function buscarClientes(q) {
    const { data } = await http.get("/api/clientes/buscar", {
        params: { q },
    });
    return Array.isArray(data?.data)
        ? data.data
        : Array.isArray(data)
          ? data
          : [];
}

function onSelected(item) {
    emit("select", item);
    open.value = false;
}
</script>
