<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div v-if="cargando" class="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin" />
            Cargando registros
        </div>

        <div v-else-if="pedidos.length === 0" class="p-10 text-center text-sm text-slate-500">
            No hay registros.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Folio / Cliente</th>
                        <th class="px-4 py-3">Artículos</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Anticipo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="pedido in pedidos" :key="pedido.id" class="align-top hover:bg-slate-50/70">
                        <td class="px-4 py-3">
                            <p class="font-black text-slate-900">{{ pedido.folio }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-700">
                                {{ pedido.cliente?.nombre || 'Sin cliente' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ pedido.cliente?.telefono || '' }}
                            </p>
                            <p class="mt-1 text-xs capitalize text-slate-400">
                                {{ pedido.tipo }}
                            </p>
                        </td>

                        <td class="px-4 py-3">
                            <div>
                                <p class="font-bold text-slate-900">
                                    {{ pedido.detalles?.length || 0 }}
                                    {{ (pedido.detalles?.length || 0) === 1 ? 'artículo' : 'artículos' }}
                                </p>

                                <p v-if="pedido.detalles?.length" class="mt-1 max-w-md truncate text-xs text-slate-500">
                                    {{ pedido.detalles[0].descripcion }}
                                </p>

                                <p v-if="(pedido.detalles?.length || 0) > 1" class="mt-1 text-xs font-bold text-emerald-600">
                                    +{{ pedido.detalles.length - 1 }} más
                                </p>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-right font-black text-slate-900">
                            {{ money(subtotalVigente(pedido)) }}
                        </td>

                        <td class="px-4 py-3 text-right">
                            <p class="font-black text-emerald-700">{{ money(pedido.anticipo) }}</p>
                            <p v-if="pedidoCerrado(pedido)" class="text-xs font-bold text-emerald-700">
                                Liquidado
                            </p>
                            <p v-else class="text-xs text-slate-500">
                                Debe {{ money(pedido.saldo_pendiente) }}
                            </p>
                        </td>

                        <td class="px-4 py-3">
                            <EncargoEstadoBadge :estado="pedido.estado" />
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex flex-col items-end gap-2">
                                <div class="flex gap-1.5">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 hover:text-slate-700"
                                        title="Ver detalle"
                                        @click="$emit('detalle', pedido)"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>

                                    <button
                                        v-if="!pedidoCerrado(pedido) && auth.can('pedidos.cancelar')"
                                        type="button"
                                        class="rounded-xl border border-red-200 p-2 text-red-500 hover:bg-red-50 hover:text-red-700"
                                        title="Cancelar"
                                        @click="$emit('cancelar', pedido)"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </button>
                                </div>

                                <div v-if="puedeAbonar(pedido)" class="flex w-full flex-col items-end gap-1.5">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <input
                                            v-model.number="abonos[pedido.id].monto"
                                            type="number"
                                            min="0"
                                            :max="pedido.saldo_pendiente"
                                            step="0.01"
                                            class="w-20 rounded-xl border border-slate-200 px-2 py-1.5 text-right text-sm outline-none focus:border-emerald-500"
                                            placeholder="0.00"
                                        />

                                        <select
                                            v-model="abonos[pedido.id].forma_pago"
                                            class="rounded-xl border border-slate-200 px-2 py-1.5 text-xs outline-none focus:border-emerald-500"
                                        >
                                            <option value="efectivo">Efec.</option>
                                            <option value="tarjeta">Tarj.</option>
                                            <option value="transferencia">Trans.</option>
                                        </select>

                                        <button
                                            type="button"
                                            class="rounded-xl border border-emerald-200 px-2 py-1.5 text-xs font-black text-emerald-700 hover:bg-emerald-50"
                                            @click="$emit('abonar', pedido)"
                                        >
                                            Abonar
                                        </button>
                                    </div>

                                    <select
                                        v-if="abonos[pedido.id].forma_pago === 'transferencia'"
                                        v-model="abonos[pedido.id].cuenta_bancaria_id"
                                        class="w-full rounded-xl border border-slate-200 px-2 py-1.5 text-xs outline-none focus:border-emerald-500"
                                    >
                                        <option value="" disabled>Cuenta bancaria…</option>
                                        <option v-for="c in cuentasBancarias" :key="c.id" :value="c.id">
                                            {{ c.nombre }}<template v-if="c.banco"> ({{ c.banco }})</template>
                                        </option>
                                    </select>

                                    <select
                                        v-if="abonos[pedido.id].forma_pago === 'tarjeta'"
                                        v-model="abonos[pedido.id].terminal_pago_id"
                                        class="w-full rounded-xl border border-slate-200 px-2 py-1.5 text-xs outline-none focus:border-emerald-500"
                                    >
                                        <option value="" disabled>Terminal…</option>
                                        <option v-for="t in terminalesPago" :key="t.id" :value="t.id">
                                            {{ t.nombre }}<template v-if="t.banco"> ({{ t.banco }})</template>
                                        </option>
                                    </select>
                                </div>

                                <p v-else-if="pedido?.estado === 'vencido'" class="text-xs font-bold text-amber-600">
                                    Vencido
                                </p>
                                <p v-else class="text-xs font-bold text-slate-400">
                                    Cerrado
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { Eye, Loader2, XCircle } from 'lucide-vue-next'
import EncargoEstadoBadge from './EncargoEstadoBadge.vue'
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();

defineProps({
    pedidos: { type: Array, default: () => [] },
    cargando: { type: Boolean, default: false },
    abonos: { type: Object, required: true },
    cuentasBancarias: { type: Array, default: () => [] },
    terminalesPago: { type: Array, default: () => [] },
})

defineEmits(['detalle', 'cancelar', 'abonar'])

function pedidoCerrado(pedido) {
    return ['entregado', 'devuelto', 'cancelado'].includes(pedido?.estado) || !tieneDetallesCancelables(pedido)
}

function tieneDetallesCancelables(pedido) {
    if (!Array.isArray(pedido?.detalles)) return true
    return pedido.detalles.some((detalle) => !['entregado', 'devuelto', 'cancelado'].includes(detalle?.estado))
}

function subtotalVigente(pedido) {
    if (!Array.isArray(pedido?.detalles)) return pedido?.subtotal
    return pedido.detalles
        .filter((detalle) => detalle?.estado !== 'cancelado')
        .reduce((total, detalle) => total + Number(detalle?.subtotal || 0), 0)
}

// Un pedido vencido ya no admite abonos, pero sigue pudiendo cancelarse para
// decidir que pasa con su anticipo (por eso no cuenta como "cerrado").
function puedeAbonar(pedido) {
    return !pedidoCerrado(pedido) && pedido?.estado !== 'vencido'
}

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}
</script>
