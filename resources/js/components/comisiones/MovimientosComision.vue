<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <!-- Filtros -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <h3 class="text-sm font-bold text-slate-900">{{ titulo }}</h3>
            <div v-if="filtrable" class="flex flex-wrap gap-2">
                <select v-model="beneficiario" class="campo" aria-label="Filtrar por rol" @change="emit('filtrar', filtros())">
                    <option value="">Vendedor y cajero</option>
                    <option value="vendedor">Como vendedor</option>
                    <option value="cajero">Como cajero</option>
                </select>
                <select v-model="tipo" class="campo" aria-label="Filtrar por tipo" @change="emit('filtrar', filtros())">
                    <option value="">Todos los movimientos</option>
                    <option value="venta">Ventas</option>
                    <option value="devolucion">Devoluciones</option>
                    <option value="cancelacion">Cancelaciones</option>
                    <option value="ajuste">Ajustes</option>
                </select>
            </div>
        </div>

        <div v-if="cargando" class="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin" /> Cargando…
        </div>

        <p v-else-if="!pagina?.data?.length" class="p-10 text-center text-sm text-slate-400">No hay comisiones en este periodo.</p>

        <template v-else>
            <!-- Escritorio -->
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Ticket</th>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2">Cálculo</th>
                            <th class="px-4 py-2 text-right">Comisión</th>
                            <th class="px-4 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in pagina.data" :key="m.id" :class="m.monto < 0 ? 'bg-red-50/30' : ''">
                            <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ fecha(m.fecha) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span class="font-mono text-xs font-semibold text-slate-800">{{ m.folio ?? '—' }}</span>
                                <span class="ml-1.5 rounded px-1.5 py-px text-[10px] font-semibold" :class="ESTILO_TIPO[m.tipo]">{{ ETIQUETA_TIPO[m.tipo] }}</span>
                                <p class="text-[11px] text-slate-400">
                                    {{ m.beneficiario === 'cajero' ? 'Cajero' : 'Vendedor' }}<template v-if="m.sucursal"> · {{ m.sucursal }}</template>
                                </p>
                            </td>
                            <td class="max-w-[260px] px-4 py-2.5">
                                <p class="truncate text-slate-800" :title="m.producto">{{ m.producto ?? '—' }}</p>
                                <p v-if="m.precio !== null" class="text-[11px] text-slate-400">
                                    {{ Number(Math.abs(m.cantidad)) }} × {{ dinero(m.precio) }}<template v-if="m.lista_precio"> · {{ etiquetaLista(m.lista_precio) }}</template>
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-500">{{ calculo(m) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums" :class="m.monto < 0 ? 'text-red-600' : 'text-slate-900'">
                                {{ dinero(m.monto) }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="m.estado_pago === 'pagada' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                    {{ m.estado_pago === 'pagada' ? 'Pagada' : 'Pendiente' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Celular -->
            <ul class="divide-y divide-slate-100 md:hidden">
                <li v-for="m in pagina.data" :key="m.id" class="flex items-start justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-800">{{ m.producto ?? '—' }}</p>
                        <p class="text-xs text-slate-500">
                            {{ fecha(m.fecha) }} · <span class="font-mono">{{ m.folio ?? '—' }}</span> · {{ ETIQUETA_TIPO[m.tipo] }}
                        </p>
                        <p class="text-[11px] text-slate-400">{{ calculo(m) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-semibold tabular-nums" :class="m.monto < 0 ? 'text-red-600' : 'text-slate-900'">{{ dinero(m.monto) }}</p>
                        <p class="text-[11px]" :class="m.estado_pago === 'pagada' ? 'text-emerald-700' : 'text-amber-700'">{{ m.estado_pago === 'pagada' ? 'Pagada' : 'Pendiente' }}</p>
                    </div>
                </li>
            </ul>

            <!-- Paginación -->
            <div v-if="pagina.last_page > 1" class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-2.5 text-sm">
                <span class="text-slate-500">{{ pagina.from }}–{{ pagina.to }} de {{ pagina.total }}</span>
                <div class="flex gap-1">
                    <button type="button" class="boton-pagina" :disabled="pagina.current_page <= 1" aria-label="Página anterior" @click="emit('pagina', pagina.current_page - 1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button type="button" class="boton-pagina" :disabled="pagina.current_page >= pagina.last_page" aria-label="Página siguiente" @click="emit('pagina', pagina.current_page + 1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { ChevronLeft, ChevronRight, Loader2 } from "lucide-vue-next";
import { dinero, dineroCorto, etiquetaLista } from "@/helpers/comisiones";

defineProps({
    pagina: { type: Object, default: null }, // paginador de Laravel
    cargando: { type: Boolean, default: false },
    filtrable: { type: Boolean, default: true },
    titulo: { type: String, default: "Detalle" },
});

const emit = defineEmits(["filtrar", "pagina"]);

const ETIQUETA_TIPO = { venta: "Venta", devolucion: "Devolución", cancelacion: "Cancelación", ajuste: "Ajuste" };
const ESTILO_TIPO = {
    venta: "bg-slate-100 text-slate-600",
    devolucion: "bg-red-50 text-red-700",
    cancelacion: "bg-red-50 text-red-700",
    ajuste: "bg-sky-50 text-sky-700",
};

const beneficiario = ref("");
const tipo = ref("");

const fmtFecha = new Intl.DateTimeFormat("es-MX", { day: "2-digit", month: "short" });

function filtros() {
    return { beneficiario: beneficiario.value || undefined, tipo: tipo.value || undefined };
}

function fecha(texto) {
    if (!texto) return "—";
    const [a, m, d] = texto.split("-").map(Number);
    return fmtFecha.format(new Date(a, m - 1, d));
}

function calculo(m) {
    if (m.tipo === "ajuste") return "Ajuste manual";
    if (!m.regla_tipo) return "—";
    if (m.regla_tipo === "porcentaje") {
        return `${Number(m.tasa)}% de ${dinero(Math.abs(m.base_calculo))}${m.base === "utilidad" ? " de utilidad" : ""}`;
    }
    return `${dineroCorto(m.tasa)} × ${Number(Math.abs(m.cantidad))} pza`;
}
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
.boton-pagina {
    @apply flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-40;
}
</style>
