<template>
    <div class="space-y-4">
        <!-- Formulario -->
        <form
            class="rounded-xl border border-slate-200 bg-white"
            @submit.prevent="crear"
        >
            <div class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-sm font-semibold text-slate-900">
                        Nuevo código promocional
                    </h1>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Cada código puede canjearse una sola vez.
                    </p>
                </div>

                <div class="mt-2 flex items-center gap-2 sm:mt-0">
                    <span class="rounded-md bg-emerald-50 px-2 py-1 text-[10px] font-medium text-emerald-700">
                        Un solo uso
                    </span>
                </div>
            </div>

            <div class="p-4">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <!-- Código -->
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Código
                        </span>

                        <div class="relative">
                            <Ticket
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />

                            <input
                                v-model.trim="form.codigo"
                                type="text"
                                placeholder="Vacío para generar automáticamente"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </div>
                    </label>

                    <!-- Plan -->
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Plan
                            <span class="text-rose-500">*</span>
                        </span>

                        <select
                            v-model="form.plan_id"
                            required
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >
                            <option value="">Selecciona un plan</option>

                            <option
                                v-for="plan in datos.planes"
                                :key="plan.id"
                                :value="plan.id"
                            >
                                {{ plan.nombre }}
                            </option>
                        </select>
                    </label>

                    <!-- Empresa -->
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Empresa destinataria
                        </span>

                        <select
                            v-model="form.empresa_destino_id"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >
                            <option value="">Cualquier empresa</option>

                            <option
                                v-for="empresa in datos.empresas"
                                :key="empresa.id"
                                :value="empresa.id"
                            >
                                {{ empresa.nombre }}
                            </option>
                        </select>
                    </label>

                    <!-- Duración -->
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Duración
                            <span class="text-rose-500">*</span>
                        </span>

                        <input
                            v-model.number="form.duracion_cantidad"
                            type="number"
                            min="1"
                            max="365"
                            required
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <!-- Unidad -->
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Unidad
                        </span>

                        <select
                            v-model="form.duracion_tipo"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >
                            <option value="dias">Días</option>
                            <option value="meses">Meses</option>
                        </select>
                    </label>

                    <!-- Expiración -->
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Disponible hasta
                        </span>

                        <input
                            v-model="form.expira_en"
                            type="datetime-local"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <!-- Descripción -->
                    <label class="block md:col-span-2 xl:col-span-3">
                        <span class="mb-1 block text-xs font-medium text-slate-600">
                            Descripción
                        </span>

                        <input
                            v-model.trim="form.descripcion"
                            type="text"
                            placeholder="Ej. Cortesía, demostración o cuenta interna"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>
                </div>

                <!-- Error -->
                <div
                    v-if="error"
                    class="mt-3 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"
                >
                    <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <!-- Acciones -->
                <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        :disabled="guardando"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Loader2
                            v-if="guardando"
                            class="h-4 w-4 animate-spin"
                        />

                        <Plus v-else class="h-4 w-4" />

                        {{ guardando ? "Generando..." : "Generar código" }}
                    </button>
                </div>
            </div>
        </form>

        <!-- Listado -->
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Códigos generados
                    </h2>

                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ datos.codigos.length }}
                        {{ datos.codigos.length === 1 ? "registro" : "registros" }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
                    @click="cargar"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    Actualizar
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                Código
                            </th>

                            <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                Beneficio
                            </th>

                            <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                Destino
                            </th>

                            <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                Estado
                            </th>

                            <th class="w-28 px-4 py-2.5 text-right text-xs font-medium text-slate-500">
                                Acción
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="codigo in datos.codigos"
                            :key="codigo.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <!-- Código -->
                            <td class="px-4 py-3 align-top">
                                <button
                                    type="button"
                                    class="group inline-flex items-center gap-1.5 font-mono text-sm font-semibold text-emerald-700 hover:text-emerald-800"
                                    title="Copiar código"
                                    @click="copiar(codigo.codigo)"
                                >
                                    {{ codigo.codigo }}

                                    <Copy
                                        class="h-3.5 w-3.5 text-slate-400 opacity-0 transition group-hover:opacity-100"
                                    />
                                </button>

                                <p
                                    v-if="codigo.descripcion"
                                    class="mt-1 max-w-xs truncate text-xs text-slate-400"
                                    :title="codigo.descripcion"
                                >
                                    {{ codigo.descripcion }}
                                </p>
                            </td>

                            <!-- Beneficio -->
                            <td class="px-4 py-3 align-top text-sm text-slate-700">
                                <p class="font-medium text-slate-800">
                                    {{ codigo.duracion_cantidad }}
                                    {{ textoDuracion(codigo) }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ codigo.plan?.nombre || "Sin plan" }}
                                </p>
                            </td>

                            <!-- Destino -->
                            <td class="px-4 py-3 align-top">
                                <div class="flex items-center gap-2 text-sm text-slate-700">
                                    <Building2 class="h-4 w-4 shrink-0 text-slate-400" />

                                    <span class="max-w-48 truncate">
                                        {{
                                            codigo.empresa_destino?.nombre ||
                                            "Cualquier empresa"
                                        }}
                                    </span>
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-4 py-3 align-top">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-medium"
                                    :class="estado(codigo).clase"
                                >
                                    {{ estado(codigo).texto }}
                                </span>

                                <p
                                    v-if="codigo.empresa_canje"
                                    class="mt-1 max-w-48 truncate text-xs text-slate-500"
                                >
                                    Canjeado por {{ codigo.empresa_canje.nombre }}
                                </p>

                                <p
                                    v-else-if="codigo.expira_en"
                                    class="mt-1 text-xs text-slate-400"
                                >
                                    Expira {{ formatearFecha(codigo.expira_en) }}
                                </p>
                            </td>

                            <!-- Acción -->
                            <td class="px-4 py-3 text-right align-top">
                                <button
                                    v-if="!codigo.canjeado_en"
                                    type="button"
                                    class="inline-flex h-8 items-center justify-center rounded-lg px-3 text-xs font-medium transition"
                                    :class="
                                        codigo.activo
                                            ? 'text-rose-600 hover:bg-rose-50'
                                            : 'text-emerald-700 hover:bg-emerald-50'
                                    "
                                    @click="alternar(codigo)"
                                >
                                    {{ codigo.activo ? "Desactivar" : "Activar" }}
                                </button>

                                <span
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    Sin acciones
                                </span>
                            </td>
                        </tr>

                        <tr v-if="!datos.codigos.length">
                            <td colspan="5" class="px-4 py-12">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100">
                                        <Ticket class="h-5 w-5 text-slate-400" />
                                    </div>

                                    <p class="mt-3 text-sm font-medium text-slate-600">
                                        No hay códigos promocionales
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400">
                                        Genera el primer código desde el formulario.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import {
    Building2,
    CircleAlert,
    Copy,
    Loader2,
    Plus,
    RefreshCw,
    Ticket,
} from "lucide-vue-next";

