<template>
    <div class="rounded-xl border border-black-200 bg-white p-3">
        <label
            class="mb-2 block text-[11px] font-semibold uppercase tracking-widest text-slate-400"
        >
            Buscar producto
            <span
                class="ml-2 normal-case font-normal tracking-normal text-slate-300"
            >
                Nombre, código, SKU o código de barras
            </span>
        </label>

        <div class="relative">
            <BaseInput
                ref="baseRef"
                v-model="local"
                :leftIcon="Search"
                :disabled="disabled"
                placeholder="Escribe y presiona Enter…"
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
                class="absolute left-0 right-0 top-[calc(100%+6px)] z-40 rounded-xl border border-slate-200 bg-white p-6 text-center shadow-[0_16px_48px_rgba(0,0,0,0.10)]"
            >
                <div class="text-2xl">🔍</div>
                <p class="mt-2 text-[13px] text-slate-500">
                    Sin resultados para
                    <strong class="text-slate-700">{{ local }}</strong>
                </p>
            </div>

            <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-400">
                <span class="rounded-full bg-slate-100 px-2 py-0.5"
                    >F2 Buscar</span
                >
                <span class="rounded-full bg-slate-100 px-2 py-0.5"
                    >Enter Buscar</span
                >
                <span class="rounded-full bg-slate-100 px-2 py-0.5"
                    >Ctrl+Enter Guardar</span
                >
                <span class="rounded-full bg-slate-100 px-2 py-0.5"
                    >Del Eliminar fila</span
                >
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import { Search, X, Loader2, ImageOff } from "lucide-vue-next";

const props = defineProps({
    modelValue: { type: String, default: "" },
    resultados: { type: Array, default: () => [] },
    buscando: { type: Boolean, default: false },
    dropdown: { type: Boolean, default: false },
    cursor: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
    formatPrecio: { type: Function, required: true },
});

const emit = defineEmits([
    "update:modelValue",
    "input",
    "enter",
    "moveCursor",
    "selectCursor",
    "close",
    "clear",
    "selectItem",
    "hoverItem",
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
