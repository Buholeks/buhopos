<template>
    <article
        class="group flex flex-col rounded-2xl border bg-white transition hover:shadow-md"
        :class="destacada ? 'border-slate-200 bg-slate-50/60' : 'border-slate-200'"
    >
        <!-- Encabezado -->
        <header class="flex items-start gap-3 px-4 pt-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" :class="destacada ? 'bg-slate-200/70 text-slate-600' : 'bg-emerald-50 text-emerald-600'">
                <component :is="icono" class="h-[18px] w-[18px]" />
            </span>
            <div class="min-w-0 flex-1">
                <h4 class="truncate text-sm font-semibold text-slate-900" :title="nombre">{{ nombre }}</h4>
                <p class="text-xs text-slate-500">
                    {{ grupo.tipo === 'porcentaje' ? 'Porcentaje' : 'Monto por pieza' }} sobre {{ grupo.base === 'utilidad' ? 'utilidad' : 'venta' }}
                </p>
            </div>
            <div class="flex shrink-0 gap-0.5">
                <button type="button" class="accion" title="Editar" :aria-label="`Editar ${nombre}`" @click="emit('editar')">
                    <Pencil class="h-4 w-4" />
                </button>
                <button type="button" class="accion hover:bg-red-50 hover:text-red-600" title="Quitar" :aria-label="`Quitar ${nombre}`" @click="emit('eliminar')">
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>
        </header>

        <!-- Valor -->
        <div class="flex-1 px-4 py-3">
            <template v-if="grupo.forma === 'unico'">
                <p class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ grupo.tipo === 'porcentaje' ? `${Number(grupo.valor)}%` : dineroCorto(grupo.valor) }}
                    <span v-if="grupo.tipo !== 'porcentaje'" class="text-sm font-medium text-slate-500">por pieza</span>
                </p>
                <p v-if="preciosConValor.length" class="mt-1 text-xs text-slate-500">Valor base; algunos precios tienen valor propio.</p>
            </template>

            <table v-else class="w-full text-sm">
                <caption class="sr-only">Rangos de precio</caption>
                <tbody>
                    <tr v-for="(r, i) in grupo.rangos" :key="i" class="border-b border-dashed border-slate-200 last:border-0">
                        <td class="py-1.5 text-slate-600">{{ rangoTexto(i) }}</td>
                        <td class="py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ valorCorto(grupo.tipo, r.valor) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pie: precios y fuera de rango -->
        <footer v-if="preciosApagados.length || preciosConValor.length || grupo.forma === 'rangos'" class="space-y-1.5 border-t border-slate-100 px-4 py-2.5 text-xs">
            <p v-if="grupo.forma === 'rangos' && grupo.aplica_a !== 'general'" class="flex items-center gap-1.5 text-slate-500">
                <ArrowDownToLine class="h-3.5 w-3.5" />
                Fuera de los rangos: <strong class="font-semibold text-slate-700">{{ grupo.fuera_de_rango === 'sin_comision' ? 'sin comisión' : 'usa lo demás' }}</strong>
            </p>
            <p v-if="preciosConValor.length" class="flex flex-wrap items-center gap-1">
                <span v-for="p in preciosConValor" :key="p.lista" class="rounded-md bg-sky-50 px-1.5 py-0.5 font-medium text-sky-700">
                    {{ etiquetaLista(p.lista) }} {{ valorCorto(p.tipo, p.valor) }}
                </span>
            </p>
            <p v-if="preciosApagados.length" class="flex flex-wrap items-center gap-1 text-slate-500">
                <BanIcon class="h-3.5 w-3.5" /> Sin comisión en:
                <span v-for="p in preciosApagados" :key="p.lista" class="rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">{{ etiquetaLista(p.lista) }}</span>
            </p>
        </footer>
    </article>
</template>

<script setup>
import { computed } from "vue";
import { ArrowDownToLine, Ban as BanIcon, FolderTree, Layers, Package, Pencil, Trash2 } from "lucide-vue-next";
import { dineroCorto, etiquetaLista, valorCorto } from "@/helpers/comisiones";

const props = defineProps({
    grupo: { type: Object, required: true },
    titulo: { type: String, default: null },
    destacada: { type: Boolean, default: false }, // estilo atenuado para "lo demás"
});

const emit = defineEmits(["editar", "eliminar"]);

const nombre = computed(() => props.titulo ?? props.grupo.referencia_nombre);
const icono = computed(() => ({ categoria: FolderTree, producto: Package }[props.grupo.aplica_a] ?? Layers));

const preciosApagados = computed(() => props.grupo.precios.filter((p) => !p.activo));
const preciosConValor = computed(() =>
    props.grupo.forma === "unico"
        ? props.grupo.precios.filter((p) => p.activo && (p.tipo !== props.grupo.tipo || Number(p.valor) !== Number(props.grupo.valor)))
        : [],
);

function rangoTexto(i) {
    const rangos = props.grupo.rangos;
    const desde = Number(rangos[i].desde);
    const siguiente = rangos[i + 1];
    const hasta = siguiente ? Number(siguiente.desde) - 0.01 : props.grupo.hasta_final;
    if (hasta == null) return `${dineroCorto(desde)} o más`;
    if (desde === 0) return `Hasta ${dineroCorto(hasta)}`;
    return `${dineroCorto(desde)} – ${dineroCorto(hasta)}`;
}
</script>

<style scoped>
@reference "../../../css/app.css";

.accion {
    @apply rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-200;
}
</style>
