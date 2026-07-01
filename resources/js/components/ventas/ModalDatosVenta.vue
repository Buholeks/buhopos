<template>
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4">
        <div class="w-full max-w-lg overflow-visible rounded-3xl bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Datos del vendedor</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Selecciona quién realiza la venta.
                </p>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div
                    v-if="cliente"
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3"
                >
                    <p class="text-[11px] font-black uppercase tracking-wide text-emerald-700">
                        Cliente seleccionado
                    </p>
                    <p class="mt-1 text-sm font-semibold text-emerald-950">
                        {{ cliente.nombre || cliente.name || "Sin nombre" }}
                    </p>
                </div>

                <BaseSearchSelect
                    ref="vendedorRef"
                    :model-value="vendedor?.id ?? vendedorId ?? null"
                    label="Vendedor"
                    placeholder="Buscar vendedor..."
                    :fetcher="buscarVendedores"
                    :min-chars="1"
                    :debounce-ms="250"
                    :label-key="(it) => it.name || it.nombre || 'Sin nombre'"
                    :sub-label-key="(it) => it.email || it.telefono || ''"
                    value-key="id"
                    required
                    @selected="$emit('select-vendedor', $event)"
                />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                <button
                    type="button"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('cancel')"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    @click="$emit('confirm')"
                >
                    Continuar al cobro
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from "vue";
import http from "@/lib/http";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";

const props = defineProps({
    cliente: { type: Object, default: null },
    vendedorId: { type: [String, Number, null], default: null },
    vendedor: { type: Object, default: null },
});

defineEmits([
    "select-vendedor",
    "cancel",
    "confirm",
]);

const vendedorRef = ref(null);

onMounted(() => nextTick(() => vendedorRef.value?.focus?.()));

async function buscarVendedores(q) {
    const { data } = await http.get("/api/users/vendedores", { params: { q } });
    return Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
}
</script>
