<template>
    <main class="mx-auto w-full max-w-6xl space-y-5 p-3 sm:p-5 lg:p-6">
        <header
            class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <RouterLink
                    :to="{ name: 'configuracion' }"
                    class="rounded-xl border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50"
                    ><ArrowLeft class="h-5 w-5"
                /></RouterLink>
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-wider text-emerald-600"
                    >
                        Configuración
                    </p>
                    <h1 class="mt-0.5 text-xl font-bold text-slate-900">
                        Sucursales
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Administra las ubicaciones disponibles para tu empresa.
                    </p>
                </div>
            </div>
            <button
                v-if="datos"
                type="button"
                :disabled="!puedeCrear"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                @click="mostrarFormulario = !mostrarFormulario"
            >
                <Plus class="h-4 w-4" />Nueva sucursal
            </button>
        </header>

        <section
            v-if="cargando"
            class="flex min-h-56 items-center justify-center rounded-2xl border border-slate-200 bg-white"
        >
            <LoaderCircle class="h-7 w-7 animate-spin text-emerald-600" />
        </section>

        <template v-else-if="datos">
            <section class="grid gap-3 sm:grid-cols-3">
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-bold uppercase text-slate-400">
                        Plan actual
                    </p>
                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ datos.suscripcion?.plan?.nombre || "Sin plan" }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-bold uppercase text-slate-400">
                        Sucursales activas
                    </p>
                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ datos.uso.sucursales_activas }} de {{ limiteTexto }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border p-4 shadow-sm"
                    :class="
                        puedeCrear
                            ? 'border-emerald-200 bg-emerald-50'
                            : 'border-amber-200 bg-amber-50'
                    "
                >
                    <p
                        class="text-xs font-bold uppercase"
                        :class="
                            puedeCrear ? 'text-emerald-600' : 'text-amber-600'
                        "
                    >
                        Capacidad
                    </p>
                    <p
                        class="mt-2 text-sm font-semibold"
                        :class="
                            puedeCrear ? 'text-emerald-800' : 'text-amber-800'
                        "
                    >
                        {{
                            puedeCrear
                                ? "Puedes agregar otra sucursal"
                                : "Alcanzaste el límite de tu plan"
                        }}
                    </p>
                    <RouterLink
                        v-if="!puedeCrear"
                        :to="{ name: 'facturacion' }"
                        class="mt-2 inline-block text-xs font-bold text-amber-800 underline"
                        >Ver planes</RouterLink
                    >
                </article>
            </section>

            <form
                v-if="mostrarFormulario"
                class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm"
                @submit.prevent="crearSucursal"
            >
                <div class="mb-4">
                    <h2 class="font-bold text-slate-900">Nueva sucursal</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        La nueva ubicación quedará activa inmediatamente.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block"
                        ><span
                            class="mb-1.5 block text-xs font-semibold text-slate-600"
                            >Nombre</span
                        ><input
                            v-model.trim="form.nombre"
                            required
                            maxlength="255"
                            placeholder="Ej. Sucursal Centro"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    /></label>
                    <label class="block"
                        ><span
                            class="mb-1.5 block text-xs font-semibold text-slate-600"
                            >Teléfono</span
                        ><input
                            v-model.trim="form.telefono"
                            maxlength="30"
                            placeholder="Opcional"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    /></label>
                    <label class="block"
                        ><span
                            class="mb-1.5 block text-xs font-semibold text-slate-600"
                            >Correo</span
                        ><input
                            v-model.trim="form.correo"
                            type="email"
                            maxlength="255"
                            placeholder="Opcional"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    /></label>
                    <label class="block"
                        ><span
                            class="mb-1.5 block text-xs font-semibold text-slate-600"
                            >Dirección</span
                        ><input
                            v-model.trim="form.direccion"
                            maxlength="500"
                            placeholder="Opcional"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    /></label>
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <button
                        type="button"
                        class="h-10 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-600"
                        @click="mostrarFormulario = false"
                    >
                        Cancelar</button
                    ><button
                        type="submit"
                        :disabled="guardando"
                        class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white disabled:opacity-50"
                    >
                        <LoaderCircle
                            v-if="guardando"
                            class="h-4 w-4 animate-spin"
                        /><Store v-else class="h-4 w-4" />{{
                            guardando ? "Creando…" : "Crear sucursal"
                        }}
                    </button>
                </div>
            </form>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="sucursal in datos.sucursales"
                    :key="sucursal.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    :class="!sucursal.activo ? 'opacity-70' : ''"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl"
                            :class="
                                sucursal.activo
                                    ? 'bg-emerald-50 text-emerald-600'
                                    : 'bg-slate-100 text-slate-400'
                            "
                        >
                            <Store class="h-5 w-5" />
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="
                                sucursal.activo
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-slate-100 text-slate-500'
                            "
                            >{{ sucursal.activo ? "Activa" : "Inactiva" }}</span
                        >
                    </div>
                    <h2 class="mt-4 font-bold text-slate-900">
                        {{ sucursal.nombre }}
                    </h2>
                    <div class="mt-3 space-y-2 text-sm text-slate-500">
                        <p class="flex items-start gap-2">
                            <MapPin class="mt-0.5 h-4 w-4 shrink-0" />{{
                                sucursal.direccion || "Sin dirección"
                            }}
                        </p>
                        <p
                            v-if="sucursal.telefono"
                            class="flex items-center gap-2"
                        >
                            <Phone class="h-4 w-4" />{{ sucursal.telefono }}
                        </p>
                        <p
                            v-if="sucursal.correo"
                            class="flex items-center gap-2"
                        >
                            <Mail class="h-4 w-4" />{{ sucursal.correo }}
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="procesandoId === sucursal.id"
                        class="mt-5 inline-flex h-9 w-full items-center justify-center gap-2 rounded-xl border px-3 text-xs font-bold disabled:opacity-50"
                        :class="
                            sucursal.activo
                                ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                        "
                        @click="cambiarEstado(sucursal)"
                    >
                        <LoaderCircle
                            v-if="procesandoId === sucursal.id"
                            class="h-4 w-4 animate-spin"
                        /><CircleOff
                            v-else-if="sucursal.activo"
                            class="h-4 w-4"
                        /><CircleCheck v-else class="h-4 w-4" />{{
                            sucursal.activo ? "Desactivar" : "Activar"
                        }}
                    </button>
                </article>
            </section>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import {
    ArrowLeft,
    CircleCheck,
    CircleOff,
    LoaderCircle,
    Mail,
    MapPin,
    Phone,
    Plus,
    Store,
} from "lucide-vue-next";
import http from "@/lib/http";
import { confirm, error as showError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
const datos = ref(null);
const cargando = ref(true);
const guardando = ref(false);
const procesandoId = ref(null);
const mostrarFormulario = ref(false);
const form = reactive({ nombre: "", direccion: "", telefono: "", correo: "" });

const puedeCrear = computed(
    () =>
        datos.value?.uso?.sucursales_limite == null ||
        datos.value.uso.sucursales_activas < datos.value.uso.sucursales_limite,
);
const limiteTexto = computed(
    () => datos.value?.uso?.sucursales_limite ?? "Ilimitadas",
);

async function cargar() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/facturacion");
        datos.value = data;
    } catch (error) {
        showError(
            "Error",
            error?.response?.data?.message ||
                "No se pudieron cargar las sucursales.",
        );
    } finally {
        cargando.value = false;
    }
}

