<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Artículos</h3>
                <p class="text-xs text-slate-500">{{ detalles.length }} agregado(s)</p>
            </div>
            <slot name="actions" />
        </div>

        <div v-if="detalles.length" class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="min-w-260px px-3 py-2">Producto</th>
                        <th class="w-24 px-3 py-2 text-right">Cant.</th>
                        <th class="w-32 px-3 py-2 text-right">Precio</th>
                        <th class="w-32 px-3 py-2 text-right">Importe</th>
                        <th class="w-12 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(detalle, index) in detalles" :key="detalle.uid" class="align-middle hover:bg-slate-50/70">
                        <td class="px-3 py-2">
                            <p class="font-bold text-slate-800">{{ detalle.descripcion }}</p>
                            <p v-if="metaDetalle(detalle)" class="mt-1 text-xs text-slate-500">{{ metaDetalle(detalle) }}</p>
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ detalle.variante_id ? 'Variante vinculada' : detalle.producto_id ? 'Producto base vinculado' : 'Sin vínculo' }}
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <input
                                :value="detalle.cantidad"
                                type="number"
                                min="1"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @input="actualizar(index, 'cantidad', Number($event.target.value || 1))"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                :value="detalle.precio_acordado"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @input="actualizar(index, 'precio_acordado', Number($event.target.value || 0))"
                            />
                        </td>
                        <td class="px-3 py-2 text-right font-black text-slate-900">
                            {{ money(Number(detalle.cantidad || 0) * Number(detalle.precio_acordado || 0)) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button
                                type="button"
                                class="rounded-xl p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                title="Quitar producto"
                                @click="$emit('remove', index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="px-4 py-10 text-center">
            <p class="text-sm font-medium text-slate-500">Busca y selecciona productos para agregarlos.</p>
        </div>
    </div>
</template>

<script setup>
import { Trash2 } from 'lucide-vue-next'

const props = defineProps({
    detalles: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:detalles', 'remove'])

function actualizar(index, key, value) {
    const copia = props.detalles.map((item) => ({ ...item }))
    copia[index][key] = value
    emit('update:detalles', copia)
}

function metaDetalle(detalle) {
    return [detalle.marca_texto, detalle.modelo_texto, detalle.color_texto, detalle.talla_texto].filter(Boolean).join(' / ')
}

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
}
</script>
