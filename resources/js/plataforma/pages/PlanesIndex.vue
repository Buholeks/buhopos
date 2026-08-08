<template>
    <div class="grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
        <!-- Formulario -->
        <form
            class="self-start rounded-xl border border-slate-200 bg-white"
            @submit.prevent="guardar"
        >
            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-3"
            >
                <div>
                    <h1 class="text-sm font-semibold text-slate-900">
                        {{ editando ? "Editar plan" : "Nuevo plan" }}
                    </h1>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Configura precios y límites del servicio.
                    </p>
                </div>

                <button
                    v-if="editando"
                    type="button"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    title="Cancelar edición"
                    @click="nuevo"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="space-y-3 p-4">
                <!-- Nombre -->
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-600">
                        Nombre
                        <span class="text-rose-500">*</span>
                    </span>

                    <input
                        v-model.trim="form.nombre"
                        type="text"
                        required
                        placeholder="Ej. Plan Profesional"
                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <!-- Descripción -->
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-600">
                        Descripción
                    </span>

                    <textarea
                        v-model.trim="form.descripcion"
                        rows="2"
                        placeholder="Descripción breve del plan"
                        class="w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </label>

                <!-- Precio -->
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-600">
                        Precio mensual
                        <span class="text-rose-500">*</span>
                    </span>

                    <div class="relative">
                        <span
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                        >
                            $
                        </span>

                        <input
                            v-model.number="form.precio_mensual"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-12 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />

                        <span
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"
                        >
                            MXN
                        </span>
                    </div>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <!-- Sucursales -->
                    <label class="block">
                        <span
                            class="mb-1 block text-xs font-medium text-slate-600"
                        >
                            Sucursales
                        </span>

                        <input
                            v-model.number="form.sucursales_incluidas"
                            type="number"
                            min="1"
                            required
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>

                    <!-- Usuarios -->
                    <label class="block">
                        <span
                            class="mb-1 block text-xs font-medium text-slate-600"
                        >
                            Usuarios
                        </span>

                        <input
                            v-model.number="form.usuarios_incluidos"
                            type="number"
                            min="1"
                            placeholder="Ilimitados"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />
                    </label>
                </div>

                <!-- Sucursal adicional -->
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-600">
                        Precio por sucursal adicional
                    </span>

                    <div class="relative">
                        <span
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                        >
                            $
                        </span>

                        <input
                            v-model.number="form.precio_sucursal_adicional"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-12 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        />

                        <span
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"
                        >
                            MXN
                        </span>
                    </div>
                </label>

                <!-- Activo -->
                <label
                    class="flex cursor-pointer items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5"
                >
                    <div>
                        <p class="text-xs font-medium text-slate-700">
                            Plan activo
                        </p>

                        <p class="mt-0.5 text-[10px] text-slate-400">
                            Disponible para nuevas suscripciones
                        </p>
                    </div>

                    <input
                        v-model="form.activo"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                    />
                </label>

                <!-- Error -->
                <div
                    v-if="errorFormulario"
                    class="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"
                >
                    <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ errorFormulario }}
                </div>

                <!-- Botones -->
                <div
                    class="flex justify-end gap-2 border-t border-slate-100 pt-4"
                >
                    <button
                        v-if="editando"
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
                        @click="nuevo"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        :disabled="guardando"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Loader2
                            v-if="guardando"
                            class="h-4 w-4 animate-spin"
                        />

                        <Save v-else class="h-4 w-4" />

                        {{
                            guardando
                                ? "Guardando..."
                                : editando
                                  ? "Guardar cambios"
                                  : "Crear plan"
                        }}
                    </button>
                </div>
            </div>
        </form>

        <!-- Listado -->
        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3"
            >
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Planes disponibles
                    </h2>

                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ planes.length }}
                        {{
                            planes.length === 1
                                ? "plan registrado"
                                : "planes registrados"
                        }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                    :disabled="cargando"
                    @click="cargar"
                >
                    <RefreshCw
                        class="h-3.5 w-3.5"
                        :class="{ 'animate-spin': cargando }"
                    />
                    Actualizar
                </button>
            </div>

            <!-- Error -->
            <div
                v-if="errorCarga"
                class="flex items-start gap-2 border-b border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700"
            >
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />

                <span class="flex-1">
                    {{ errorCarga }}
                </span>

                <button
                    type="button"
                    class="font-medium hover:text-rose-900"
                    @click="cargar"
                >
                    Reintentar
                </button>
            </div>

            <!-- Cargando -->
            <div
                v-if="cargando && !planes.length"
                class="flex min-h-48 items-center justify-center"
            >
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <Loader2 class="h-4 w-4 animate-spin" />
                    Cargando planes...
                </div>
            </div>

            <!-- Tabla -->
            <div v-else-if="planes.length" class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-slate-500"
                            >
                                Plan
                            </th>

                            <th
                                class="px-4 py-2.5 text-xs font-medium text-slate-500"
                            >
                                Precio
                            </th>

                            <th
                                class="px-4 py-2.5 text-xs font-medium text-slate-500"
                            >
                                Incluye
                            </th>

                            <th
                                class="px-4 py-2.5 text-xs font-medium text-slate-500"
                            >
                                Suscripciones
                            </th>

                            <th
                                class="px-4 py-2.5 text-xs font-medium text-slate-500"
                            >
                                Stripe
                            </th>

                            <th
                                class="px-4 py-2.5 text-xs font-medium text-slate-500"
                            >
                                Estado
                            </th>

                            <th
                                class="w-40 px-4 py-2.5 text-right text-xs font-medium text-slate-500"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="plan in planes"
                            :key="plan.id"
                            class="transition hover:bg-slate-50/70"
                        >
                            <!-- Plan -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                    >
                                        <WalletCards class="h-4 w-4" />
                                    </div>

                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800">
                                            {{ plan.nombre }}
                                        </p>

                                        <p
                                            v-if="plan.descripcion"
                                            class="mt-0.5 max-w-56 truncate text-xs text-slate-500"
                                            :title="plan.descripcion"
                                        >
                                            {{ plan.descripcion }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Precio -->
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">
                                    {{ dinero.format(plan.precio_mensual) }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-400">
                                    Por mes
                                </p>
                            </td>

                            <!-- Incluye -->
                            <td class="px-4 py-3">
                                <p class="text-xs text-slate-700">
                                    {{ plan.sucursales_incluidas }}
                                    {{
                                        Number(plan.sucursales_incluidas) === 1
                                            ? "sucursal"
                                            : "sucursales"
                                    }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{
                                        plan.usuarios_incluidos
                                            ? `${plan.usuarios_incluidos} usuarios`
                                            : "Usuarios ilimitados"
                                    }}
                                </p>
                            </td>

                            <!-- Suscripciones -->
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex min-w-8 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600"
                                >
                                    {{ plan.suscripciones_count || 0 }}
                                </span>
                            </td>

                            <!-- Stripe -->
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-medium"
                                    :class="
                                        plan.stripe_price_id
                                            ? 'bg-violet-50 text-violet-700'
                                            : 'bg-amber-50 text-amber-700'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            plan.stripe_price_id
                                                ? 'bg-violet-500'
                                                : 'bg-amber-500'
                                        "
                                    />

                                    {{
                                        plan.stripe_price_id
                                            ? "Sincronizado"
                                            : "Pendiente"
                                    }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-medium"
                                    :class="
                                        plan.activo
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            plan.activo
                                                ? 'bg-emerald-500'
                                                : 'bg-slate-400'
                                        "
                                    />

                                    {{ plan.activo ? "Activo" : "Inactivo" }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50"
                                        @click="editar(plan)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        Editar
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-violet-700 transition hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="sincronizando === plan.id"
                                        @click="sincronizar(plan)"
                                    >
                                        <Loader2
                                            v-if="sincronizando === plan.id"
                                            class="h-3.5 w-3.5 animate-spin"
                                        />

                                        <RefreshCw v-else class="h-3.5 w-3.5" />

                                        Stripe
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Vacío -->
            <div
                v-else
                class="flex flex-col items-center justify-center px-4 py-12 text-center"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100"
                >
                    <WalletCards class="h-5 w-5 text-slate-400" />
                </div>

                <p class="mt-3 text-sm font-medium text-slate-600">
                    No hay planes registrados
                </p>

                <p class="mt-1 text-xs text-slate-400">
                    Crea el primer plan desde el formulario.
                </p>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import {
    CircleAlert,
    Loader2,
    Pencil,
    RefreshCw,
    Save,
    WalletCards,
    X,
} from "lucide-vue-next";

import http from "@/lib/http";

const planes = ref([]);
const editando = ref(null);

const cargando = ref(false);
const guardando = ref(false);
const sincronizando = ref(null);

const errorCarga = ref("");
const errorFormulario = ref("");

const dinero = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});

const base = () => ({
    nombre: "",
    descripcion: "",
    precio_mensual: 0,
    sucursales_incluidas: 1,
    usuarios_incluidos: null,
    precio_sucursal_adicional: 0,
    activo: true,
});

const form = reactive(base());

async function cargar() {
    if (cargando.value) {
        return;
    }

    cargando.value = true;
    errorCarga.value = "";

    try {
        const { data } = await http.get("/api/plataforma/planes");
        planes.value = data;
    } catch (error) {
        errorCarga.value =
            obtenerMensajeError(error) || "No se pudieron cargar los planes.";
    } finally {
        cargando.value = false;
    }
}

function nuevo() {
    editando.value = null;
    errorFormulario.value = "";
    Object.assign(form, base());
}

function editar(plan) {
    editando.value = plan.id;
    errorFormulario.value = "";

    Object.assign(form, {
        nombre: plan.nombre || "",
        descripcion: plan.descripcion || "",
        precio_mensual: Number(plan.precio_mensual || 0),
        sucursales_incluidas: Number(plan.sucursales_incluidas || 1),
        usuarios_incluidos:
            plan.usuarios_incluidos === null
                ? null
                : Number(plan.usuarios_incluidos),
        precio_sucursal_adicional: Number(plan.precio_sucursal_adicional || 0),
        activo: Boolean(plan.activo),
    });
}

async function guardar() {
    if (guardando.value) {
        return;
    }

    guardando.value = true;
    errorFormulario.value = "";

    try {
        const url = editando.value
            ? `/api/plataforma/planes/${editando.value}`
            : "/api/plataforma/planes";

        const metodo = editando.value ? "put" : "post";

        const payload = {
            ...form,
            usuarios_incluidos:
                form.usuarios_incluidos === "" ||
                form.usuarios_incluidos === null
                    ? null
                    : Number(form.usuarios_incluidos),
        };

        await http[metodo](url, payload);

        nuevo();
        await cargar();
    } catch (error) {
        errorFormulario.value =
            obtenerMensajeError(error) || "No se pudo guardar el plan.";
    } finally {
        guardando.value = false;
    }
}

async function sincronizar(plan) {
    if (sincronizando.value) {
        return;
    }

    sincronizando.value = plan.id;

    try {
        await http.post(`/api/plataforma/planes/${plan.id}/stripe/sincronizar`);

        await cargar();
    } catch (error) {
        window.alert(
            obtenerMensajeError(error) ||
                "No se pudo sincronizar el plan con Stripe.",
        );
    } finally {
        sincronizando.value = null;
    }
}

function obtenerMensajeError(error) {
    const errores = Object.values(error?.response?.data?.errors || {})
        .flat()
        .join(" ");

    return errores || error?.response?.data?.message || "";
}

onMounted(cargar);
</script>
