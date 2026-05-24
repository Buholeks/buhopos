<template>
    <div class="min-h-screen bg-slate-50">
        <!-- TOPBAR -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700"
                    >
                        <History class="h-5 w-5" />
                    </div>
                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900"
                        >
                            Historial de cortes
                        </h1>
                        <p class="text-xs text-slate-500">
                            Todos los turnos registrados
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-6 py-6">
            <!-- FILTROS -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-600"
                        >Estado</span
                    >
                    <select
                        v-model="filtroEstado"
                        @change="onChangeFiltro"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
                    >
                        <option value="">Todos</option>
                        <option value="abierto">Abierto</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>
            </div>

            <!-- TABLA -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    v-if="cargando"
                    class="flex items-center justify-center py-20 text-slate-400"
                >
                    <Loader2 class="h-6 w-6 animate-spin" />
                </div>

                <table v-else class="w-full text-sm">
                    <thead
                        class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        <tr>
                            <th class="px-4 py-3 text-left">Apertura</th>
                            <th class="px-4 py-3 text-left">Cierre</th>
                            <th class="px-4 py-3 text-left">Usuario</th>
                            <th class="px-4 py-3 text-right">Ventas</th>
                            <th class="px-4 py-3 text-right">Efectivo</th>
                            <th class="px-4 py-3 text-right">Tarjeta</th>
                            <th class="px-4 py-3 text-right">Transferencia</th>
                            <th class="px-4 py-3 text-right">Crédito</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="cortes.length === 0">
                            <td
                                colspan="10"
                                class="py-12 text-center text-slate-400"
                            >
                                Sin cortes registrados
                            </td>
                        </tr>

                        <tr
                            v-for="c in cortes"
                            :key="c.id"
                            class="transition-colors hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 text-slate-700">
                                {{ formatFecha(c.fecha_apertura) }}
                            </td>

                            <td class="px-4 py-3 text-slate-500">
                                {{
                                    c.fecha_cierre
                                        ? formatFecha(c.fecha_cierre)
                                        : "—"
                                }}
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                {{ c.user?.name ?? "—" }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-semibold text-slate-900"
                            >
                                {{ c.num_ventas ?? 0 }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-medium text-emerald-700"
                            >
                                {{ fmt(c.ventas_efectivo) }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-medium text-blue-700"
                            >
                                {{ fmt(c.ventas_tarjeta) }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-medium text-violet-700"
                            >
                                {{ fmt(c.ventas_transferencia) }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-medium text-orange-700"
                            >
                                {{ fmt(c.ventas_credito) }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                    :class="
                                        c.estado === 'abierto'
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                >
                                    {{
                                        c.estado === "abierto"
                                            ? "Abierto"
                                            : "Cerrado"
                                    }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    @click="verDetalle(c.id)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    Ver detalle
                                    <ChevronRight
                                        class="h-3.5 w-3.5 opacity-60"
                                    />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <div
                v-if="meta.last_page > 1"
                class="mt-4 flex items-center justify-between text-sm text-slate-600"
            >
                <span
                    >Página {{ meta.current_page }} de
                    {{ meta.last_page }}</span
                >

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="meta.current_page === 1"
                        @click="prevPage"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        Anterior
                    </button>

                    <button
                        type="button"
                        :disabled="meta.current_page === meta.last_page"
                        @click="nextPage"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-40"
                    >
                        Siguiente
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import {
    History,
    Eye,
    Loader2,
    ChevronLeft,
    ChevronRight,
} from "lucide-vue-next";
import { useRouter } from "vue-router";

const router = useRouter();
const cargando = ref(true);
const cortes = ref([]);

const filtroEstado = ref("");
const pagina = ref(1);
const meta = ref({ current_page: 1, last_page: 1 });

onMounted(cargar);

function onChangeFiltro() {
    pagina.value = 1; // importante para no quedar en páginas “fantasma”
    cargar();
}

function verDetalle(id) {
    router.push({
        name: "corte-detalle",
        params: { id },
    });
}

function prevPage() {
    if (meta.value.current_page === 1) return;
    pagina.value--;
    cargar();
}

function nextPage() {
    if (meta.value.current_page === meta.value.last_page) return;
    pagina.value++;
    cargar();
}

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/cortes-caja", {
            params: {
                estado: filtroEstado.value || undefined,
                page: pagina.value,
                por_pagina: 20,
            },
        });

        cortes.value = data.data ?? [];
        meta.value = {
            current_page: data.current_page ?? 1,
            last_page: data.last_page ?? 1,
        };
    } catch {
        toastError("No se pudo cargar el historial de cortes");
    } finally {
        cargando.value = false;
    }
}

const fmt = (v) =>
    new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(v ?? 0);

const formatFecha = (f) =>
    f
        ? new Date(f).toLocaleString("es-MX", {
              dateStyle: "short",
              timeStyle: "short",
          })
        : "—";
</script>
