```vue
<template>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <!-- Encabezado -->
        <div
            class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-sm font-semibold text-slate-900">
                    Empresas clientes
                </h1>

                <p class="mt-0.5 text-xs text-slate-500">
                    Administra suscripciones, sucursales, usuarios y acceso.
                </p>
            </div>

            <div class="relative w-full sm:w-80">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />

                <input
                    v-model.trim="q"
                    type="search"
                    placeholder="Buscar empresa, correo o RFC"
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-9 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    @input="buscar"
                />

                <button
                    v-if="q"
                    type="button"
                    class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    title="Limpiar búsqueda"
                    @click="limpiarBusqueda"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="flex items-start gap-2 border-b border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700"
        >
            <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />

            <span class="flex-1">
                {{ error }}
            </span>

            <button
                type="button"
                class="font-medium hover:text-rose-900"
                @click="cargar"
            >
                Reintentar
            </button>
        </div>

        <!-- Tabla -->
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                            Empresa
                        </th>

                        <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                            Plan
                        </th>

                        <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                            Suscripción
                        </th>

                        <th class="px-4 py-2.5 text-center text-xs font-medium text-slate-500">
                            Sucursales
                        </th>

                        <th class="px-4 py-2.5 text-center text-xs font-medium text-slate-500">
                            Usuarios
                        </th>

                        <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                            Acceso
                        </th>

                        <th class="w-32 px-4 py-2.5 text-right text-xs font-medium text-slate-500">
                            Acción
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <!-- Esqueleto -->
                    <template v-if="cargando">
                        <tr
                            v-for="fila in 5"
                            :key="fila"
                        >
                            <td class="px-4 py-3">
                                <div class="h-4 w-40 animate-pulse rounded bg-slate-100" />
                                <div class="mt-2 h-3 w-28 animate-pulse rounded bg-slate-100" />
                            </td>

                            <td class="px-4 py-3">
                                <div class="h-4 w-24 animate-pulse rounded bg-slate-100" />
                            </td>

                            <td class="px-4 py-3">
                                <div class="h-6 w-20 animate-pulse rounded-full bg-slate-100" />
                            </td>

                            <td class="px-4 py-3">
                                <div class="mx-auto h-4 w-6 animate-pulse rounded bg-slate-100" />
                            </td>

                            <td class="px-4 py-3">
                                <div class="mx-auto h-4 w-6 animate-pulse rounded bg-slate-100" />
                            </td>

                            <td class="px-4 py-3">
                                <div class="h-6 w-16 animate-pulse rounded-full bg-slate-100" />
                            </td>

                            <td class="px-4 py-3">
                                <div class="ml-auto h-7 w-24 animate-pulse rounded bg-slate-100" />
                            </td>
                        </tr>
                    </template>

                    <!-- Registros -->
                    <tr
                        v-for="empresa in empresas"
                        v-else
                        :key="empresa.id"
                        class="transition hover:bg-slate-50/70"
                    >
                        <!-- Empresa -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                                >
                                    <Building2 class="h-4 w-4" />
                                </div>

                                <div class="min-w-0">
                                    <p class="max-w-64 truncate font-medium text-slate-800">
                                        {{ empresa.nombre }}
                                    </p>

                                    <p class="mt-0.5 max-w-64 truncate text-xs text-slate-500">
                                        {{ empresa.correo || "Sin correo registrado" }}
                                    </p>

                                    <p
                                        v-if="empresa.rfc"
                                        class="mt-0.5 text-[10px] text-slate-400"
                                    >
                                        RFC: {{ empresa.rfc }}
                                    </p>
                                </div>
                            </div>
                        </td>

                        <!-- Plan -->
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-700">
                                {{
                                    empresa.suscripcion?.plan?.nombre ||
                                    "Sin plan"
                                }}
                            </p>

                            <p
                                v-if="empresa.suscripcion?.fecha_vencimiento"
                                class="mt-0.5 text-xs text-slate-400"
                            >
                                Vence
                                {{
                                    formatearFecha(
                                        empresa.suscripcion.fecha_vencimiento,
                                    )
                                }}
                            </p>
                        </td>

                        <!-- Suscripción -->
                        <td class="px-4 py-3">
                            <EstadoBadge
                                :estado="empresa.suscripcion?.estado"
                            />
                        </td>

                        <!-- Sucursales -->
                        <td class="px-4 py-3 text-center">
                            <span
                                class="inline-flex min-w-8 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600"
                            >
                                {{ empresa.sucursales_count || 0 }}
                            </span>
                        </td>

                        <!-- Usuarios -->
                        <td class="px-4 py-3 text-center">
                            <span
                                class="inline-flex min-w-8 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600"
                            >
                                {{ empresa.usuarios_count || 0 }}
                            </span>
                        </td>

                        <!-- Acceso -->
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-medium"
                                :class="
                                    empresa.activo
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-rose-50 text-rose-700'
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        empresa.activo
                                            ? 'bg-emerald-500'
                                            : 'bg-rose-500'
                                    "
                                />

                                {{ empresa.activo ? "Activo" : "Suspendido" }}
                            </span>
                        </td>

                        <!-- Acción -->
                        <td class="px-4 py-3 text-right">
                            <RouterLink
                                :to="{
                                    name: 'plataforma-empresa-detalle',
                                    params: {
                                        id: empresa.id,
                                    },
                                }"
                                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50"
                            >
                                Administrar
                                <ChevronRight class="h-3.5 w-3.5" />
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Vacío -->
        <div
            v-if="!cargando && !empresas.length"
            class="flex flex-col items-center justify-center border-t border-slate-100 px-4 py-12 text-center"
        >
            <div
                class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100"
            >
                <Building2 class="h-5 w-5 text-slate-400" />
            </div>

            <p class="mt-3 text-sm font-medium text-slate-600">
                No se encontraron empresas
            </p>

            <p class="mt-1 text-xs text-slate-400">
                {{
                    q
                        ? "Prueba con otro nombre, correo o RFC."
                        : "Todavía no hay empresas registradas."
                }}
            </p>

            <button
                v-if="q"
                type="button"
                class="mt-3 rounded-lg px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                @click="limpiarBusqueda"
            >
                Limpiar búsqueda
            </button>
        </div>

        <!-- Pie -->
        <div
            v-if="!cargando && empresas.length"
            class="flex items-center justify-between border-t border-slate-200 bg-slate-50/60 px-4 py-2.5"
        >
            <p class="text-xs text-slate-500">
                {{ empresas.length }}
                {{ empresas.length === 1 ? "empresa encontrada" : "empresas encontradas" }}
            </p>

            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                @click="cargar"
            >
                <RefreshCw class="h-3.5 w-3.5" />
                Actualizar
            </button>
        </div>
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import {
    Building2,
    ChevronRight,
    CircleAlert,
    RefreshCw,
    Search,
    X,
} from "lucide-vue-next";

import http from "@/lib/http";
import EstadoBadge from "../components/EstadoBadge.vue";

const empresas = ref([]);
const q = ref("");
const cargando = ref(false);
const error = ref("");

let timer = null;
let controller = null;

async function cargar() {
    if (controller) {
        controller.abort();
    }

    controller = new AbortController();
    cargando.value = true;
    error.value = "";

    try {
        const { data } = await http.get("/api/plataforma/empresas", {
            params: {
                q: q.value || undefined,
            },
            signal: controller.signal,
        });

        empresas.value = data.data || [];
    } catch (e) {
        if (
            e?.name === "CanceledError" ||
            e?.code === "ERR_CANCELED"
        ) {
            return;
        }

        error.value =
            e?.response?.data?.message ||
            "No se pudieron cargar las empresas.";
    } finally {
        cargando.value = false;
        controller = null;
    }
}

function buscar() {
    clearTimeout(timer);

    timer = setTimeout(() => {
        cargar();
    }, 300);
}

function limpiarBusqueda() {
    q.value = "";
    clearTimeout(timer);
    cargar();
}

function formatearFecha(fecha) {
    if (!fecha) {
        return "";
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

onMounted(cargar);

onBeforeUnmount(() => {
    clearTimeout(timer);

    if (controller) {
        controller.abort();
    }
});
</script>
```
