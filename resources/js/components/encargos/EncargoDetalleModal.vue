<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="$emit('close')">
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-black text-slate-900">
                        {{ pedido?.folio || 'Detalle' }}
                        <span class="ml-2 text-sm font-semibold capitalize text-slate-400">{{ pedido?.tipo }}</span>
                    </h2>
                    <p class="text-sm text-slate-500">{{ pedido?.cliente?.nombre || 'Sin cliente' }}</p>
                </div>
                <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="$emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div v-if="cargando" class="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" />
                Cargando detalle
            </div>

            <div v-else-if="data" class="max-h-[70vh] space-y-5 overflow-y-auto p-5">
                <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Estado</p>
                        <EncargoEstadoBadge :estado="data.estado" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Fecha promesa</p>
                        <p class="font-bold text-slate-900">{{ formatFechaPura(data.fecha_promesa) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Registrado</p>
                        <p class="font-bold text-slate-900">{{ formatFecha(data.created_at) }}</p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-black text-slate-900">Artículos</h3>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Descripción</th>
                                    <th class="px-3 py-2 text-center">Cant.</th>
                                    <th class="px-3 py-2 text-right">Precio</th>
                                    <th class="px-3 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="detalle in data.detalles" :key="detalle.id">
                                    <td class="px-3 py-2">
                                        <p class="font-bold text-slate-900">{{ detalle.descripcion }}</p>
                                        <p v-if="detalle.producto?.nombre" class="text-xs text-slate-400">{{ detalle.producto.nombre }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-center text-slate-700">{{ detalle.cantidad }}</td>
                                    <td class="px-3 py-2 text-right text-slate-700">{{ money(detalle.precio_acordado) }}</td>
                                    <td class="px-3 py-2 text-right font-black text-slate-900">{{ money(detalle.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="flex justify-between text-sm"><span class="text-slate-500">Total</span><span class="font-black text-slate-900">{{ money(data.subtotal) }}</span></div>
                    <div class="mt-1 flex justify-between text-sm"><span class="text-slate-500">Anticipo pagado</span><span class="font-bold text-emerald-700">{{ money(data.anticipo) }}</span></div>
                    <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 text-sm"><span class="font-bold text-slate-700">Saldo pendiente</span><span class="font-black text-slate-900">{{ money(data.saldo_pendiente) }}</span></div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-black text-slate-900">Historial de pagos</h3>
                    <div v-if="!data.saldos?.length" class="rounded-xl border border-slate-100 p-4 text-center text-sm text-slate-400">Sin pagos registrados</div>
                    <div v-else class="space-y-2">
                        <div v-for="mov in data.saldos" :key="mov.id" class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-sm">
                            <div>
                                <p class="font-bold text-slate-900">{{ mov.concepto }}</p>
                                <p class="text-xs text-slate-400">{{ formatFechaHora(mov.created_at) }} <span v-if="mov.forma_pago">· {{ mov.forma_pago }}</span></p>
                            </div>
                            <span class="font-black" :class="['abono', 'devolucion', 'ajuste'].includes(mov.tipo) ? 'text-emerald-700' : 'text-red-600'">
                                {{ ['abono', 'devolucion', 'ajuste'].includes(mov.tipo) ? '+' : '-' }}{{ money(mov.monto) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="data.notas">
                    <h3 class="mb-1 text-sm font-black text-slate-900">Notas</h3>
                    <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ data.notas }}</p>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="$emit('close')">Cerrar</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Loader2, X } from 'lucide-vue-next'
import EncargoEstadoBadge from './EncargoEstadoBadge.vue'

defineProps({
    visible: { type: Boolean, default: false },
    cargando: { type: Boolean, default: false },
    pedido: { type: Object, default: null },
    data: { type: Object, default: null },
})

defineEmits(['close'])

function money(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
}
function formatFecha(fecha) {
    if (!fecha) return '—'
    return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
// fecha_promesa es una fecha pura (sin hora); agregar T12:00:00 evita que
// new Date() la interprete como medianoche UTC y se corra un día al convertir a hora local.
function formatFechaPura(fecha) {
    if (!fecha) return '—'
    return new Date(`${String(fecha).slice(0, 10)}T12:00:00`).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatFechaHora(fecha) {
    if (!fecha) return '—'
    return new Date(fecha).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
