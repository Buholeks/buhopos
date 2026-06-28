<template>
    <main class="space-y-4">
        <header>
            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">Apartados</p>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Consulta de apartados</h1>
            <p class="mt-1 text-sm text-slate-500">Reservas hechas sobre inventario existente.</p>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Seguimiento</h2>
                    <p class="text-sm text-slate-500">Filtra por estado, fecha o cliente.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="filtroEstado" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" @change="cargarPedidos">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="disponible">Disponible</option>
                        <option value="entregado">Entregado</option>
                        <option value="devuelto">Devuelto</option>
                        <option value="cancelado">Cancelado</option>
                        <option value="vencido">Vencido</option>
                    </select>
                    <input v-model="filtroFechaDesde" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" @change="cargarPedidos" />
                    <input v-model="filtroFechaHasta" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" @change="cargarPedidos" />
                    <div class="relative w-full sm:w-72">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input v-model="buscar" class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Folio, cliente o artículo" @keyup.enter="cargarPedidos" />
                    </div>
                </div>
            </div>
        </section>

        <EncargosTable :pedidos="pedidos" :cargando="cargando" :abonos="abonos" @detalle="abrirDetalle" @cancelar="abrirCancelar" @abonar="registrarAbono" />
        <EncargoDetalleModal :visible="modalDetalle.visible" :cargando="modalDetalle.cargando" :pedido="modalDetalle.pedido" :data="modalDetalle.data" @close="cerrarDetalle" />
        <EncargoCancelarModal :visible="modalCancelar.visible" :procesando="modalCancelar.procesando" :pedido="modalCancelar.pedido" @close="cerrarCancelar" @confirm="ejecutarCancelacion" />
    </main>
</template>

<script setup>
import { onMounted, reactive, watch } from 'vue'
import { Search } from 'lucide-vue-next'
import http from '@/lib/http'
import { toastError } from '@/lib/alert'
import EncargosTable from '@/components/encargos/EncargosTable.vue'
import EncargoDetalleModal from '@/components/encargos/EncargoDetalleModal.vue'
import EncargoCancelarModal from '@/components/encargos/EncargoCancelarModal.vue'
import { useEncargos } from '@/stores/useEncargos'

let timer = null
const modalDetalle = reactive({ visible: false, cargando: false, pedido: null, data: null })
const modalCancelar = reactive({ visible: false, procesando: false, pedido: null })
const { pedidos, cargando, buscar, filtroEstado, filtroFechaDesde, filtroFechaHasta, abonos, cargarPedidos, registrarAbono, cancelarPedido } = useEncargos({ tipo: 'apartado' })

watch(buscar, () => {
    window.clearTimeout(timer)
    timer = window.setTimeout(cargarPedidos, 350)
})
onMounted(cargarPedidos)

async function abrirDetalle(pedido) {
    modalDetalle.visible = true
    modalDetalle.cargando = true
    modalDetalle.pedido = pedido
    modalDetalle.data = null
    try {
        const { data } = await http.get(`/api/pedidos/${pedido.id}`)
        modalDetalle.data = data
    } catch {
        toastError('No se pudo cargar el detalle')
        cerrarDetalle()
    } finally {
        modalDetalle.cargando = false
    }
}
function cerrarDetalle() {
    modalDetalle.visible = false
    modalDetalle.pedido = null
    modalDetalle.data = null
}
function abrirCancelar(pedido) {
    modalCancelar.visible = true
    modalCancelar.pedido = pedido
}
function cerrarCancelar() {
    modalCancelar.visible = false
    modalCancelar.pedido = null
    modalCancelar.procesando = false
}
async function ejecutarCancelacion() {
    if (!modalCancelar.pedido) return
    modalCancelar.procesando = true
    try {
        await cancelarPedido(modalCancelar.pedido)
        cerrarCancelar()
    } finally {
        modalCancelar.procesando = false
    }
}
</script>
