<template>
    <main class="mx-auto max-w-screen-xl space-y-5 p-3 sm:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Mi perfil</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Mis comisiones</h1>
                <p class="mt-1 text-sm text-slate-500">Lo que llevas ganado por tus ventas. Las devoluciones y cancelaciones se descuentan solas.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2">
                <FiltroPeriodo @cambio="cambiarPeriodo" />
            </div>
        </header>

        <!-- Cargando -->
        <section v-if="!datos && cargando" class="animate-pulse rounded-2xl border border-slate-200 bg-white p-5" aria-busy="true">
            <div class="h-3 w-40 rounded bg-slate-100"></div>
            <div class="mt-3 h-9 w-48 rounded bg-slate-100"></div>
            <div class="mt-3 h-3 w-64 rounded bg-slate-100"></div>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="i in 4" :key="i" class="h-12 rounded-xl bg-slate-50"></div>
            </div>
        </section>

        <!-- Error -->
        <section v-else-if="!datos && errorCarga" class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white p-10 text-center">
            <CircleAlert class="h-9 w-9 text-red-400" />
            <p class="text-sm text-red-600">{{ errorCarga }}</p>
            <button type="button" class="boton-secundario" @click="cargar(1)">
                <RotateCw class="h-4 w-4" /> Reintentar
            </button>
        </section>

        <template v-else-if="datos">
            <!-- Sin esquema -->
            <div v-if="sinEsquema" class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <Info class="mt-0.5 h-4 w-4 shrink-0" />
                <p>No tienes un esquema de comisión asignado, así que tus ventas nuevas no generan comisión. Consulta con tu encargado.</p>
            </div>

            <!-- Resumen principal -->
            <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white">
                <div class="flex flex-wrap items-end justify-between gap-4 bg-gradient-to-br from-emerald-50 to-white p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Tu comisión del periodo</p>
                        <p class="mt-1 text-4xl font-bold tracking-tight tabular-nums text-slate-900">{{ dinero(r.total) }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            <span class="font-semibold text-emerald-700">{{ dinero(r.pendiente) }}</span> por cobrar
                            <span class="text-slate-400"> · </span>
                            {{ dinero(r.pagado) }} ya pagado
                        </p>
                    </div>
                    <div v-if="datos.esquemas.vendedor || datos.esquemas.cajero" class="flex flex-wrap gap-1.5 text-xs">
                        <span v-if="datos.esquemas.vendedor" class="chip">
                            <UserRound class="h-3 w-3" /> {{ datos.esquemas.vendedor.nombre }}
                        </span>
                        <span v-if="datos.esquemas.cajero" class="chip">
                            <ShoppingBag class="h-3 w-3" /> {{ datos.esquemas.cajero.nombre }}
                        </span>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-px border-t border-emerald-100 bg-emerald-100" :class="COLUMNAS[datosSecundarios.length]">
                    <div
                        v-for="(d, i) in datosSecundarios"
                        :key="d.etiqueta"
                        class="dato"
                        :class="{ 'col-span-2 sm:col-span-1': i === datosSecundarios.length - 1 && datosSecundarios.length % 2 === 1 }"
                    >
                        <dt>{{ d.etiqueta }}</dt>
                        <dd :class="d.clase">{{ d.valor }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Detalle -->
            <MovimientosComision
                :pagina="datos.movimientos"
                :cargando="cargando"
                :filtrable="true"
                titulo="Venta por venta"
                @filtrar="filtrar"
                @pagina="cargar"
            />
        </template>
    </main>
</template>

<script setup>
import { computed, ref } from "vue";
import { CircleAlert, Info, RotateCw, ShoppingBag, UserRound } from "lucide-vue-next";
import FiltroPeriodo from "@/components/comisiones/FiltroPeriodo.vue";
import MovimientosComision from "@/components/comisiones/MovimientosComision.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { dinero } from "@/helpers/comisiones";

const periodo = ref(null);
const filtros = ref({});
const datos = ref(null);
const cargando = ref(true);
const errorCarga = ref("");
let solicitud = 0;

const r = computed(() => datos.value?.resumen ?? {});

const sinEsquema = computed(() => !datos.value?.esquemas?.vendedor && !datos.value?.esquemas?.cajero);

// La parte de cajero solo se muestra a quien cobra en caja o tuvo comisiones como cajero.
const tieneCajero = computed(() => !!datos.value?.esquemas?.cajero || Number(r.value.comision_cajero ?? 0) !== 0);

// Clases estáticas para que Tailwind las genere.
const COLUMNAS = { 3: "sm:grid-cols-3", 4: "sm:grid-cols-4", 5: "sm:grid-cols-5" };

const datosSecundarios = computed(() => {
    const v = r.value;
    const lista = [
        { etiqueta: "Tickets", valor: String((v.tickets_vendedor ?? 0) + (tieneCajero.value ? (v.tickets_cajero ?? 0) : 0)) },
        { etiqueta: "Como vendedor", valor: dinero(v.comision_vendedor) },
    ];
    if (tieneCajero.value) lista.push({ etiqueta: "Como cajero", valor: dinero(v.comision_cajero) });
    lista.push({
        etiqueta: "Devoluciones / cancel.",
        valor: dinero(v.descuentos),
        clase: Number(v.descuentos) < 0 ? "text-red-600" : "text-slate-400",
    });
    if (Number(v.ajustes ?? 0) !== 0) lista.push({ etiqueta: "Ajustes", valor: dinero(v.ajustes) });
    return lista;
});

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
    const actual = ++solicitud;
    cargando.value = true;
    errorCarga.value = "";
    try {
        const { data } = await http.get("/api/comisiones/mias", { params: { ...periodo.value, ...filtros.value, page } });
        if (actual !== solicitud) return;
        datos.value = data;
    } catch (e) {
        if (actual !== solicitud) return;
        datos.value = null;
        errorCarga.value = e?.response?.data?.message || "No se pudieron cargar tus comisiones.";
        toastError(errorCarga.value);
    } finally {
        if (actual === solicitud) cargando.value = false;
    }
}
</script>

<style scoped>
@reference "../../../css/app.css";

.chip {
    @apply inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-white px-2.5 py-1 font-medium text-emerald-800;
}
.dato {
    @apply bg-white px-4 py-3;
}
.dato dt {
    @apply text-[11px] font-semibold uppercase tracking-wider text-slate-500;
}
.dato dd {
    @apply mt-0.5 text-base font-bold tabular-nums text-slate-900;
}
.boton-secundario {
    @apply flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50;
}
</style>
