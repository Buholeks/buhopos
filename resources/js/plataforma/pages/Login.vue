<template>
    <div
        class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-8"
    >
        <div class="w-full max-w-sm">
            <!-- Identidad -->
            <div class="mb-5 text-center">
                <div
                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500 text-slate-950"
                >
                    <ShieldCheck class="h-5 w-5" />
                </div>

                <div class="mt-3 flex items-center justify-center gap-2">
                    <span class="text-lg font-semibold text-white">
                        BuhoPOS
                    </span>

                    <span
                        class="rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-emerald-300"
                    >
                        Admin
                    </span>
                </div>

                <p class="mt-1 text-xs text-slate-500">
                    Administración central de la plataforma
                </p>
            </div>

            <!-- Formulario -->
            <form
                class="rounded-xl border border-slate-800 bg-white p-5 shadow-xl shadow-slate-950/30"
                @submit.prevent="entrar"
            >
                <div>
                    <h1 class="text-base font-semibold text-slate-900">
                        Iniciar sesión
                    </h1>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Acceso exclusivo para administradores de BuhoPOS.
                    </p>
                </div>

                <div class="mt-5 space-y-3">
                    <!-- Correo -->
                    <label class="block">
                        <span
                            class="mb-1 block text-xs font-medium text-slate-600"
                        >
                            Correo electrónico
                        </span>

                        <div class="relative">
                            <Mail
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />

                            <input
                                v-model.trim="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="administrador@buhopos.com"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </div>
                    </label>

                    <!-- Contraseña -->
                    <label class="block">
                        <span
                            class="mb-1 block text-xs font-medium text-slate-600"
                        >
                            Contraseña
                        </span>

                        <div class="relative">
                            <LockKeyhole
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />

                            <input
                                v-model="form.password"
                                :type="mostrarPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="Ingresa tu contraseña"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-10 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />

                            <button
                                type="button"
                                class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                :title="
                                    mostrarPassword
                                        ? 'Ocultar contraseña'
                                        : 'Mostrar contraseña'
                                "
                                @click="mostrarPassword = !mostrarPassword"
                            >
                                <EyeOff
                                    v-if="mostrarPassword"
                                    class="h-4 w-4"
                                />

                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </label>

                    <!-- Recordar -->
                    <label
                        class="flex cursor-pointer items-center gap-2 text-xs text-slate-600"
                    >
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />

                        Mantener sesión iniciada
                    </label>
                </div>

                <!-- Error -->
                <div
                    v-if="error"
                    class="mt-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"
                >
                    <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />

                    <span>
                        {{ error }}
                    </span>
                </div>

                <!-- Botón -->
                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="mt-5 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <Loader2
                        v-if="auth.loading"
                        class="h-4 w-4 animate-spin"
                    />

                    <LogIn v-else class="h-4 w-4" />

                    {{ auth.loading ? "Ingresando..." : "Ingresar" }}
                </button>

                <div class="mt-4 border-t border-slate-100 pt-4 text-center">
                    <RouterLink
                        :to="{ name: 'login' }"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-emerald-700"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" />
                        Ir al acceso de clientes
                    </RouterLink>
                </div>
            </form>

            <p class="mt-4 text-center text-[10px] text-slate-600">
                © {{ anioActual }} BuhoPOS · Acceso restringido
            </p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import {
    ArrowLeft,
    CircleAlert,
    Eye,
    EyeOff,
    Loader2,
    LockKeyhole,
    LogIn,
    Mail,
    ShieldCheck,
} from "lucide-vue-next";

import { usePlataformaAuthStore } from "../stores/auth";

const auth = usePlataformaAuthStore();
const router = useRouter();

const error = ref("");
const mostrarPassword = ref(false);

const form = reactive({
    email: "",
    password: "",
    remember: false,
});

const anioActual = new Date().getFullYear();

async function entrar() {
    if (auth.loading) {
        return;
    }

    error.value = "";

    try {
        await auth.login(form);

        await router.replace({
            name: "plataforma-dashboard",
        });
    } catch (e) {
        error.value =
            Object.values(e?.response?.data?.errors || {})
                .flat()
                .join(" ") ||
            e?.response?.data?.message ||
            "No se pudo iniciar sesión.";
    }
}
</script>