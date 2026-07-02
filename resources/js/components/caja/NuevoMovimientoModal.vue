<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="$emit('close')"
    >
        <div
            class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
        >
            <div class="mb-4 flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">
                        Registrar movimiento
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Ingresos o egresos fuera de venta.
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600"
                    @click="$emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-700">
                        Tipo
                    </label>

                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button
                            v-for="t in TIPOS"
                            :key="t"
                            type="button"
                            class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                            :class="
                                form.tipo === t
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                            "
                            @click="form.tipo = t"
                        >
                            {{ t === "ingreso" ? "Entrada" : "Salida" }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">
                        Forma de pago
                    </label>

                    <select
                        v-model="form.forma_pago"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    >
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>

                <div v-if="form.forma_pago === 'transferencia'">
                    <label class="text-sm font-medium text-slate-700">
                        Cuenta bancaria
                    </label>

                    <select
                        v-model="form.cuenta_bancaria_id"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    >
                        <option value="" disabled>Selecciona una cuenta…</option>
                        <option v-for="c in cuentasBancarias" :key="c.id" :value="c.id">
                            {{ c.nombre }}<template v-if="c.banco"> ({{ c.banco }})</template>
                        </option>
                    </select>
                </div>

                <div v-if="form.forma_pago === 'tarjeta'">
                    <label class="text-sm font-medium text-slate-700">
                        Terminal
                    </label>

                    <select
                        v-model="form.terminal_pago_id"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    >
                        <option value="" disabled>Selecciona una terminal…</option>
                        <option v-for="t in terminalesPago" :key="t.id" :value="t.id">
                            {{ t.nombre }}<template v-if="t.banco"> ({{ t.banco }})</template>
                        </option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">
                        Monto
                    </label>

                    <div
                        class="mt-1 flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
                    >
                        <span
                            class="border-r border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500"
                        >
                            $
                        </span>

                        <input
                            v-model.number="form.monto"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            class="w-full px-3 py-2 text-sm font-mono outline-none"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">
                        Motivo
                    </label>

                    <input
                        v-model="form.concepto"
                        placeholder="Ej: retiro, pago proveedor, gasto..."
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    />
                </div>
            </div>

            <div class="mt-6 flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('close')"
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    :disabled="loading || !isValid"
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    @click="submit"
                >
                    {{ loading ? "Guardando..." : "Guardar" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, computed, watch } from "vue";
import { X } from "lucide-vue-next";

const props = defineProps({
    open: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    cuentasBancarias: { type: Array, default: () => [] },
    terminalesPago: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "submit"]);

const TIPOS = ["ingreso", "egreso"];

const form = reactive({
    tipo: "egreso",
    forma_pago: "efectivo",
    cuenta_bancaria_id: "",
    terminal_pago_id: "",
    monto: 0,
    concepto: "",
});

const isValid = computed(() => {
    if (form.concepto.trim().length === 0 || !(Number(form.monto) > 0)) return false;
    if (form.forma_pago === "transferencia" && !form.cuenta_bancaria_id) return false;
    if (form.forma_pago === "tarjeta" && !form.terminal_pago_id) return false;
    return true;
});

function submit() {
    if (!isValid.value) return;

    emit("submit", {
        ...form,
        cuenta_bancaria_id: form.forma_pago === "transferencia" ? form.cuenta_bancaria_id || null : null,
        terminal_pago_id: form.forma_pago === "tarjeta" ? form.terminal_pago_id || null : null,
    });
}

watch(
    () => props.open,
    (v) => {
        if (v) {
            Object.assign(form, {
                tipo: "egreso",
                forma_pago: "efectivo",
                cuenta_bancaria_id: "",
                terminal_pago_id: "",
                monto: 0,
                concepto: "",
            });
        }
    },
);

watch(
    () => form.forma_pago,
    () => {
        form.cuenta_bancaria_id = "";
        form.terminal_pago_id = "";
    },
);
</script>
