<template>
    <main class="space-y-6 p-2 sm:p-6">
        <HubSection title="Configuración" :items="items" />
    </main>
</template>

<script setup>
import HubSection from "@/components/hub/HubSection.vue";
import { CalendarClock, Ruler, ShieldCheck, SlidersHorizontal, Ticket, Tags, UsersRound } from "lucide-vue-next";

const items = [
    {
        label: "Usuarios",
        icon: UsersRound,
        to: { name: "usuarios" },
        badge: "Disponible",
        permiso: "usuarios.gestionar",
    },
    {
        label: "Roles y permisos",
        icon: ShieldCheck,
        to: { name: "roles" },
        badge: "Disponible",
        permiso: "usuarios.gestionar",
    },
    {
        label: "Atributos",
        icon: SlidersHorizontal,
        to: { name: "atributos" },
        badge: "Disponible",
        permiso: "catalogos.ver",
    },
    {
        label: "Unidades de medida",
        icon: Ruler,
        to: { name: "unidades-medida" },
        badge: "Disponible",
        permiso: "catalogos.ver",
    },
    {
        label: "Diseñador de etiquetas",
        icon: Tags,
        to: { name: "etiquetas-plantillas" },
        badge: "Disponible",
        permiso: "etiquetas.disenar",
    },
    {
        label: "Configurar ticket",
        icon: Ticket,
        to: { name: "ticket-config" },
        badge: "Disponible",
        permiso: "ventas.crear",
    },
    {
        label: "Vigencia de pedidos y apartados",
        icon: CalendarClock,
        to: { name: "vigencia-pedidos" },
        badge: "Disponible",
        permiso: "empresa.editar",
    },
];
</script>
