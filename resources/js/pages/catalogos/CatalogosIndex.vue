<template>
    <section class="p-2 sm:p-6 space-y-4">
        <HubSection title="Catalogos" :items="catalogosVisibles" />
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import HubSection from "@/components/hub/HubSection.vue";
import { Users, Truck, Tags, BadgeCheck, Package, SlidersHorizontal, Landmark, CreditCard } from "lucide-vue-next";

const tiposAtributo = ref([]);

const catalogosBase = [
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
    {
        label: "Cuentas Bancarias",
        icon: Landmark,
        to: { name: "cuentas-bancarias" },
        permiso: "catalogos.ver",
    },
    {
        label: "Terminales de Pago",
        icon: CreditCard,
        to: { name: "terminales-pago" },
        permiso: "catalogos.ver",
    },
];

const catalogosVisibles = computed(() => [
    ...catalogosBase,
    ...tiposAtributo.value.map((tipo) => ({
        label: tipo.nombre,
        icon: SlidersHorizontal,
        to: { name: "catalogo-atributo-valores", params: { id: tipo.id } },
        permiso: "catalogos.ver",
    })),
]);

async function cargarAtributos() {
    try {
        const { data } = await axios.get("/api/tipo-atributos");
        tiposAtributo.value = Array.isArray(data)
            ? data.filter((tipo) => tipo.activo)
            : [];
    } catch {
        tiposAtributo.value = [];
    }
}

onMounted(() => cargarAtributos());
</script>