async function crearSucursal() {
    if (guardando.value || !puedeCrear.value) return;
    guardando.value = true;
    try {
        await http.post("/api/facturacion/sucursales", form);
        Object.assign(form, {
            nombre: "",
            direccion: "",
            telefono: "",
            correo: "",
        });
        mostrarFormulario.value = false;
        await Promise.all([cargar(), auth.fetchSucursales()]);
        toastSuccess("Sucursal creada correctamente");
    } catch (error) {
        mostrarError(error, "No se pudo crear la sucursal.");
    } finally {
        guardando.value = false;
    }
}

async function cambiarEstado(sucursal) {
    const activar = !sucursal.activo;
    const aceptado = await confirm({
        title: activar ? "¿Activar sucursal?" : "¿Desactivar sucursal?",
        text: activar
            ? `Se habilitará ${sucursal.nombre}.`
            : `Los usuarios deberán tener otra sucursal activa asignada.`,
        confirmText: activar ? "Sí, activar" : "Sí, desactivar",
        tone: activar ? "primary" : "warning",
        icon: "question",
    });
    if (!aceptado) return;
    procesandoId.value = sucursal.id;
    try {
        await http.put(`/api/facturacion/sucursales/${sucursal.id}`, {
            activo: activar,
        });
        await Promise.all([cargar(), auth.fetchSucursales()]);
        toastSuccess(activar ? "Sucursal activada" : "Sucursal desactivada");
    } catch (error) {
        mostrarError(error, "No se pudo actualizar la sucursal.");
    } finally {
        procesandoId.value = null;
    }
}

function mostrarError(error, defecto) {
    const errores = error?.response?.data?.errors;
    showError(
        "No se pudo completar",
        errores
            ? Object.values(errores).flat().join(" ")
            : error?.response?.data?.message || defecto,
    );
}

onMounted(cargar);
</script>