import http from "@/lib/http";

const datos = reactive({
    codigos: [],
    planes: [],
    empresas: [],
});

const guardando = ref(false);
const error = ref("");

const inicial = () => ({
    codigo: "",
    descripcion: "",
    plan_id: "",
    empresa_destino_id: "",
    duracion_tipo: "dias",
    duracion_cantidad: 30,
    expira_en: "",
});

const form = reactive(inicial());

async function cargar() {
    try {
        const { data } = await http.get(
            "/api/plataforma/codigos-promocionales",
        );

        Object.assign(datos, data);
    } catch (e) {
        error.value =
            e?.response?.data?.message ||
            "No se pudieron cargar los códigos promocionales.";
    }
}

async function crear() {
    if (guardando.value) {
        return;
    }

    guardando.value = true;
    error.value = "";

    try {
        const payload = {
            ...form,
            codigo: form.codigo || null,
            empresa_destino_id: form.empresa_destino_id || null,
            expira_en: form.expira_en || null,
        };

        const { data } = await http.post(
            "/api/plataforma/codigos-promocionales",
            payload,
        );

        Object.assign(form, inicial());

        await cargar();
        await copiar(data.codigo);
    } catch (e) {
        error.value =
            Object.values(e?.response?.data?.errors || {})
                .flat()
                .join(" ") ||
            e?.response?.data?.message ||
            "No se pudo generar el código.";
    } finally {
        guardando.value = false;
    }
}

async function alternar(codigo) {
    try {
        await http.put(
            `/api/plataforma/codigos-promocionales/${codigo.id}`,
            {
                activo: !codigo.activo,
            },
        );

        await cargar();
    } catch (e) {
        error.value =
            e?.response?.data?.message ||
            "No se pudo actualizar el código.";
    }
}

async function copiar(codigo) {
    try {
        await navigator.clipboard.writeText(codigo);
    } catch {
        error.value = "No se pudo copiar el código al portapapeles.";
    }
}

function estado(codigo) {
    if (codigo.canjeado_en) {
        return {
            texto: "Canjeado",
            clase: "bg-violet-50 text-violet-700",
        };
    }

    if (
        codigo.expira_en &&
        new Date(codigo.expira_en).getTime() < Date.now()
    ) {
        return {
            texto: "Caducado",
            clase: "bg-amber-50 text-amber-700",
        };
    }

    if (codigo.activo) {
        return {
            texto: "Disponible",
            clase: "bg-emerald-50 text-emerald-700",
        };
    }

    return {
        texto: "Inactivo",
        clase: "bg-slate-100 text-slate-500",
    };
}

function textoDuracion(codigo) {
    const cantidad = Number(codigo.duracion_cantidad);
    const tipo = codigo.duracion_tipo;

    if (tipo === "meses") {
        return cantidad === 1 ? "mes" : "meses";
    }

    return cantidad === 1 ? "día" : "días";
}

function formatearFecha(fecha) {
    if (!fecha) {
        return "";
    }

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(fecha));
}

onMounted(cargar);
</script>