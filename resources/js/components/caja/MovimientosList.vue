<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">
                    Movimientos extras
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Retiros, ingresos, ajustes autorizados.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700"
                @click="$emit('nuevo')"
            >
                <Plus class="h-4 w-4" />
                Agregar
            </button>
        </div>

        <div
            v-if="!movimientos.length"
            class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400"
        >
            Sin movimientos registrados
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="m in movimientos"
                :key="m.id"
                class="flex items-center justify-between rounded-xl border border-slate-100 p-3 hover:bg-slate-50"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800">
                        {{ m.concepto }}
                    </p>
                    <p class="text-xs text-slate-400">
                        {{ m.tipo }} · {{ m.forma_pago }} ·
                        {{ m.user?.name ?? "Usuario" }}
                    </p>
                </div>

                <div class="ml-3 flex items-center gap-2">
                    <span
                        class="font-mono text-sm font-semibold"
                        :class="
                            m.tipo === 'ingreso'
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        "
                    >
                        {{ m.tipo === "ingreso" ? "+" : "−" }}{{ fmt(m.monto) }}
                    </span>

                    <!-- <button
                        type="button"
                        class="rounded-lg p-2 text-slate-300 hover:bg-rose-50 hover:text-rose-600"
                        @click="$emit('eliminar', m.id)"
                        title="Eliminar"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button> -->
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Plus, Trash2 } from "lucide-vue-next";

defineProps({
    movimientos: { type: Array, default: () => [] },
});

defineEmits(["nuevo", "eliminar"]);

function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
}
</script>
