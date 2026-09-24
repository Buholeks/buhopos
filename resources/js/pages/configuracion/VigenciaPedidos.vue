<template>
    <main class="mx-auto max-w-2xl space-y-4">
        <header>
            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Configuración</p>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Pedidos y apartados</h1>
            <p class="mt-1 text-sm text-slate-500">
                Configura la vigencia y los comprobantes que se ofrecen al registrar pedidos y apartados.
            </p>
        </header>

        <div v-if="cargando" class="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white p-10 text-sm text-slate-500 shadow-sm">
            <Loader2 class="h-4 w-4 animate-spin" />
            Cargando configuración
        </div>

        <form v-else class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="guardar">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <label class="mb-1 block text-sm font-black text-slate-800">Días de vigencia de un apartado</label>
                <p class="mb-2 text-xs text-slate-500">
                    Se usa como fecha de promesa por defecto cuando se crea un apartado sin capturarla a mano (hoy + estos días). Un apartado vence en esa fecha y libera el stock reservado.
                </p>
                <input
                    v-model.number="form.dias_vigencia_apartado"
                    type="number"
                    min="1"
                    max="365"
                    step="1"
                    placeholder="Sin configurar: no vence automáticamente"
                    class="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                />
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <label class="mb-1 block text-sm font-black text-slate-800">Días de vigencia de un pedido, después de que llega</label>
                <p class="mb-2 text-xs text-slate-500">
                    Un pedido no tiene nada reservado hasta que el artículo llega (se registra la compra al proveedor). A partir de ese momento, cada renglón cuenta sus propios días; si nadie lo recoge a tiempo, se libera esa reserva y el pedido se marca "vencido". La fecha de promesa del pedido sigue siendo solo el estimado de llegada, no afecta este cálculo.
                </p>
                <input
                    v-model.number="form.dias_vigencia_pedido"
                    type="number"
                    min="1"
                    max="365"
                    step="1"
                    placeholder="Sin configurar: no vence automáticamente"
                    class="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                />
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Deja un campo vacío para desactivar el vencimiento automático correspondiente: nada vencerá solo hasta que se configure.
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-black text-slate-800">Comprobantes para clientes</h2>
                <p class="mb-3 mt-1 text-xs text-slate-500">Al terminar el registro se ofrecerá imprimir un comprobante en la impresora de tickets configurada en esta terminal.</p>
                <div class="space-y-3 text-sm">
                    <label class="flex items-center gap-2"><input v-model="form.imprimir_ticket_pedido" type="checkbox"> Ofrecer ticket al registrar un pedido</label>
                    <label class="flex items-center gap-2"><input v-model="form.imprimir_ticket_apartado" type="checkbox"> Ofrecer ticket al registrar un apartado</label>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-bold text-slate-700">
                        Pie para pedidos
                        <textarea v-model="form.pie_ticket_pedido" rows="3" maxlength="1000" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Ej. Conserva este comprobante para recoger tu pedido."></textarea>
                    </label>
                    <label class="block text-sm font-bold text-slate-700">
                        Pie para apartados
                        <textarea v-model="form.pie_ticket_apartado" rows="3" maxlength="1000" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Ej. Presenta este comprobante al liquidar tu apartado."></textarea>
                    </label>
                </div>
                <p class="mt-2 text-xs text-slate-500">Puedes usar varias líneas. Si lo dejas vacío se imprimirá el aviso predeterminado.</p>
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60" :disabled="guardando">
                <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                Guardar configuración
            </button>
        </form>
    </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import http from '@/lib/http'
import { toastError, toastSuccess } from '@/lib/alert'

const cargando = ref(true)
const guardando = ref(false)
const form = reactive({
    dias_vigencia_apartado: null,
    dias_vigencia_pedido: null,
    imprimir_ticket_pedido: false,
    imprimir_ticket_apartado: false,
    pie_ticket_pedido: '',
    pie_ticket_apartado: '',
})

onMounted(async () => {
    try {
        const { data } = await http.get('/api/config-pedidos')
        form.dias_vigencia_apartado = data?.dias_vigencia_apartado ?? null
        form.dias_vigencia_pedido = data?.dias_vigencia_pedido ?? null
        form.imprimir_ticket_pedido = data?.imprimir_ticket_pedido === true
        form.imprimir_ticket_apartado = data?.imprimir_ticket_apartado === true
        form.pie_ticket_pedido = data?.pie_ticket_pedido ?? ''
        form.pie_ticket_apartado = data?.pie_ticket_apartado ?? ''
    } catch {
        toastError('No se pudo cargar la configuración')
    } finally {
        cargando.value = false
    }
})

async function guardar() {
    guardando.value = true
    try {
        await http.put('/api/config-pedidos', {
            dias_vigencia_apartado: form.dias_vigencia_apartado || null,
            dias_vigencia_pedido: form.dias_vigencia_pedido || null,
            imprimir_ticket_pedido: form.imprimir_ticket_pedido,
            imprimir_ticket_apartado: form.imprimir_ticket_apartado,
            pie_ticket_pedido: form.pie_ticket_pedido.trim() || null,
            pie_ticket_apartado: form.pie_ticket_apartado.trim() || null,
        })
        toastSuccess('Configuración guardada')
    } catch (e) {
        toastError(e?.response?.data?.message || 'No se pudo guardar la configuración')
    } finally {
        guardando.value = false
    }
}
</script>
