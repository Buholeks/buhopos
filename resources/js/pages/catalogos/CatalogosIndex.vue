<template>
    <HubPage
        title="Catálogos"
        description="Información base del sistema organizada por tipo."
        :groups="grupos"
        search-placeholder="Buscar catálogo"
    />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import HubPage from "@/components/hub/HubPage.vue";
import {
    Users,
    Truck,
    Tags,
    BadgeCheck,
    Package,
    SlidersHorizontal,
    Landmark,
    CreditCard,
} from "lucide-vue-next";

const tiposAtributo = ref([]);

const personas = [
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
];

const productos = [
    {
        label: "Productos",
        icon: Package,
        to: { name: "productos" },
        permiso: "productos.ver",
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
];

const pagos = [
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

const atributos = computed(() =>
    tiposAtributo.value.map((tipo) => ({
        label: tipo.nombre,
        icon: SlidersHorizontal,
        to: { name: "catalogo-atributo-valores", params: { id: tipo.id } },
        permiso: "catalogos.ver",
    })),
);

const grupos = computed(() => [
    {
        title: "Personas y empresas",
        description: "Clientes y socios comerciales.",
        items: personas,
    },
    {
        title: "Productos",
        description: "Artículos y clasificaciones del catálogo.",
        items: productos,
    },
    {
        title: "Pagos",
        description: "Cuentas y terminales disponibles.",
        items: pagos,
    },
    {
        title: "Atributos de producto",
        description: "Valores configurables como color, talla o capacidad.",
        items: atributos.value,
    },
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
