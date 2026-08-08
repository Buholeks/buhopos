<template>
    <div class="space-y-4">
        <!-- Cargando -->
        <div
            v-if="cargando"
            class="flex min-h-48 items-center justify-center rounded-xl border border-slate-200 bg-white"
        >
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" />
                Cargando resumen...
            </div>
        </div>

        <!-- Error -->
        <div
            v-else-if="error"
            class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
        >
            <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />

            <div class="flex-1">
                <p class="font-medium">No se pudo cargar el resumen</p>
                <p class="mt-0.5 text-xs text-rose-600">
                    {{ error }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
                @click="cargar"
            >
                Reintentar
            </button>
        </div>

        <template v-else-if="datos">
            <!-- Métricas -->
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <article
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-slate-500">
                                {{ card.label }}
                            </p>

                            <p
                                class="mt-1 truncate text-xl font-semibold tracking-tight text-slate-900"
                                :class="card.valueClass"
                            >
                                {{ card.value }}
                            </p>
                        </div>

                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                            :class="card.iconClass"
                        >
                            <component
                                :is="card.icon"
                                class="h-4 w-4"
                            />
                        </div>
                    </div>

                    <p
                        v-if="card.description"
                        class="mt-2 truncate text-[11px] text-slate-400"
                    >
                        {{ card.description }}
                    </p>
                </article>
            </section>

            <!-- Próximos vencimientos -->
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Próximos vencimientos
                        </h2>

                        <p class="mt-0.5 text-xs text-slate-500">
                            Suscripciones que vencen en los próximos 15 días
                        </p>
                    </div>

                    <span
                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-lg bg-amber-50 px-2 text-xs font-medium text-amber-700"
                    >
                        {{ datos.proximos_vencimientos.length }}
                    </span>
                </div>

                <!-- Vacío -->
                <div
                    v-if="!datos.proximos_vencimientos.length"
                    class="flex flex-col items-center justify-center px-4 py-10 text-center"
                >
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50">
                        <CircleCheck class="h-4 w-4 text-emerald-600" />
                    </div>

                    <p class="mt-2 text-sm font-medium text-slate-600">
                        No hay vencimientos próximos
                    </p>

                    <p class="mt-0.5 text-xs text-slate-400">
                        Las suscripciones están al corriente.
                    </p>
                </div>

                <!-- Lista -->
                <div v-else class="divide-y divide-slate-100">
                    <RouterLink
                        v-for="suscripcion in datos.proximos_vencimientos"
                        :key="suscripcion.id"
                        :to="{
                            name: 'plataforma-empresa-detalle',
                            params: {
                                id: suscripcion.empresa_id,
                            },
                        }"
                        class="group flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <Building2 class="h-4 w-4" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">
                                {{
                                    suscripcion.empresa?.nombre ||
                                    "Empresa sin nombre"
                                }}
                            </p>

                            <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="truncate">
                                    {{ suscripcion.plan?.nombre || "Sin plan" }}
                                </span>

                                <span class="text-slate-300">•</span>

                                <span>
                                    {{ diasRestantes(suscripcion.fecha_vencimiento) }}
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-xs font-medium text-amber-700">
                                {{ formatearFecha(suscripcion.fecha_vencimiento) }}
                            </p>

                            <p class="mt-0.5 text-[10px] text-slate-400">
                                Fecha de vencimiento
                            </p>
                        </div>

                        <ChevronRight
                            class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500"
                        />
                    </RouterLink>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import {
    Building2,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    Clock3,
    DollarSign,
    Loader2,
    ShieldCheck,
    TriangleAlert,
    UsersRound,
} from "lucide-vue-next";

import http from "@/lib/http";

const datos = ref(null);
const cargando = ref(false);
const error = ref("");

const dinero = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const cards = computed(() => {
    if (!datos.value) {
        return [];
    }

    return [
        {
            label: "Empresas",
            value: datos.value.empresas ?? 0,
            description: "Empresas registradas",
            icon: Building2,
            iconClass: "bg-slate-100 text-slate-600",
            valueClass: "text-slate-900",
        },
        {
            label: "Con acceso",
            value: datos.value.empresas_activas ?? 0,
            description: "Por pago, prueba o promoción",
            icon: ShieldCheck,
            iconClass: "bg-emerald-50 text-emerald-700",
            valueClass: "text-emerald-700",
        },
        {
            label: "Pendientes",
            value: datos.value.registros_pendientes ?? 0,
            description: "Pendientes de elegir plan o pagar",
            icon: Clock3,
            iconClass: "bg-amber-50 text-amber-700",
            valueClass: "text-amber-700",
        },
        {
            label: "Vencidas o suspendidas",
            value: datos.value.suscripciones_vencidas ?? 0,
            description: "Requieren atención",
            icon: TriangleAlert,
            iconClass: "bg-rose-50 text-rose-700",
            valueClass: "text-rose-700",
        },
        {
            label: "Ingresos del mes",
            value: dinero.format(Number(datos.value.ingresos_mes || 0)),
            description: "Cobrado durante el mes",
            icon: DollarSign,
            iconClass: "bg-violet-50 text-violet-700",
            valueClass: "text-slate-900",
        },
    ];
});

async function cargar() {
    if (cargando.value) {
        return;
    }

    cargando.value = true;
    error.value = "";

    try {
        const { data } = await http.get("/api/plataforma/dashboard");
        datos.value = data;
    } catch (e) {
        error.value =
            e?.response?.data?.message ||
            "Ocurrió un error al consultar la información.";
    } finally {
        cargando.value = false;
    }
}

function formatearFecha(fecha) {
    if (!fecha) {
        return "Sin fecha";
    }

    const valor = new Date(`${fecha}T00:00:00`);

    if (Number.isNaN(valor.getTime())) {
        return fecha;
    }

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(valor);
}

function diasRestantes(fecha) {
    if (!fecha) {
        return "Sin fecha";
    }

    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    const vencimiento = new Date(`${fecha}T00:00:00`);

    if (Number.isNaN(vencimiento.getTime())) {
        return "";
    }

    const diferencia = vencimiento.getTime() - hoy.getTime();
    const dias = Math.ceil(diferencia / 86_400_000);

    if (dias < 0) {
        return "Vencida";
    }

    if (dias === 0) {
        return "Vence hoy";
    }

    if (dias === 1) {
        return "Vence mañana";
    }

    return `Faltan ${dias} días`;
}

onMounted(cargar);
</script>
