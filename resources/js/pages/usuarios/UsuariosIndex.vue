<template>
    <main class="space-y-4 p-3 sm:p-6">
        <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <UsersRound class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold">Usuarios</h1>
                    <p class="text-xs text-slate-500">Crea cuentas y asigna manualmente su sucursal inicial.</p>
                </div>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                {{ meta.total }} usuario(s)
            </span>
        </section>

        <div class="grid gap-4 xl:grid-cols-[420px_1fr]">
            <section class="self-start rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold">Crear usuario</h2>
                    <p class="mt-0.5 text-xs text-slate-500">La empresa se asigna desde tu sesión activa.</p>
                </div>

                <form class="space-y-4 p-4" @submit.prevent="crearUsuario">
                    <BaseInput
                        :model-value="auth.empresaNombre"
                        label="Empresa"
                        disabled
                        hint="No puede cambiarse durante el alta."
                    >
                        <template #icon><Building2 class="h-4 w-4" /></template>
                    </BaseInput>

                    <BaseSearchSelect
                        v-model="form.sucursal_id"
                        label="Sucursal inicial"
                        placeholder="Selecciona una sucursal"
                        :items="sucursales"
                        label-key="nombre"
                        sub-label-key="direccion"
                        value-key="id"
                        :error="fieldError('sucursal_id')"
                        required
                    />

                    <BaseInput
                        v-model="form.name"
                        label="Nombre"
                        placeholder="Nombre completo"
                        :error="fieldError('name')"
                        required
                    >
                        <template #icon><UserRound class="h-4 w-4" /></template>
                    </BaseInput>

                    <BaseInput
                        v-model="form.email"
                        label="Correo de acceso"
                        type="email"
                        placeholder="usuario@empresa.com"
                        autocomplete="off"
                        :error="fieldError('email')"
                        required
                    >
                        <template #icon><Mail class="h-4 w-4" /></template>
                    </BaseInput>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <BaseInput
                            v-model="form.password"
                            label="Contraseña"
                            type="password"
                            autocomplete="new-password"
                            :error="fieldError('password')"
                            required
                        >
                            <template #icon><LockKeyhole class="h-4 w-4" /></template>
                        </BaseInput>
                        <BaseInput
                            v-model="form.password_confirmation"
                            label="Confirmar contraseña"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                            <template #icon><LockKeyhole class="h-4 w-4" /></template>
                        </BaseInput>
                    </div>

                    <div v-if="mensajeError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ mensajeError }}
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="guardando || !puedeGuardar"
                    >
                        <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                        <UserPlus v-else class="h-4 w-4" />
                        {{ guardando ? "Creando..." : "Crear usuario" }}
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">Usuarios de {{ auth.empresaNombre }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">La sucursal mostrada es la sucursal inicial de acceso.</p>
                    </div>
                    <BaseInput v-model="busqueda" placeholder="Buscar nombre o correo" root-class="w-full sm:w-72" @input="debounceBuscar">
                        <template #icon><Search class="h-4 w-4" /></template>
                    </BaseInput>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Usuario</th>
                                <th class="px-4 py-3 text-left">Sucursal inicial</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-left">Creado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="usuario in usuarios" :key="usuario.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ usuario.name }}</p>
                                    <p class="text-xs text-slate-500">{{ usuario.email }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ usuario.sucursal?.nombre ?? "Sin sucursal" }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="usuario.activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ usuario.activo ? "Activo" : "Inactivo" }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ fmtFecha(usuario.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="cargando" class="border-t border-slate-100 px-4 py-8 text-center text-sm text-slate-400">
                    <Loader2 class="mx-auto mb-2 h-5 w-5 animate-spin" />
                    Cargando usuarios...
                </div>
                <div v-else-if="!usuarios.length" class="border-t border-slate-100 px-4 py-10 text-center text-sm text-slate-400">
                    No hay usuarios registrados.
                </div>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import http from "@/lib/http";
import { toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import { Building2, Loader2, LockKeyhole, Mail, Search, UserPlus, UserRound, UsersRound } from "lucide-vue-next";

const auth = useAuthStore();
const sucursales = ref([]);
const usuarios = ref([]);
const cargando = ref(false);
const guardando = ref(false);
const busqueda = ref("");
const errors = ref({});
const mensajeError = ref("");
const meta = ref({ total: 0 });
let timer;

const form = reactive({
    sucursal_id: null,
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const puedeGuardar = computed(() =>
    !!form.sucursal_id &&
    form.name.trim().length >= 2 &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email) &&
    form.password.length >= 8 &&
    form.password === form.password_confirmation
);

onMounted(async () => {
    await Promise.all([cargarSucursales(), cargarUsuarios()]);
});

async function cargarSucursales() {
    const { data } = await http.get("/api/users/sucursales-disponibles");
    sucursales.value = data ?? [];
}

async function cargarUsuarios() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/users", { params: { q: busqueda.value || undefined } });
        usuarios.value = data.data ?? [];
        meta.value = { total: data.total ?? 0 };
    } finally {
        cargando.value = false;
    }
}

function debounceBuscar() {
    clearTimeout(timer);
    timer = setTimeout(cargarUsuarios, 350);
}

async function crearUsuario() {
    guardando.value = true;
    errors.value = {};
    mensajeError.value = "";
    try {
        const { data } = await http.post("/api/users", form);
        toastSuccess(`Usuario ${data.name} creado`);
        limpiarFormulario();
        await cargarUsuarios();
    } catch (error) {
        errors.value = error?.response?.data?.errors ?? {};
        mensajeError.value = error?.response?.data?.message || "No se pudo crear el usuario.";
    } finally {
        guardando.value = false;
    }
}

function limpiarFormulario() {
    form.sucursal_id = null;
    form.name = "";
    form.email = "";
    form.password = "";
    form.password_confirmation = "";
}

function fieldError(campo) {
    return errors.value?.[campo]?.[0] ?? "";
}

function fmtFecha(valor) {
    if (!valor) return "—";
    return new Date(valor).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
}
</script>
