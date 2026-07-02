<template>
    <div class="rounded-xl border border-black-200 bg-white p-2">
        <div class="mb-1.5 flex items-center justify-between gap-2 flex-wrap">
            <label
                class="block text-[11px] font-semibold uppercase tracking-widest text-slate-400"
            >
                Buscar producto
            </label>

            <div class="flex items-center gap-1">
                <button
                    v-for="opt in filtroOpciones"
                    :key="opt.value"
                    type="button"
                    class="rounded-full px-2.5 py-0.5 text-[11px] font-medium transition border"
                    :class="
                        filtroStock === opt.value
                            ? opt.activeClass
                            : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300 hover:text-slate-600'
                    "
                    @click="$emit('update:filtroStock', opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <div class="relative">
            <BaseInput
                ref="baseRef"
                v-model="local"
                :leftIcon="Search"
                :disabled="disabled"
                placeholder="Nombre, código, SKU o código de barras…"
                title="F2 Buscar · Enter Buscar/seleccionar · Ctrl+Enter Guardar · Del Eliminar fila"
                autocomplete="off"
                @keydown.down.prevent="$emit('moveCursor', 1)"
                @keydown.up.prevent="$emit('moveCursor', -1)"
                @keydown.enter.prevent="onEnter"
                @keydown.esc.prevent="$emit('close')"
                @input="onInput"
            >
                <template #right>
                    <Loader2
                        v-if="buscando"
                        class="mr-2 h-4 w-4 animate-spin text-emerald-600"
                    />
                    <button
                        v-else-if="local"
                        type="button"
                        class="mr-1 rounded-lg p-1 text-slate-400 transition hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="disabled"
                        @click="$emit('clear')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </template>
            </BaseInput>

            <!-- Dropdown -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 translate-y-[-4px] scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-[-2px] scale-95"
            >
                <div
                    v-if="!disabled && dropdown && resultados.length > 0"
                    class="absolute left-0 right-0 top-[calc(100%+6px)] z-40 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_16px_48px_rgba(0,0,0,0.10),0_4px_12px_rgba(0,0,0,0.06)]"
                >
                    <div class="max-h-72 overflow-y-auto">
                        <div
                            v-for="(r, i) in resultados"
                            :key="r.id ?? `${r.producto_id}-${i}`"
                            class="flex items-center gap-3 border-b border-slate-50 px-4 py-2.5 transition"
                            :class="[
                                r.sin_stock
                                    ? 'cursor-not-allowed opacity-50'
                                    : 'cursor-pointer',
                                !r.sin_stock && cursor === i
                                    ? 'bg-emerald-50'
                                    : 'bg-white',
                            ]"
                            @mouseenter="!r.sin_stock && $emit('hoverItem', i)"
                            @click="!r.sin_stock && $emit('selectItem', r)"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50"
                            >
                                <img
                                    v-if="r.imagen_url"
                                    :src="r.imagen_url"
                                    :alt="r.nombre"
                                    class="h-full w-full object-contain"
                                />
                                <ImageOff
                                    v-else
                                    class="h-4 w-4 text-slate-300"
                                />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate text-[13px] font-medium text-slate-900"
                                >
                                    {{ r.nombre }}
                                    <span
                                        v-if="r.nombre_variante"
                                        class="ml-1 text-emerald-600"
                                    >
                                        — {{ r.nombre_variante }}
                                    </span>
                                </div>

                                <div
                                    class="mt-0.5 flex gap-2 font-mono text-[11px] text-slate-400"
                                >
                                    <span>{{ r.codigo }}</span>
                                    <span v-if="r.sku">· {{ r.sku }}</span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-sky-100 bg-sky-50 text-sky-600 transition hover:border-sky-200 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="disabled"
                                title="Ver existencias por sucursal"
                                @click.stop="$emit('checkStock', r)"
                            >
                                <Cloud class="h-4 w-4" />
                            </button>

                            <div class="text-right">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="
                                        r.sin_stock
                                            ? 'bg-red-100 text-red-500'
                                            : r.stock <= 5
                                              ? 'bg-amber-100 text-amber-600'
                                              : 'bg-green-100 text-green-600'
                                    "
                                >
                                    {{
                                        r.sin_stock
                                            ? "Sin stock"
                                            : `${r.stock} uds`
                                    }}
                                </span>

                                <div
                                    class="mt-1 font-mono text-[13px] font-medium text-slate-700"
                                >
                                    {{ formatPrecio(r.precio_venta) }}
                                </div>
                            </div>

                            <div
                                v-if="!r.sin_stock"
                                class="flex h-6 w-6 items-center justify-center rounded-md border text-[11px] transition"
                                :class="
                                    cursor === i
                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-600'
                                        : 'border-slate-200 bg-white text-slate-400'
                                "
                            >
                                ↵
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex gap-4 border-t border-slate-100 bg-slate-50 px-4 py-2 text-[11px] text-slate-400"
                    >
                        <span>↑↓ Navegar</span>
                        <span>↵ Buscar / seleccionar</span>
                        <span>Esc Cerrar</span>
                    </div>
                </div>
            </Transition>

            <div
                v-if="
                    !disabled &&
                    dropdown &&
                    resultados.length === 0 &&
                    local.length > 0 &&
                    !buscando
                "
                class="absolute left-0 right-0 top-[calc(100%+6px)] z-40 flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-8 text-center shadow-[0_16px_48px_rgba(0,0,0,0.10),0_4px_12px_rgba(0,0,0,0.06)]"
            >
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100"
                >
                    <SearchX class="h-5 w-5 text-slate-400" />
                </div>
                <p class="text-[13px] text-slate-500">
                    Sin resultados para
                    <strong class="text-slate-700">"{{ local }}"</strong>
                </p>
                <p class="text-[11px] text-slate-400">
                    Verifica el nombre, código o SKU e intenta de nuevo
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import { Search, X, Loader2, ImageOff, Cloud, SearchX } from "lucide-vue-next";

const props = defineProps({
    modelValue: { type: String, default: "" },
    filtroStock: { type: String, default: "con_existencia" },
    resultados: { type: Array, default: () => [] },
    buscando: { type: Boolean, default: false },
    dropdown: { type: Boolean, default: false },
    cursor: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
    formatPrecio: { type: Function, required: true },
});

const filtroOpciones = [
    {
        value: "todos",
        label: "Todos",
        activeClass: "border-slate-400 bg-slate-100 text-slate-700",
    },
    {
        value: "con_existencia",
        label: "Con existencia",
        activeClass: "border-emerald-400 bg-emerald-50 text-emerald-700",
    },
    {
        value: "sin_existencia",
        label: "Sin existencia",
        activeClass: "border-red-300 bg-red-50 text-red-600",
    },
];

const emit = defineEmits([
    "update:modelValue",
    "update:filtroStock",
    "input",
    "enter",
    "moveCursor",
    "selectCursor",
    "close",
    "clear",
    "selectItem",
    "hoverItem",
    "checkStock",
]);

const baseRef = ref(null);

function onEnter() {
    if (props.dropdown && props.resultados.length > 0) {
        emit("selectCursor");
        return;
    }

    emit("enter");
}

const local = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

function onInput() {
    emit("input");
}

defineExpose({
    focus: () => baseRef.value?.$el?.querySelector("input")?.focus(),
});
</script>
