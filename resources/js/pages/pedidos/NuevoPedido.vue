<template>
    <main class="mx-auto max-w-6xl space-y-4">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Pedidos</p>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">Nuevo pedido</h1>
                <p class="mt-1 text-sm text-slate-500">Encargos sin depender necesariamente del stock actual.</p>
            </div>
        </header>

        <form class="rounded-2xl border border-slate-200 bg-white shadow-sm" @submit.prevent="guardarEncargo">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Datos del pedido</h2>
                    <p class="text-xs text-slate-500">Cliente, artículos y anticipo</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Pedido</span>
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
                        <input v-model.number="form.anticipo" type="number" min="0" step="0.01" :max="subtotal" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" :class="Number(form.anticipo) > subtotal ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : ''" />
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

                <p v-if="Number(form.anticipo) > subtotal" class="-mt-2 text-xs font-bold text-red-600">El anticipo no puede superar el total.</p>

                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_44px] lg:items-end">
                    <BaseSearchSelect
                        :model-value="null"
                        label="Producto a pedir"
                        placeholder="Buscar producto o variante"
                        :fetcher="buscarProductosCatalogo"
                        :min-chars="1"
                        :label-key="labelProducto"
                        :sub-label-key="subLabelProducto"
                        value-key="selector_id"
                        @selected="agregarProducto"
                    />
                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50" title="Crear producto rápido">
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <EncargoDetalleTable v-model:detalles="form.detalles" @remove="quitarDetalle" />

                <textarea v-model="form.notas" rows="2" class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Notas internas del pedido..." />

                <EncargoResumenCard :subtotal="subtotal" :anticipo="Number(form.anticipo || 0)" :saldo-pendiente="saldoPendiente" />

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60" :disabled="guardando || Number(form.anticipo) > subtotal || form.detalles.length === 0">
                    <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                    Registrar pedido
                </button>
            </div>
        </form>
    </main>
</template>

<script setup>
import { Loader2, Plus } from 'lucide-vue-next'
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
    buscarProductosCatalogo,
    labelProducto,
    subLabelProducto,
    agregarProducto,
    quitarDetalle,
    guardarEncargo,
} = useEncargos({ tipo: 'pedido' })
</script>
