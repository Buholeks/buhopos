<template>
    <main class="flex min-h-[60vh] flex-col items-center justify-center gap-6 p-6 text-center">
        <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-rose-50 text-rose-500">
            <ShieldOff class="h-10 w-10" />
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl font-bold text-slate-900">Sin permiso</h1>
            <p class="max-w-sm text-sm text-slate-500">
                No tienes acceso a esta sección. Contacta al administrador de tu empresa
                si crees que esto es un error.
            </p>
        </div>

        <div class="flex gap-3">
            <button
                type="button"
                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                @click="router.back()"
            >
                Volver
            </button>
            <RouterLink
                :to="{ name: 'dashboard' }"
                class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
            >
                Ir al inicio
            </RouterLink>
        </div>
    </main>
</template>

<script setup>
import { useRouter } from "vue-router";
import { ShieldOff } from "lucide-vue-next";

const router = useRouter();
</script>
