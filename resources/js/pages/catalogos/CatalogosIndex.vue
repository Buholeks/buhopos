<template>
    <section class="p-2 sm:p-6 space-y-4">
        <HubSection title="Catalogos" :items="Catalogos" />
    </section>
</template>

<script setup>
import HubSection from "@/components/hub/HubSection.vue";
import { Users, Truck, Tags, BadgeCheck, Package } from "lucide-vue-next";

const Catalogos = [
    {
        label: "Clientes",
        icon: Users,
        to: { name: "clientes" },
        permiso: "clientes.ver",
    },
    {
        label: "Proveedores",
        icon: Truck,
        to: { name: "proveedores" },
        permiso: "catalogos.ver",
    },
    {
        label: "Categorías",
        icon: Tags,
        to: { name: "categorias" },
        permiso: "catalogos.ver",
    },
    {
        label: "Marcas y Modelos",
        icon: BadgeCheck,
        to: { name: "marcas" },
        permiso: "catalogos.ver",
    },
    {
        label: "Productos",
        icon: Package,
        to: { name: "productos" },
        permiso: "productos.ver",
    },
];
</script>
