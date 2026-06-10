<template>
    <main class="mx-auto max-w-6xl space-y-4">
        <header>
            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Apartados</p>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Nuevo apartado</h1>
            <p class="mt-1 text-sm text-slate-500">Reserva mercancía con inventario existente.</p>
        </header>

        <form class="rounded-2xl border border-slate-200 bg-white shadow-sm" @submit.prevent="guardarEncargo">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Datos del apartado</h2>
                    <p class="text-xs text-slate-500">Cliente, artículos reservados y anticipo</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Apartado</span>
            </div>

            <div class="space-y-4 p-4">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_160px]">
                    <BaseSearchSelect
                        :model-value="cliente?.id ?? null"
                        label="Cliente"
                        placeholder="Buscar cliente"
                        :fetcher="buscarClientes"
                        :min-chars="1"
                        :label-key="(item) => item.nombre || 'Sin nombre'"
                        :sub-label-key="(item) => item.telefono || item.email || 'Sin referencia'"
                        value-key="id"
                        required
                        @selected="seleccionarCliente"
                    />
                    <div v-if="cliente" class="rounded-2xl border border-emerald-100 bg-emerald-50 px-3 py-2">
                        <p class="text-[11px] font-black uppercase tracking-wide text-emerald-700">Saldo favor</p>
                        <p class="text-lg font-black text-emerald-900">{{ money(resumenCliente.saldo_favor) }}</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-xs font-bold text-slate-600">Fecha promesa</span>
                        <input v-model="form.fecha_promesa" type="date" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-bold text-slate-600">Anticipo</span>
                        <input v-model.number="form.anticipo" type="number" min="0" step="0.01" :max="subtotal" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-bold text-slate-600">Pago anticipo</span>
                        <select v-model="form.forma_pago" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" :disabled="Number(form.anticipo) <= 0" :class="Number(form.anticipo) <= 0 ? 'bg-slate-50 text-slate-400' : ''">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </label>
                </div>

                <BaseSearchSelect
                    :model-value="null"
                    label="Producto a apartar"
                    placeholder="Buscar producto con stock"
                    :fetcher="buscarProductosConStock"
                    :min-chars="1"
                    :label-key="labelProducto"
                    :sub-label-key="subLabelProducto"
                    value-key="selector_id"
                    @selected="agregarProducto"
                />

                <EncargoDetalleTable v-model:detalles="form.detalles" @remove="quitarDetalle" />

                <textarea v-model="form.notas" rows="2" class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Notas internas del apartado..." />
                <EncargoResumenCard :subtotal="subtotal" :anticipo="Number(form.anticipo || 0)" :saldo-pendiente="saldoPendiente" />

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60" :disabled="guardando || Number(form.anticipo) > subtotal || form.detalles.length === 0">
                    <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                    Registrar apartado
                </button>
            </div>
        </form>
    </main>
</template>

<script setup>
import { Loader2 } from 'lucide-vue-next'
import BaseSearchSelect from '@/components/ui/BaseSearchSelect.vue'
import EncargoDetalleTable from '@/components/encargos/EncargoDetalleTable.vue'
import EncargoResumenCard from '@/components/encargos/EncargoResumenCard.vue'
import { useEncargos } from '@/stores/useEncargos'

const {
    cliente,
    resumenCliente,
    form,
    subtotal,
    saldoPendiente,
    guardando,
    money,
    buscarClientes,
    seleccionarCliente,
    buscarProductosConStock,
    labelProducto,
    subLabelProducto,
    agregarProducto,
    quitarDetalle,
    guardarEncargo,
} = useEncargos({ tipo: 'apartado' })
</script>
