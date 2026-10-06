<template>
    <main class="mx-auto max-w-screen-xl space-y-5 p-3 sm:p-6">
        <header>
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Mi perfil</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Mis comisiones</h1>
            <p class="mt-1 text-sm text-slate-500">Lo que llevas ganado por tus ventas. Las devoluciones y cancelaciones se descuentan solas.</p>
        </header>

        <section class="flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
            <FiltroPeriodo @cambio="cambiarPeriodo" />
            <div v-if="datos" class="flex flex-wrap gap-1.5 pb-1.5 text-xs">
                <span v-if="datos.esquemas.vendedor" class="chip">
                    <UserRound class="h-3 w-3" /> Vendedor: {{ datos.esquemas.vendedor.nombre }}
                </span>
                <span v-if="datos.esquemas.cajero" class="chip">
                    <ShoppingBag class="h-3 w-3" /> Cajero: {{ datos.esquemas.cajero.nombre }}
                </span>
                <span v-if="!datos.esquemas.vendedor && !datos.esquemas.cajero" class="chip bg-amber-50 text-amber-700">
                    No tienes un esquema de comisión asignado
                </span>
            </div>
        </section>

        <div v-if="!datos && cargando" class="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white p-12 text-sm text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin" /> Cargando…
        </div>

        <template v-else-if="datos">
            <ResumenComision :datos="datos.resumen" />
            <MovimientosComision :pagina="datos.movimientos" :cargando="cargando" @filtrar="filtrar" @pagina="cargar" />
        </template>
    </main>
</template>

<script setup>
import { ref } from "vue";
import { Loader2, ShoppingBag, UserRound } from "lucide-vue-next";
import FiltroPeriodo from "@/components/comisiones/FiltroPeriodo.vue";
import MovimientosComision from "@/components/comisiones/MovimientosComision.vue";
import ResumenComision from "@/components/comisiones/ResumenComision.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";

const periodo = ref(null);
const filtros = ref({});
const datos = ref(null);
const cargando = ref(true);

function cambiarPeriodo(p) {
    periodo.value = p;
    cargar(1);
}

function filtrar(f) {
    filtros.value = f;
    cargar(1);
}

async function cargar(page = 1) {
    if (!periodo.value) return;
    cargando.value = true;
    try {
        const { data } = await http.get("/api/comisiones/mias", { params: { ...periodo.value, ...filtros.value, page } });
        datos.value = data;
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudieron cargar tus comisiones.");
    } finally {
        cargando.value = false;
    }
}
</script>

<style scoped>
@reference "../../../css/app.css";

.chip {
    @apply inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600;
}
</style>
