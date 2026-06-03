<template>
   <div class="grid min-h-screen lg:grid-cols-2">

    <!-- PANEL IZQUIERDO (form) -->
    <div class="flex items-center justify-center bg-slate-50 p-6">
        <div class="w-full max-w-md">

            <!-- Card -->
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-6">
                    <h1 class="text-xl font-semibold text-slate-900">
                        Iniciar sesión
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Sistema de control de inventario y ventas
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4 p-6">

                    <!-- Email -->
                    <BaseInput
                        v-model="email"
                        label="Correo"
                        type="email"
                        placeholder="tu@correo.com"
                        autocomplete="email"
                        :error="errorEmail"
                        required
                    >
                        <template #icon>
                            <Mail class="h-4 w-4" />
                        </template>
                    </BaseInput>

                    <!-- Password -->
                    <BaseInput
                        v-model="password"
                        label="Contraseña"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        :error="errorPass"
                        required
                    >
                        <template #icon>
                            <LockKeyhole class="h-4 w-4" />
                        </template>

                        <template #suffix>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-4 focus:ring-slate-100"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </template>
                    </BaseInput>

                    <div
                        v-if="error"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
                    >
                        {{ error }}
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200 disabled:opacity-70"
                        :disabled="auth.loading || !canSubmit"
                    >
                        <Loader2
                            v-if="auth.loading"
                            class="h-4 w-4 animate-spin"
                        />
                        <LogIn v-else class="h-4 w-4" />
                        {{ auth.loading ? "Entrando..." : "Entrar" }}
                    </button>

                    <p class="text-center text-sm text-slate-500">
                        ¿No tienes cuenta?
                        <RouterLink
                            :to="{ name: 'register' }"
                            class="font-semibold text-slate-900 hover:underline"
                        >
                            Regístrate aquí
                        </RouterLink>
                    </p>

                </form>
            </div>
        </div>
    </div>

    <!-- PANEL DERECHO (branding) -->
    <div
        class="relative hidden overflow-hidden border-l border-slate-200 bg-slate-950 lg:block"
    >
        <div class="absolute inset-0 opacity-[0.08]">
            <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-white"></div>
            <div class="absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-white"></div>
        </div>

        <div class="relative flex h-full flex-col justify-center p-12 text-white">
            <div class="flex items-center gap-3 mb-8">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10">
                    <Package class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold">
                        Control total del negocio
                    </h2>
                    <p class="text-sm text-white/70">
                        Inventario · Ventas · Compras · Sucursales
                    </p>
                </div>
            </div>

            <p class="text-sm text-white/60 max-w-md leading-relaxed">
                Administra productos, controla existencias y registra ventas
                en tiempo real con un sistema diseñado para empresas modernas.
            </p>
        </div>
    </div>

</div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import BaseInput from "@/components/ui/BaseInput.vue";

import {
    ShieldCheck,
    CheckCircle2,
    Building2,
    Zap,
    LogIn,
    Mail,
    LockKeyhole,
    Eye,
    EyeOff,
    AlertCircle,
    Loader2,
    Package,
} from "lucide-vue-next";

const auth = useAuthStore();
const router = useRouter();

const email = ref("");
const password = ref("");
const remember = ref(true);
const showPassword = ref(false);

const error = ref("");

const errorEmail = computed(() => {
    if (!email.value) return "";
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
    return ok ? "" : "Ingresa un correo válido.";
});

const errorPass = computed(() => {
    if (!password.value) return "";
    return password.value.length >= 4 ? "" : "La contraseña parece muy corta.";
});

const canSubmit = computed(() => {
    return (
        !!email.value &&
        !!password.value &&
        !errorEmail.value &&
        !errorPass.value
    );
});

const onForgotPassword = () => {
    error.value = "Aún no está configurada la recuperación de contraseña.";
};

const submit = async () => {
    error.value = "";
    try {
        await auth.login({
            email: email.value,
            password: password.value,
            remember: remember.value,
        });

        router.push({ name: "dashboard" });
    } catch (e) {
        error.value =
            e?.response?.data?.message || "No se pudo iniciar sesión.";
    }
};
</script>
