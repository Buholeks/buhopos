<template>
    <main class="space-y-6 p-2 sm:p-6">
        <HubSection title="Consultas y reportes" :items="consultas" />
    </main>
</template>

<script setup>
import HubSection from "@/components/hub/HubSection.vue";

import {
    ReceiptText,
    Landmark,
    BarChart3,
    CreditCard,
    Repeat2,
    TrendingUp,
} from "lucide-vue-next";

const consultas = [
    {
        label: "Reporte de caja",
        icon: Landmark,
        to: { name: "reportes-caja" },
        badge: "Disponible",
    },
    {
        label: "Consulta de Ventas/Tickets",
        icon: CreditCard,
        to: { name: "reportes-ventas" },
        badge: "Disponible",
    },
    {
        label: "Ventas agrupadas",
        icon: BarChart3,
        to: { name: "reportes-ventas-agrupado" },
        badge: "Disponible",
    },
    {
        label: "Consulta de compras",
        icon: ReceiptText,
        to: { name: "reportes-compras" },
        badge: "Disponible",
    },
    {
        label: "Reporte de utilidades",
        icon: TrendingUp,
        to: { name: "reportes-utilidades" },
        badge: "Disponible",
    },
    {
        label: "Traspasos entrada",
        icon: Repeat2,
        to: { name: "traspasos-entrada" },
        badge: "Disponible",
    },
    {
        label: "Traspasos salida",
        icon: Repeat2,
        to: { name: "traspasos-salida" },
        badge: "Disponible",
    },
     {
        label: "Consulta pedidos",
        icon: ClipboardList,
        to: { name: "pedidos-consulta" },
        badge: "Disponible",
    },
    {
        label: "Consulta apartados",
        icon: ClipboardList,
        to: { name: "apartados-consulta" },
        badge: "Disponible",
    },
];
</script>
