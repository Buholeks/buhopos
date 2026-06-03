<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="flex items-center justify-center bg-slate-50 p-6">
            <div class="w-full max-w-xl">
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-6">
                        <h1 class="text-xl font-semibold text-slate-900">
                            Registrar empresa
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            La cuenta quedará pendiente de activación manual.
                        </p>
                    </div>

                    <form v-if="!success" @submit.prevent="submit" class="space-y-5 p-6">
                        <section class="space-y-3">
                            <h2 class="text-sm font-semibold text-slate-900">Empresa</h2>
                            <BaseInput v-model="form.empresa_nombre" label="Nombre de la empresa" placeholder="Mi negocio" :error="fieldError('empresa_nombre')" required>
                                <template #icon><Building2 class="h-4 w-4" /></template>
                            </BaseInput>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <BaseInput v-model="form.empresa_correo" label="Correo de contacto" type="email" placeholder="contacto@empresa.com" :error="fieldError('empresa_correo')">
                                    <template #icon><Mail class="h-4 w-4" /></template>
                                </BaseInput>
                                <BaseInput v-model="form.empresa_telefono" label="Teléfono" placeholder="5551234567" :error="fieldError('empresa_telefono')">
                                    <template #icon><Phone class="h-4 w-4" /></template>
                                </BaseInput>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h2 class="text-sm font-semibold text-slate-900">Sucursal inicial</h2>
                            <BaseInput v-model="form.sucursal_nombre" label="Nombre de la sucursal" placeholder="Matriz" :error="fieldError('sucursal_nombre')" required>
                                <template #icon><Store class="h-4 w-4" /></template>
                            </BaseInput>
                            <BaseInput v-model="form.sucursal_direccion" label="Dirección" placeholder="Calle, número, colonia" :error="fieldError('sucursal_direccion')">
                                <template #icon><MapPin class="h-4 w-4" /></template>
                            </BaseInput>
                        </section>

                        <section class="space-y-3">
                            <h2 class="text-sm font-semibold text-slate-900">Usuario administrador</h2>
                            <BaseInput v-model="form.usuario_nombre" label="Nombre" placeholder="Nombre completo" :error="fieldError('usuario_nombre')" required>
                                <template #icon><UserRound class="h-4 w-4" /></template>
                            </BaseInput>
                            <BaseInput v-model="form.email" label="Correo para iniciar sesión" type="email" placeholder="tu@correo.com" autocomplete="email" :error="fieldError('email')" required>
                                <template #icon><Mail class="h-4 w-4" /></template>
                            </BaseInput>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <BaseInput v-model="form.password" label="Contraseña" type="password" autocomplete="new-password" :error="fieldError('password')" required>
                                    <template #icon><LockKeyhole class="h-4 w-4" /></template>
                                </BaseInput>
                                <BaseInput v-model="form.password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" :error="fieldError('password_confirmation')" required>
                                    <template #icon><LockKeyhole class="h-4 w-4" /></template>
                                </BaseInput>
                            </div>
                        </section>

                        <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">
                            {{ error }}
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200 disabled:opacity-70" :disabled="loading || !canSubmit">
                                <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                                <UserPlus v-else class="h-4 w-4" />
                                {{ loading ? "Registrando..." : "Enviar registro" }}
                            </button>

                            <RouterLink :to="{ name: 'login' }" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Volver
                            </RouterLink>
                        </div>
                    </form>

                    <div v-else class="space-y-4 p-6">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                            <div class="flex items-start gap-3">
                                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <p class="font-semibold">Registro recibido</p>
                                    <p class="mt-1 text-sm">
                                        Tu empresa, sucursal y usuario fueron creados. La cuenta está inactiva hasta aprobación manual.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <RouterLink :to="{ name: 'login' }" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Ir al inicio de sesión
                        </RouterLink>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative hidden overflow-hidden border-l border-slate-200 bg-slate-950 lg:block">
            <div class="relative flex h-full flex-col justify-center p-12 text-white">
                <div class="mb-8 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10">
                        <Package class="h-6 w-6" />
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold">BuhoPOS</h2>
                        <p class="text-sm text-white/70">Alta controlada de nuevos clientes</p>
                    </div>
                </div>

                <p class="max-w-md text-sm leading-relaxed text-white/60">
                    El registro crea la estructura inicial del negocio, pero el acceso queda bloqueado hasta que un administrador active la cuenta.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import {
    Building2,
    CheckCircle2,
    Loader2,
    LockKeyhole,
    Mail,
    MapPin,
    Package,
    Phone,
    Store,
    UserPlus,
    UserRound,
} from "lucide-vue-next";

const form = reactive({
    empresa_nombre: "",
    empresa_correo: "",
    empresa_telefono: "",
    sucursal_nombre: "",
    sucursal_direccion: "",
    usuario_nombre: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const loading = ref(false);
const success = ref(false);
const error = ref("");
const errors = ref({});

const canSubmit = computed(() => {
    return (
        form.empresa_nombre.trim().length >= 2 &&
        form.sucursal_nombre.trim().length >= 2 &&
        form.usuario_nombre.trim().length >= 2 &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email) &&
        form.password.length >= 8 &&
        form.password === form.password_confirmation
    );
});

function fieldError(field) {
    return errors.value?.[field]?.[0] ?? "";
}

async function submit() {
    loading.value = true;
    error.value = "";
    errors.value = {};

    try {
        await http.get("/sanctum/csrf-cookie");
        await http.post("/api/register", form);
        success.value = true;
    } catch (e) {
        errors.value = e?.response?.data?.errors ?? {};
        error.value = e?.response?.data?.message || "No se pudo completar el registro.";
    } finally {
        loading.value = false;
    }
}
</script>
