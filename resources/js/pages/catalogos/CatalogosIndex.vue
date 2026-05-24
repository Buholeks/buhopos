<template>
    <section class="p-2 sm:p-6 space-y-4">
        <HubSection title="Catalogos" :items="Catalogos" />
    </section>
</template>

<script setup>
import HubSection from "@/components/hub/HubSection.vue";
import {
    Users,
    Truck,
    Tags,
    BadgeCheck,
    SlidersHorizontal,
    Ruler,
    Package,
} from "lucide-vue-next";

const Catalogos = [
    {
        label: "Clientes",
        icon: Users,
        to: { name: "clientes" },
    },
    {
        label: "Proveedores",
        icon: Truck,
        to: { name: "proveedores" },
    },
    {
        label: "Categorías",
        icon: Tags,
        to: { name: "categorias" },
    },
    {
        label: "Marcas y Modelos",
        icon: BadgeCheck,
        to: { name: "marcas" },
    },
    {
        label: "Atributos",
        icon: SlidersHorizontal,
        to: { name: "atributos" },
    },
    {
        label: "Unidades de Medida",
        icon: Ruler,
        to: { name: "unidades-medida" },
    },
    {
        label: "Productos",
        icon: Package,
        to: { name: "productos" },
    },
];
</script>
