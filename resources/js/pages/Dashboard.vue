<template>
    <div>
        <div class="bg-white border rounded-xl p-5 shadow-sm">
            <h1 class="text-2xl font-bold">
                Bienvenido al Sistema - {{ auth.user?.name }}
            </h1>
        </div>
    </div>
</template>
<script setup>
import { useAuthStore } from "../stores/auth";
const auth = useAuthStore();
</script>
