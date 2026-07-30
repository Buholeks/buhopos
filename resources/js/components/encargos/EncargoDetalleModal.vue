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
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Descripción</th>
                                    <th class="px-3 py-2 text-center">Cant.</th>
                                    <th class="px-3 py-2 text-right">Precio</th>
                                    <th class="px-3 py-2 text-right">Subtotal</th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="detalle in data.detalles" :key="detalle.id">
                                    <td class="px-3 py-2">
                                        <p class="font-bold text-slate-900">{{ detalle.descripcion }}</p>
                                        <p v-if="detalle.producto?.nombre" class="text-xs text-slate-400">{{ detalle.producto.nombre }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-center text-slate-700">{{ detalle.cantidad }}</td>
                                    <td class="px-3 py-2 text-right text-slate-700">
                                        <div v-if="editandoPrecioId === detalle.id" class="flex min-w-40 items-center justify-end gap-1">
                                            <span class="text-slate-400">$</span>
                                            <input
                                                v-model="precioEditado"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-24 rounded-lg border border-emerald-300 px-2 py-1 text-right outline-none focus:ring-2 focus:ring-emerald-100"
                                                @keyup.enter="guardarPrecio(detalle)"
                                                @keyup.esc="cancelarEdicionPrecio"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-emerald-600 hover:bg-emerald-50 disabled:opacity-50"
                                                title="Guardar precio"
                                                :disabled="guardandoPrecioId === detalle.id"
                                                @click="guardarPrecio(detalle)"
                                            >
                                                <Loader2 v-if="guardandoPrecioId === detalle.id" class="h-4 w-4 animate-spin" />
                                                <Check v-else class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" title="Cancelar edición" @click="cancelarEdicionPrecio">
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <span v-else>{{ money(detalle.precio_acordado) }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-black text-slate-900">{{ money(detalle.subtotal) }}</td>
                                    <td class="px-3 py-2">
                                        <EncargoEstadoBadge :estado="detalle.estado" />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            v-if="puedeEditarPrecio(detalle)"
                                            type="button"
                                            class="rounded-lg p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
                                            title="Editar precio acordado"
                                            @click="iniciarEdicionPrecio(detalle)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="puedeCancelarDetalle(detalle)"
                                            type="button"
                                            class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            title="Cancelar articulo"
                                            @click="$emit('cancelar-detalle', detalle)"
                                        >
                                            <XCircle class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="flex justify-between text-sm"><span class="text-slate-500">Total</span><span class="font-black text-slate-900">{{ money(subtotalVigente) }}</span></div>
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
                            <div class="flex items-center gap-2">
                                <span class="font-black" :class="['abono', 'devolucion', 'ajuste'].includes(mov.tipo) ? 'text-emerald-700' : 'text-red-600'">
                                    {{ ['abono', 'devolucion', 'ajuste'].includes(mov.tipo) ? '+' : '-' }}{{ money(mov.monto) }}
                                </span>
                                <button
                                    v-if="mov.tipo === 'abono' && puedeEliminarAbono"
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                                    title="Eliminar abono"
                                    :disabled="eliminandoAbonoId === mov.id"
                                    @click="$emit('eliminar-abono', mov.id)"
                                >
                                    <Loader2 v-if="eliminandoAbonoId === mov.id" class="h-4 w-4 animate-spin" />
                                    <Trash2 v-else class="h-4 w-4" />
                                </button>
                            </div>
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
import { computed, ref, watch } from 'vue'
import { Check, Loader2, Pencil, Trash2, X, XCircle } from 'lucide-vue-next'
import EncargoEstadoBadge from './EncargoEstadoBadge.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const props = defineProps({
    visible: { type: Boolean, default: false },
    cargando: { type: Boolean, default: false },
    pedido: { type: Object, default: null },
    data: { type: Object, default: null },
    eliminandoAbonoId: { type: [Number, String], default: null },
    guardandoPrecioId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close', 'eliminar-abono', 'cancelar-detalle', 'editar-precio'])
const editandoPrecioId = ref(null)
const precioEditado = ref('')

watch(() => props.data, () => cancelarEdicionPrecio())

const puedeEliminarAbono = computed(() => {
    if (!auth.can('pedidos.crear')) return false
    return !['entregado', 'devuelto', 'cancelado', 'vencido'].includes(props.data?.estado)
})

const subtotalVigente = computed(() => {
    if (!Array.isArray(props.data?.detalles)) return props.data?.subtotal
    return props.data.detalles
        .filter((detalle) => detalle?.estado !== 'cancelado')
        .reduce((total, detalle) => total + Number(detalle?.subtotal || 0), 0)
})

function puedeCancelarDetalle(detalle) {
    if (!auth.can('pedidos.cancelar')) return false
    if (['entregado', 'devuelto', 'cancelado'].includes(props.data?.estado)) return false
    return !['entregado', 'devuelto', 'cancelado'].includes(detalle?.estado)
}

function puedeEditarPrecio(detalle) {
    if (!auth.can('pedidos.crear') || props.data?.tipo !== 'pedido') return false
    if (['entregado', 'devuelto', 'cancelado', 'vencido', 'parcial'].includes(props.data?.estado)) return false
    return !['entregado', 'devuelto', 'cancelado'].includes(detalle?.estado)
}

function iniciarEdicionPrecio(detalle) {
    editandoPrecioId.value = detalle.id
    precioEditado.value = Number(detalle.precio_acordado || 0).toFixed(2)
}

function cancelarEdicionPrecio() {
    editandoPrecioId.value = null
    precioEditado.value = ''
}

function guardarPrecio(detalle) {
    const precio = Number(precioEditado.value)
    if (!Number.isFinite(precio) || precio < 0) return
    emit('editar-precio', { detalle, precio })
}

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
