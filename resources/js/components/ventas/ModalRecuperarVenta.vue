<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">
                        Recuperar venta en espera
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Selecciona una venta suspendida para continuar.
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600"
                    @click="$emit('close')"
                >
                    ✕
                </button>
            </div>

            <div class="max-h-[420px] overflow-y-auto">
                <div
                    v-if="items.length === 0"
                    class="p-10 text-center text-sm text-slate-500"
                >
                    No hay ventas en espera
                </div>

                <div
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-4"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-slate-900">
                            {{ item.referencia || "Venta en espera" }}
                        </div>

                        <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-500">
                            <span>
                                Cliente:
                                <strong class="text-slate-700">
                                    {{ item.cliente?.nombre || item.cliente?.name || "Publico en General" }}
                                </strong>
                            </span>

                            <span>
                                Total:
                                <strong class="text-emerald-700">
                                    {{ formatPrecio(item.total) }}
                                </strong>
                            </span>

                            <span>
                                {{ formatFecha(item.created_at) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="$emit('delete', item.id)"
                        >
                            Eliminar
                        </button>

                        <button
                            type="button"
                            class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                            @click="$emit('recover', item)"
                        >
                            Recuperar
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('close')"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    formatPrecio: { type: Function, required: true },
});

defineEmits(["close", "recover", "delete"]);

function formatFecha(v) {
    if (!v) return "";
    return new Date(v).toLocaleString("es-MX", {
        dateStyle: "short",
        timeStyle: "short",
    });
}
</script>