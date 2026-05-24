<template>
    <div class="overflow-visible rounded-xl border border-slate-200 bg-white">
        <!-- Header -->
        <div
            class="grid grid-cols-[1fr_68px_110px_170px_110px_36px] gap-2 rounded-t-xl border-b border-slate-100 bg-slate-50 px-4 py-2.5"
        >
            <span
                class="text-[10px] font-semibold uppercase tracking-widest text-slate-400"
            >
                Producto
            </span>

            <span
                class="text-center text-[10px] font-semibold uppercase tracking-widest text-slate-400"
            >
                Stock
            </span>

            <span
                class="text-center text-[10px] font-semibold uppercase tracking-widest text-slate-400"
            >
                Cantidad
            </span>

            <div>
                <span
                    class="text-[10px] font-semibold uppercase tracking-widest text-slate-400"
                >
                    Precio
                </span>
                <span class="ml-1 text-[10px] text-slate-300">
                    · doble clic = lista
                </span>
            </div>

            <span
                class="text-right text-[10px] font-semibold uppercase tracking-widest text-slate-400"
            >
                Subtotal
            </span>

            <span></span>
        </div>

        <!-- Empty -->
        <div v-if="detalles.length === 0" class="p-14 text-center">
            <div
                class="mx-auto mb-3 inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50"
            >
                <ShoppingBag class="h-6 w-6 text-slate-300" />
            </div>

            <p class="text-[14px] font-medium text-slate-500">
                Sin artículos todavía
            </p>

            <p class="mt-1 text-[12px] text-slate-400">
                Usa el buscador o escanea un código de barras
            </p>
        </div>

        <!-- Rows -->
        <div v-else>
            <div
                v-for="(det, idx) in detalles"
                :key="det._key"
                class="grid grid-cols-[1fr_68px_110px_170px_110px_36px] items-center gap-2 border-b border-slate-50 px-4 py-2.5"
                :class="
                    det.cantidad > det.stock_disponible
                        ? 'bg-red-50/20'
                        : 'bg-white'
                "
            >
                <!-- Producto -->
                <div class="flex min-w-0 items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50"
                    >
                        <img
                            v-if="det.imagen_url"
                            :src="det.imagen_url"
                            :alt="det.nombre"
                            class="h-full w-full object-contain"
                        />
                        <ImageOff v-else class="h-4 w-4 text-slate-300" />
                    </div>

                    <div class="min-w-0">
                        <div
                            class="truncate text-[13px] font-medium text-slate-900"
                        >
                            {{ det.nombre }}
                        </div>

                        <div
                            class="mt-0.5 font-mono text-[11px] text-slate-400"
                        >
                            {{ det.codigo }}
                            <span
                                v-if="det.nombre_variante"
                                class="text-emerald-600"
                            >
                                — {{ det.nombre_variante }}
                            </span>
                        </div>

                        <!-- Exhibido -->
                        <div v-if="det.inventario_exhibido" class="mt-1.5">
                            <label
                                class="inline-flex cursor-pointer select-none items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100"
                                :class="
                                    det.era_exhibido
                                        ? 'ring-4 ring-amber-100'
                                        : ''
                                "
                            >
                                <input
                                    v-model="det.era_exhibido"
                                    type="checkbox"
                                    class="sr-only"
                                />

                                <div
                                    class="flex h-5 w-5 items-center justify-center rounded-full"
                                    :class="
                                        det.era_exhibido
                                            ? 'bg-amber-600 text-white'
                                            : 'bg-white text-amber-700 ring-1 ring-amber-200'
                                    "
                                >
                                    <component
                                        :is="det.era_exhibido ? Check : Eye"
                                        class="h-3.5 w-3.5"
                                    />
                                </div>

                                <span>
                                    {{
                                        det.era_exhibido
                                            ? "VENDIENDO EXHIBIDO"
                                            : "Marcar como exhibido"
                                    }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Stock -->
                <div class="text-center">
                    <span
                        class="font-mono text-[12px] font-medium"
                        :class="
                            det.stock_disponible <= 0
                                ? 'text-red-500'
                                : det.stock_disponible <= 5
                                  ? 'text-amber-600'
                                  : 'text-slate-500'
                        "
                    >
                        {{ det.stock_disponible }}
                    </span>
                </div>

                <!-- Cantidad -->
                <div>
                    <div
                        class="flex items-stretch overflow-hidden rounded-lg border bg-white"
                        :class="
                            det.cantidad > det.stock_disponible
                                ? 'border-red-300'
                                : 'border-slate-200'
                        "
                    >
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-2.5 text-slate-400 transition hover:bg-slate-50 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="det.cantidad <= 1 || det.cantidad_fija"
                            @click="
                                det.cantidad > 1 &&
                                !det.cantidad_fija &&
                                (det.cantidad--, $emit('recalcularLinea', idx))
                            "
                            title="Restar"
                        >
                            <Minus class="h-4 w-4" />
                        </button>

                        <input
                            v-model="det.cantidad"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            class="w-12 border-x border-slate-200 bg-white px-1 py-1.5 text-center font-mono text-[13px] outline-none focus:bg-slate-50/40 disabled:bg-slate-50 disabled:text-slate-400"
                            :disabled="det.cantidad_fija"
                            @input="$emit('recalcularLinea', idx)"
                        />

                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-2.5 text-slate-400 transition hover:bg-slate-50 hover:text-emerald-600 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="det.cantidad_fija"
                            @click="
                                !det.cantidad_fija &&
                                (det.cantidad++, $emit('recalcularLinea', idx))
                            "
                            title="Sumar"
                        >
                            <Plus class="h-4 w-4" />
                        </button>
                    </div>

                    <div
                        v-if="det.cantidad > det.stock_disponible"
                        class="mt-1 text-center text-[10px] text-red-500"
                    >
                        Excede stock
                    </div>
                </div>

                <!-- Precio + Popover -->
                <div class="relative">
                    <div
                        class="group flex cursor-pointer overflow-hidden rounded-lg border transition"
                        :class="
                            popoverIdx === idx
                                ? 'border-emerald-600 bg-emerald-50 ring-4 ring-emerald-100'
                                : 'border-slate-200 bg-white hover:border-slate-300'
                        "
                        @dblclick.stop="$emit('abrirPopoverPrecio', idx, $event)"
                    >
                        <span
                            class="flex items-center border-r border-slate-200 bg-slate-50 px-2.5 py-1.5 font-mono text-[12px] text-slate-400"
                        >
                            $
                        </span>

                        <input
                            :value="det.precio_venta"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full bg-transparent px-2.5 py-1.5 text-right font-mono text-[13px] outline-none"
                            @dblclick.stop="$emit('abrirPopoverPrecio', idx, $event)"
                            @focus="$emit('guardarPrecioAnterior', idx)"
                            @blur="$emit('precioBlur', idx, $event)"
                        />

                        <span
                            v-if="det.motivo_precio"
                            class="flex items-center border-l border-orange-200 bg-orange-50 px-2 py-1.5 text-[10px] font-bold text-orange-700"
                            :title="'Motivo: ' + det.motivo_precio"
                        >
                            Manual
                        </span>

                        <span
                            v-else-if="det.precio_lista_sel"
                            class="flex items-center border-l border-green-200 bg-green-50 px-2 py-1.5 text-[10px] font-bold tracking-wide text-green-600"
                        >
                            {{ det.precio_lista_sel }}
                        </span>

                        <span
                            v-else
                            class="flex items-center px-2 py-1.5 text-slate-200"
                        >
                            <Clock3 class="h-3.5 w-3.5" />
                        </span>
                    </div>

                    <div
                        class="pointer-events-none absolute -bottom-4 left-1/2 -translate-x-1/2 text-[10px] text-slate-400 opacity-0 transition group-hover:opacity-100"
                    >
                        doble clic = lista de precios
                    </div>

                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1 scale-95"
                        enter-to-class="opacity-100 translate-y-0 scale-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0 scale-100"
                        leave-to-class="opacity-0 -translate-y-0.5 scale-95"
                    >
                        <div
                            v-if="popoverIdx === idx"
                            class="absolute z-50 min-w-[190px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_8px_30px_rgba(0,0,0,0.12),0_2px_8px_rgba(0,0,0,0.06)]"
                            :style="popoverStyle"
                            @click.stop
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5"
                            >
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-widest text-slate-400"
                                >
                                    Lista de precios
                                </span>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center text-slate-300 transition hover:text-slate-500"
                                    @click.stop="$emit('cerrarPopover')"
                                    title="Cerrar"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <button
                                v-for="p in preciosValidos(det)"
                                :key="p.clave"
                                type="button"
                                class="flex w-full items-center justify-between border-b border-slate-50 px-4 py-2.5 text-left transition last:border-b-0 hover:bg-emerald-50"
                                :class="
                                    det.precio_lista_sel === p.clave
                                        ? 'bg-emerald-50'
                                        : 'bg-white'
                                "
                                @click="$emit('aplicarPrecio', idx, p)"
                            >
                                <span
                                    class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500"
                                    :class="
                                        det.precio_lista_sel === p.clave
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : ''
                                    "
                                >
                                    {{ p.clave }}
                                </span>

                                <span
                                    class="font-mono text-[13px] font-medium text-slate-800"
                                    :class="
                                        det.precio_lista_sel === p.clave
                                            ? 'text-emerald-700'
                                            : ''
                                    "
                                >
                                    {{ formatPrecio(p.valor) }}
                                </span>
                            </button>

                            <div
                                v-if="preciosValidos(det).length === 0"
                                class="px-4 py-4 text-center text-[12px] text-slate-400"
                            >
                                Sin precios de lista disponibles
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Subtotal -->
                <div
                    class="text-right font-mono text-[13px] font-medium text-slate-700"
                >
                    {{ formatPrecio(det.subtotal) }}
                </div>

                <!-- Eliminar -->
                <div class="flex justify-center">
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent text-slate-300 transition hover:border-red-200 hover:bg-red-50 hover:text-red-500"
                        @click="$emit('quitarDetalle', idx)"
                        title="Eliminar"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3 md:flex-row md:items-center md:justify-end"
            >
                <div class="flex flex-wrap items-center justify-end gap-4">
                    <span class="text-[13px] text-slate-500">
                        Subtotal:
                        <span class="ml-1 font-mono text-slate-700">
                            {{ formatPrecio(subtotal) }}
                        </span>
                    </span>

                    <div class="flex items-center gap-2">
                        <label
                            class="text-[13px] font-medium text-slate-500"
                            for="descuento-footer"
                        >
                            Descuento:
                        </label>

                        <div
                            class="flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
                        >
                            <span
                                class="border-r border-slate-200 bg-slate-50 px-2.5 py-2 text-[12px] font-mono text-slate-400"
                            >
                                $
                            </span>

                            <input
                                id="descuento-footer"
                                :value="formDescuento"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                class="w-28 bg-white px-2.5 py-2 text-right font-mono text-[13px] outline-none"
                                placeholder="0.00"
                                @input="$emit('update:descuento', $event.target.value)"
                            />
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100 px-4 py-2"
                    >
                        <span
                            class="text-[13px] font-semibold text-emerald-900/80"
                        >
                            Total:
                        </span>
                        <span
                            class="font-mono text-[17px] font-medium tracking-tight text-emerald-600"
                        >
                            {{ formatPrecio(total) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerta excedido -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-0.5"
        >
            <div
                v-if="hayExcedido"
                class="flex items-center gap-2 border-t border-red-200 bg-red-50 px-4 py-3"
            >
                <AlertTriangle class="h-4 w-4 text-red-500" />
                <p class="text-[13px] text-red-600">
                    Uno o más artículos superan el stock disponible. Ajusta las
                    cantidades antes de continuar.
                </p>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import {
    ShoppingBag,
    ImageOff,
    Trash2,
    AlertTriangle,
    Clock3,
    X,
    Plus,
    Minus,
    Check,
    Eye,
} from "lucide-vue-next";

defineProps({
    detalles: { type: Array, default: () => [] },
    popoverIdx: { type: [Number, null], default: null },
    popoverStyle: { type: Object, default: () => ({}) },
    hayExcedido: { type: Boolean, default: false },

    subtotal: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    formDescuento: { type: [Number, String], default: 0 },

    formatPrecio: { type: Function, required: true },
    preciosValidos: { type: Function, required: true },
});

defineEmits([
    "recalcularLinea",
    "quitarDetalle",
    "abrirPopoverPrecio",
    "cerrarPopover",
    "aplicarPrecio",
    "guardarPrecioAnterior",
    "precioBlur",
    "update:descuento",
]);
</script>