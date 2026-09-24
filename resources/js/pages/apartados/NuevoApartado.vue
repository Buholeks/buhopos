<template>
    <main class="mx-auto max-w-6xl space-y-4">
        <header>
            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Apartados</p>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Nuevo apartado</h1>
            <p class="mt-1 text-sm text-slate-500">Reserva mercancía con inventario existente.</p>
        </header>

        <form class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="guardarEncargo">
            <div class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-black text-emerald-700">1</span>
                        <div>
                            <h2 class="font-black text-slate-900">Selecciona al cliente</h2>
                            <p class="text-xs text-slate-500">Busca por nombre, teléfono o correo.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_44px] items-end gap-2">
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
                        <button
                            v-if="auth.can('clientes.editar')"
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm transition hover:bg-emerald-700"
                            title="Registrar nuevo cliente"
                            aria-label="Registrar nuevo cliente"
                            @click="clienteModalOpen = true"
                        >
                            <Plus class="h-5 w-5" />
                        </button>
                    </div>
                    <div v-if="cliente" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                        <div>
                            <p class="font-black text-emerald-950">{{ cliente.nombre }}</p>
                            <p class="text-xs text-emerald-800">{{ cliente.telefono || cliente.email || 'Sin teléfono registrado' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black uppercase tracking-wide text-emerald-700">Saldo a favor</p>
                            <p class="text-lg font-black text-emerald-950">{{ money(resumenCliente.saldo_favor) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-black text-emerald-700">2</span>
                        <div>
                            <h2 class="font-black text-slate-900">Agrega los productos</h2>
                            <p class="text-xs text-slate-500">Solo aparecen productos disponibles para reservar.</p>
                        </div>
                    </div>
                    <BaseSearchSelect
                        :model-value="null"
                        label="Buscar producto"
                        placeholder="Nombre, código o variante"
                        :fetcher="buscarProductosConStock"
                        :min-chars="1"
                        :label-key="labelProducto"
                        :sub-label-key="subLabelProducto"
                        value-key="selector_id"
                        @selected="agregarProducto"
                    />
                    <div class="mt-4">
                        <EncargoDetalleTable v-model:detalles="form.detalles" @remove="quitarDetalle" />
                    </div>
                    <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <summary class="cursor-pointer text-sm font-bold text-slate-700">Agregar notas internas</summary>
                        <textarea v-model="form.notas" rows="3" class="mt-3 w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Indicaciones internas del apartado..." />
                    </details>
                </section>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Paso 3</p>
                            <h2 class="font-black text-slate-900">Pago y confirmación</h2>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Apartado</span>
                    </div>

                    <EncargoResumenCard :subtotal="subtotal" :anticipo="Number(form.anticipo || 0)" :saldo-pendiente="saldoPendiente" />

                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-bold text-slate-600">Anticipo</span>
                            <input v-model.number="form.anticipo" type="number" min="0" step="0.01" :max="subtotal" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-bold text-slate-600">Forma de pago</span>
                            <select v-model="form.forma_pago" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" :disabled="Number(form.anticipo) <= 0" :class="Number(form.anticipo) <= 0 ? 'bg-slate-50 text-slate-400' : ''">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>
                        </label>
                        <label v-if="Number(form.anticipo) > 0 && form.forma_pago === 'transferencia'" class="block">
                            <span class="mb-1 block text-xs font-bold text-slate-600">Cuenta bancaria</span>
                            <select v-model="form.cuenta_bancaria_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                                <option value="" disabled>Selecciona una cuenta…</option>
                                <option v-for="c in cuentasBancarias" :key="c.id" :value="c.id">{{ c.nombre }}<template v-if="c.banco"> ({{ c.banco }})</template></option>
                            </select>
                        </label>
                        <label v-if="Number(form.anticipo) > 0 && form.forma_pago === 'tarjeta'" class="block">
                            <span class="mb-1 block text-xs font-bold text-slate-600">Terminal</span>
                            <select v-model="form.terminal_pago_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                                <option value="" disabled>Selecciona una terminal…</option>
                                <option v-for="t in terminalesPago" :key="t.id" :value="t.id">{{ t.nombre }}<template v-if="t.banco"> ({{ t.banco }})</template></option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-bold text-slate-600">Fecha prometida</span>
                            <input v-model="form.fecha_promesa" type="date" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                            <span class="mt-1 block text-[11px] text-slate-500">Si la dejas vacía, se aplicará la vigencia configurada.</span>
                        </label>
                    </div>

                    <div class="sticky bottom-2 z-10 mt-4 rounded-xl bg-white pt-2">
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!!motivoBloqueo">
                            <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                            Registrar apartado
                        </button>
                        <p v-if="motivoBloqueo && !guardando" class="mt-2 text-center text-xs text-amber-700">{{ motivoBloqueo }}</p>
                    </div>
                </section>
            </aside>
        </form>

        <ClienteCreateModal
            :open="clienteModalOpen"
            @close="clienteModalOpen = false"
            @created="clienteCreado"
        />
    </main>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Loader2, Plus } from 'lucide-vue-next'
import BaseSearchSelect from '@/components/ui/BaseSearchSelect.vue'
import ClienteCreateModal from '@/components/clientes/ClienteCreateModal.vue'
import EncargoDetalleTable from '@/components/encargos/EncargoDetalleTable.vue'
import EncargoResumenCard from '@/components/encargos/EncargoResumenCard.vue'
import { useAuthStore } from '@/stores/auth'
import { useEncargos } from '@/stores/useEncargos'

const auth = useAuthStore()
const clienteModalOpen = ref(false)

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
    cuentasBancarias,
    terminalesPago,
    cargarCuentasBancarias,
    cargarTerminalesPago,
} = useEncargos({ tipo: 'apartado' })

cargarCuentasBancarias()
cargarTerminalesPago()

async function clienteCreado(nuevoCliente) {
    clienteModalOpen.value = false
    await seleccionarCliente(nuevoCliente)
}

const motivoBloqueo = computed(() => {
    if (guardando.value) return 'Registrando apartado…'
    if (!cliente.value?.id) return 'Selecciona un cliente para continuar.'
    if (form.detalles.length === 0) return 'Agrega al menos un producto.'
    if (Number(form.anticipo) > subtotal.value) return 'El anticipo no puede superar el total.'
    if (Number(form.anticipo) > 0 && form.forma_pago === 'transferencia' && !form.cuenta_bancaria_id) return 'Selecciona una cuenta bancaria.'
    if (Number(form.anticipo) > 0 && form.forma_pago === 'tarjeta' && !form.terminal_pago_id) return 'Selecciona una terminal.'
    return ''
})
</script>
