<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <!-- Vacío -->
        <div v-if="isEmpty" class="p-10 text-center">
            <div
                class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
            >
                <ClipboardList class="h-6 w-6" />
            </div>
            <p class="text-sm font-medium text-slate-600">
                Sin artículos todavía
            </p>
            <p class="mt-1 text-xs text-slate-400">
                Usa el buscador para agregar productos
            </p>
        </div>

        <!-- Contenido -->
        <div v-else>
            <!-- Desktop / Tablet: Tabla -->
            <div v-if="isDesktop">
                <div class="max-h-[420px] overflow-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead
                            class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400"
                        >
                            <tr>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    Producto / Variante
                                </th>
                                <th
                                    class="w-32 border-b border-slate-200 px-3 py-3 text-right"
                                >
                                    Cantidad
                                </th>
                                <th
                                    class="w-36 border-b border-slate-200 px-3 py-3 text-right"
                                >
                                    Compra
                                </th>
                                <th
                                    class="w-36 border-b border-slate-200 px-3 py-3 text-right"
                                >
                                    Venta
                                </th>
                                <th
                                    class="w-32 border-b border-slate-200 px-3 py-3 text-right"
                                >
                                    Subtotal
                                </th>
                                <th
                                    class="w-12 border-b border-slate-200 px-3 py-3"
                                ></th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(det, idx) in detalles"
                                :key="det._key"
                                class="border-b border-slate-100 hover:bg-slate-50/60"
                            >
                                <!-- Producto -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                                        >
                                            <img
                                                v-if="det.imagen_url"
                                                :src="det.imagen_url"
                                                class="h-full w-full object-contain"
                                                :alt="det.nombre"
                                            />
                                            <ImageOff
                                                v-else
                                                class="h-4 w-4 text-slate-300"
                                            />
                                        </div>

                                        <div class="min-w-0">
                                            <div
                                                class="flex min-w-0 items-center gap-2"
                                            >
                                                <p
                                                    class="min-w-0 truncate text-sm font-semibold text-slate-900"
                                                >
                                                    {{ det.nombre }}
                                                </p>

                                                <span
                                                    v-if="det.tiene_series"
                                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                                                >
                                                    <Smartphone
                                                        class="h-3 w-3"
                                                    />
                                                    {{ det.imeis?.length ?? 0 }}
                                                    IMEI
                                                </span>
                                                <span
                                                    v-if="det.pedido_detalle_ids?.length"
                                                    class="inline-flex shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                                >
                                                    {{ det.pedido_detalle_ids.length }} pedido(s)
                                                </span>
                                            </div>

                                            <p
                                                class="mt-0.5 text-xs text-slate-400"
                                            >
                                                <span class="font-mono">{{
                                                    det.codigo
                                                }}</span>
                                                <span
                                                    v-if="det.nombre_variante"
                                                    class="text-violet-600"
                                                >
                                                    — {{ det.nombre_variante }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Cantidad -->
                                <td class="px-3 py-3">
                                    <div
                                        v-if="det.tiene_series"
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <span
                                            class="font-mono text-sm font-semibold text-slate-800"
                                        >
                                            {{ det.cantidad }}
                                        </span>
                                        <button
                                            type="button"
                                            @click="emit('editar-imeis', idx)"
                                            class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50"
                                            title="Editar IMEIs y cantidad"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <input
                                        v-else
                                        v-model="det.cantidad"
                                        type="number"
                                        min="1"
                                        step="1"
                                        inputmode="numeric"
                                        @input="emit('recalcular', idx)"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-2 text-right font-mono text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                    />
                                </td>

                                <!-- Precio compra -->
                                <td class="px-3 py-3">
                                    <div class="relative min-w-[140px]">
                                        <span
                                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500"
                                        >
                                            $
                                        </span>

                                        <input
                                            :value="det.precio_compra ?? ''"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            @input="onPrecioCompra($event, idx)"
                                            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-7 pr-3 text-right font-mono text-sm tabular-nums outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                        />
                                    </div>
                                </td>

                                <!-- Precio venta -->
                                <td class="px-3 py-3">
                                    <div class="relative min-w-[140px]">
                                        <span
                                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-emerald-600"
                                        >
                                            $
                                        </span>

                                        <input
                                            :value="det.precio_venta ?? ''"
                                            type="text"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            @input="onPrecioVenta($event, idx)"
                                            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-7 pr-3 text-right font-mono text-sm tabular-nums outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                        />
                                    </div>
                                </td>

                                <!-- Subtotal -->
                                <td
                                    class="px-3 py-3 text-right font-mono text-sm font-semibold text-slate-800"
                                >
                                    {{ formatPrecio(det.subtotal) }}
                                </td>

                                <!-- Acciones -->
                                <td class="px-3 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="emit('quitar', idx)"
                                        class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600"
                                        title="Quitar"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>

                        <tfoot class="sticky bottom-0 bg-slate-50">
                            <tr>
                                <td
                                    colspan="4"
                                    class="px-4 py-3 text-right text-sm font-semibold text-slate-700"
                                >
                                    Total
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-mono text-base font-extrabold text-violet-700"
                                >
                                    {{ formatPrecio(total) }}
                                </td>
                                <td class="px-3 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Mobile: Tarjetas -->
            <div v-else>
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="(det, idx) in detalles"
                        :key="det._key"
                        class="p-4"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                            >
                                <img
                                    v-if="det.imagen_url"
                                    :src="det.imagen_url"
                                    class="h-full w-full object-contain"
                                    :alt="det.nombre"
                                />
                                <ImageOff
                                    v-else
                                    class="h-4 w-4 text-slate-300"
                                />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="min-w-0 truncate text-sm font-semibold text-slate-900"
                                    >
                                        {{ det.nombre }}
                                    </p>
                                    <span
                                        v-if="det.tiene_series"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                                    >
                                        <Smartphone class="h-3 w-3" />
                                        {{ det.imeis?.length ?? 0 }} IMEI
                                    </span>
                                    <span
                                        v-if="det.pedido_detalle_ids?.length"
                                        class="inline-flex shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                                    >
                                        {{ det.pedido_detalle_ids.length }} pedido(s)
                                    </span>
                                </div>

                                <p class="mt-0.5 text-xs text-slate-400">
                                    <span class="font-mono">{{
                                        det.codigo
                                    }}</span>
                                    <span
                                        v-if="det.nombre_variante"
                                        class="text-violet-600"
                                    >
                                        — {{ det.nombre_variante }}
                                    </span>
                                </p>

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <!-- Cantidad -->
                                    <div>
                                        <p
                                            class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400"
                                        >
                                            Cantidad
                                        </p>

                                        <div
                                            v-if="det.tiene_series"
                                            class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2"
                                        >
                                            <span
                                                class="font-mono text-sm font-semibold text-slate-800"
                                            >
                                                {{ det.cantidad }}
                                            </span>
                                            <button
                                                type="button"
                                                @click="
                                                    emit('editar-imeis', idx)
                                                "
                                                class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50"
                                                title="Editar IMEIs y cantidad"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                        </div>

                                        <input
                                            v-else
                                            v-model="det.cantidad"
                                            type="number"
                                            min="1"
                                            step="1"
                                            inputmode="numeric"
                                            @input="emit('recalcular', idx)"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-right font-mono text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                        />
                                    </div>

                                    <!-- Subtotal -->
                                    <div>
                                        <p
                                            class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400"
                                        >
                                            Subtotal
                                        </p>
                                        <div
                                            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-right font-mono text-sm font-bold text-slate-800"
                                        >
                                            {{ formatPrecio(det.subtotal) }}
                                        </div>
                                    </div>

                                    <!-- Compra -->
                                    <div>
                                        <p
                                            class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400"
                                        >
                                            Compra
                                        </p>
                                        <div
                                            class="flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100"
                                        >
                                            <span
                                                class="border-r border-slate-200 bg-slate-50 px-2 py-2 text-xs text-slate-500"
                                            >
                                                $
                                            </span>
                                            <input
                                                v-model="det.precio_compra"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                @input="emit('recalcular', idx)"
                                                class="w-full px-2 py-2 text-right font-mono text-sm outline-none"
                                            />
                                        </div>
                                    </div>

                                    <!-- Venta -->
                                    <div>
                                        <p
                                            class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400"
                                        >
                                            Venta
                                        </p>
                                        <div
                                            class="flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-100"
                                        >
                                            <span
                                                class="border-r border-slate-200 bg-emerald-50 px-2 py-2 text-xs text-emerald-600"
                                            >
                                                $
                                            </span>
                                            <input
                                                v-model="det.precio_venta"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-full px-2 py-2 text-right font-mono text-sm outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 flex items-center justify-between"
                                >
                                    <button
                                        type="button"
                                        @click="emit('quitar', idx)"
                                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Quitar
                                    </button>

                                    <div class="text-right">
                                        <p
                                            class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"
                                        >
                                            Total
                                        </p>
                                        <p
                                            class="font-mono text-base font-extrabold text-violet-700"
                                        >
                                            {{ formatPrecio(total) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700"
                            >Total</span
                        >
                        <span
                            class="font-mono text-lg font-extrabold text-violet-700"
                        >
                            {{ formatPrecio(total) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import {
    ClipboardList,
    ImageOff,
    Trash2,
    Pencil,
    Smartphone,
} from "lucide-vue-next";

const props = defineProps({
    detalles: { type: Array, required: true },
    total: { type: Number, default: 0 },
    formatPrecio: { type: Function, required: true },
});

const emit = defineEmits(["recalcular", "quitar", "editar-imeis"]);

const isEmpty = computed(() => !props.detalles?.length);

// --- breakpoint: md (Tailwind) => min-width: 768px ---
const isDesktop = ref(false);
let mql;

const sync = () => {
    isDesktop.value = !!mql?.matches;
};

onMounted(() => {
    mql = window.matchMedia("(min-width: 768px)");
    sync();
    // Safari viejo usa addListener/removeListener
    if (mql.addEventListener) mql.addEventListener("change", sync);
    else mql.addListener(sync);
});

onBeforeUnmount(() => {
    if (!mql) return;
    if (mql.removeEventListener) mql.removeEventListener("change", sync);
    else mql.removeListener(sync);
});

function parseMoney(raw) {
    // deja solo números, punto y coma; luego normaliza coma->punto
    const s = String(raw ?? "")
        .replace(/[^\d.,]/g, "")
        .replace(",", ".");
    return s;
}

function onPrecioCompra(e, idx) {
    const v = parseMoney(e.target.value);
    props.detalles[idx].precio_compra = v;
    emit("recalcular", idx);
}

function onPrecioVenta(e, idx) {
    const v = parseMoney(e.target.value);
    props.detalles[idx].precio_venta = v;
    emit("recalcular", idx);
}
</script>
