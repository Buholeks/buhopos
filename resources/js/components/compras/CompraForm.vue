<template>
    <div class="space-y-4">
        <!-- Datos de la compra -->
        <div>
            <div class="mb-4 flex items-center gap-2">
                <FileText class="h-4 w-4 text-slate-400" />
                <h2 class="text-sm font-semibold text-slate-800">
                    Datos de la compra
                </h2>
            </div>

            <!-- Proveedor (BaseSearchSelect) -->
            <div class="mb-4">
                <BaseSearchSelect
                    :modelValue="
                        form.proveedor_id ? String(form.proveedor_id) : null
                    "
                    @update:modelValue="
                        (v) => updateField('proveedor_id', v ? Number(v) : '')
                    "
                    :items="proveedores"
                    label="Proveedor"
                    placeholder="Buscar proveedor…"
                    hint="Puedes buscar por nombre comercial"
                    labelKey="nombre_comercial"
                    valueKey="id"
                />
            </div>

            <!-- Folio -->
            <div class="mb-4">
                <BaseInput
                    label="N° Factura / Folio"
                    placeholder="FAC-0001"
                    :modelValue="form.folio"
                    @update:modelValue="(v) => updateField('folio', v)"
                />
                <p class="mt-1 text-xs text-slate-400">(opcional)</p>
            </div>

            <!-- Fecha -->
            <div class="mb-4">
                <BaseInput
                    label="Fecha de compra"
                    type="date"
                    required
                    :modelValue="form.fecha"
                    @update:modelValue="(v) => updateField('fecha', v)"
                />
            </div>

            <!-- Forma de pago -->
            <div class="mb-4">
                <label class="text-sm font-semibold text-slate-800"
                    >Forma de pago</label
                >

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button
                        v-for="fp in FORMAS_PAGO"
                        :key="fp.valor"
                        type="button"
                        @click="updateField('forma_pago', fp.valor)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-4"
                        :class="
                            form.forma_pago === fp.valor
                                ? 'border-emerald-400 bg-emerald-50 text-emerald-800 focus:ring-emerald-100'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 focus:ring-slate-100'
                        "
                    >
                        <component :is="fp.icon" class="h-4 w-4" />
                        {{ fp.label }}
                    </button>
                </div>
            </div>

            <!-- Fecha vencimiento (solo crédito) -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-100"
                leave-to-class="opacity-0"
            >
               <div v-if="['credito', 'tarjeta_credito'].includes(form.forma_pago)">
                    <BaseInput
                        label="Fecha de vencimiento"
                        type="date"
                        :modelValue="form.fecha_vencimiento"
                        @update:modelValue="
                            (v) => updateField('fecha_vencimiento', v)
                        "
                    />
                    <p class="mt-1 text-xs text-slate-400">
                        Se usa para control de pagos a proveedor.
                    </p>
                </div>
            </Transition>

            <!-- Notas -->
            <div class="space-y-1">
                <label class="text-sm font-semibold text-slate-800">
                    Notas
                    <span class="text-xs font-normal text-slate-400"
                        >(opcional)</span
                    >
                </label>

                <textarea
                    :value="form.notas"
                    @input="updateField('notas', $event.target.value)"
                    rows="2"
                    placeholder="Observaciones de la compra…"
                    class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                />
            </div>
        </div>

        <!-- Total -->
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-5 w-5 items-center justify-center rounded-md bg-emerald-100 text-xs font-bold text-emerald-700"
                    >
                        $
                    </span>
                    <span class="text-sm font-semibold text-emerald-800">
                        Total de la compra
                    </span>
                </div>

                <span
                    class="font-mono text-xl font-extrabold text-emerald-900 tabular-nums"
                >
                    {{ formatPrecio(total) }}
                </span>
            </div>

            <div class="mt-2 text-xs text-emerald-700">
                {{ cantidadArticulos }} artículo{{
                    cantidadArticulos !== 1 ? "s" : ""
                }}
            </div>
        </div>
    </div>
</template>

<script setup>
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";

import {
    FileText,
    Banknote,
    ArrowLeftRight,
    CreditCard,
    FileCheck,
    CreditCardIcon,
    ClockIcon,
} from "lucide-vue-next";

const FORMAS_PAGO = [
    { valor: "efectivo", label: "Efectivo", icon: Banknote },
    {
        valor: "transferencia",
        label: "Transferencia",
        icon: ArrowLeftRight,

    },
    { valor: "tarjeta_debito", label: "T. Débito", icon: CreditCardIcon },
    { valor: "tarjeta_credito", label: "T. Crédito", icon: CreditCardIcon },
    { valor: "credito", label: "Crédito", icon: ClockIcon },
];

const props = defineProps({
    proveedores: { type: Array, required: true },
    form: { type: Object, required: true },
    total: { type: Number, default: 0 },
    cantidadArticulos: { type: Number, default: 0 },
    formatPrecio: { type: Function, required: true },
});

const emit = defineEmits(["update:form"]);

function updateField(key, value) {
    emit("update:form", { key, value });
}
// function updateField(key, value) {
//   emit('update:form', { ...props.form, [key]: value })
// }
</script>
