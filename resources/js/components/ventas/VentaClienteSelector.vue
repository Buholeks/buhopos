
<template>
    <div
        class="flex items-center gap-5 rounded-2xl border bg-white p-4 transition"
        :class="
            asignado
                ? 'border-stone-200'
                : 'border-dashed border-stone-300 bg-stone-50/60'
        "
    >
        <!-- Avatar -->
        <div class="relative shrink-0">
            <div
                class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-semibold"
                :class="
                    asignado
                        ? 'bg-stone-900 text-white'
                        : 'bg-stone-100 text-stone-400'
                "
            >
                <span v-if="asignado">{{ iniciales }}</span>
                <UserRound v-else class="h-5 w-5" />
            </div>
            <span
                v-if="asignado"
                class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"
            />
        </div>

        <!-- Info -->
        <div class="min-w-0 flex-1">
            <template v-if="asignado">
                <div class="flex items-center gap-2">
                    <span
                        class="text-[11px] font-medium uppercase tracking-[0.14em] text-stone-500"
                    >
                        Cliente
                    </span>
                    <span class="h-1 w-1 rounded-full bg-stone-300" />
                    <span
                        v-if="cliente?.tag"
                        class="text-[11px] font-medium text-emerald-700"
                    >
                        {{ cliente.tag }}
                    </span>
                </div>
                <h2
                    class="truncate text-[17px] font-semibold tracking-tight text-stone-900"
                >
                    {{ nombreCliente }}
                </h2>
                <p class="truncate text-xs text-stone-500">
                    {{ subtextoCliente }}
                </p>
            </template>

            <template v-else>
                <div class="flex items-center gap-2">
                    <span
                        class="text-[11px] font-medium uppercase tracking-[0.14em] text-rose-600"
                    >
                        Requerido
                    </span>
                </div>
                <h2
                    class="truncate text-[17px] font-semibold tracking-tight text-stone-900"
                >
                    Selecciona un cliente
                </h2>
                <p class="truncate text-xs text-stone-500">
                    No se puede registrar la venta sin un cliente asignado
                </p>
            </template>
        </div>

        <!-- Buscador -->
        <div class="w-[300px] shrink-0">
            <BaseSearchSelect
                :model-value="cliente?.id ?? null"
                label=""
                placeholder="Buscar cliente..."
                :fetcher="buscarClientes"
                :min-chars="1"
                :debounce-ms="250"
                :label-key="(it) => it.nombre || it.name || 'Sin nombre'"
                :sub-label-key="
                    (it) =>
                        it.telefono ||
                        it.phone ||
                        it.email ||
                        'Sin referencia'
                "
                value-key="id"
                @selected="onSelected"
            />
        </div>

        <!-- Quitar (sólo para cambiar; sigue siendo obligatorio asignar otro) -->
        <button
            v-if="asignado"
            type="button"
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:bg-stone-50 hover:text-stone-900"
            title="Quitar cliente"
            @click="$emit('clear')"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { UserRound, X } from "lucide-vue-next";
import http from "@/lib/http";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";

const props = defineProps({
    cliente: { type: Object, default: null },
});

const emit = defineEmits(["select", "clear"]);

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

const subtextoCliente = computed(() => {
    const c = props.cliente;
    if (!c) return "";
    const partes = [];
    if (c.telefono || c.phone) partes.push(c.telefono || c.phone);
    if (c.compras != null) partes.push(`${c.compras} compras`);
    if (c.ultima) partes.push(`última ${c.ultima}`);
    return partes.length
        ? partes.join(" · ")
        : c.email || "Cliente asignado a la venta";
});

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
}
</script>