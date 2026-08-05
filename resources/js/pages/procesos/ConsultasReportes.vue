<template>
    <HubPage
        title="Consultas y reportes"
        description="Historiales operativos e información para tomar decisiones."
        :groups="grupos"
        search-placeholder="Buscar consulta o reporte"
    />
</template>

<script setup>
import HubPage from "@/components/hub/HubPage.vue";

import {
    ReceiptText,
    Landmark,
    BarChart3,
    CreditCard,
    Repeat2,
    TrendingUp,
    FileSearch,
    ClipboardList,
    PackageSearch,
    SlidersHorizontal,
} from "lucide-vue-next";

const consultasOperativas = [
    {
        label: "Consulta de caja",
        icon: ClipboardList,
        to: { name: "consulta-caja" },
        badge: "Disponible",
        permiso: "caja.historial",
    },
    {
        label: "Consulta de Ventas/Tickets",
        icon: CreditCard,
        to: { name: "reportes-ventas" },
        badge: "Disponible",
        permiso: "ventas.ver",
    },
    {
        label: "Consulta de compras",
        icon: ReceiptText,
        to: { name: "reportes-compras" },
        badge: "Disponible",
        permiso: "compras.ver",
    },
    {
        label: "Consulta de articulo",
        icon: PackageSearch,
        to: { name: "reportes-articulo" },
        badge: "Disponible",
        permiso: "reportes.ver",
    },
    {
        label: "Consulta pedidos",
        icon: ClipboardList,
        to: { name: "pedidos-consulta" },
        badge: "Disponible",
        permiso: "pedidos.ver",
    },
    {
        label: "Consulta apartados",
        icon: ClipboardList,
        to: { name: "apartados-consulta" },
        badge: "Disponible",
        permiso: "pedidos.ver",
    },
];

const consultasInventario = [
    {
        label: "Traspasos entrada",
        icon: Repeat2,
        to: { name: "traspasos-entrada" },
        badge: "Disponible",
        permiso: "inventario.traspasos",
    },
    {
        label: "Traspasos salida",
        icon: Repeat2,
        to: { name: "traspasos-salida" },
        badge: "Disponible",
        permiso: "inventario.traspasos",
    },
    {
        label: "Conteos de inventario",
        icon: FileSearch,
        to: { name: "conteos-inventario-consulta" },
        badge: "Disponible",
        permiso: "inventario.conteos.ver",
    },
    {
        label: "Ajustes de inventario",
        icon: SlidersHorizontal,
        to: { name: "ajustes-inventario-consulta" },
        badge: "Disponible",
        permiso: "inventario.ajustes.crear",
    },
];

const reportes = [
    {
        label: "Reporte de caja",
        icon: Landmark,
        to: { name: "reportes-caja" },
        badge: "Disponible",
        permiso: "reportes.ver",
    },
    {
        label: "Ventas agrupadas",
        icon: BarChart3,
        to: { name: "reportes-ventas-agrupado" },
        badge: "Disponible",
        permiso: "ventas.ver",
    },
    {
        label: "Reporte de utilidades",
        icon: TrendingUp,
        to: { name: "reportes-utilidades" },
        badge: "Disponible",
        permiso: "reportes.utilidades",
    },
    {
        label: "Inversión en mercancía",
        icon: PackageSearch,
        to: { name: "reportes-inventario" },
        badge: "Disponible",
        permiso: "reportes.ver",
    },
];

const grupos = [
    { title: "Consultas operativas", description: "Movimientos e historiales del trabajo diario.", items: consultasOperativas },
    { title: "Consultas de inventario", description: "Entradas, salidas, conteos y ajustes.", items: consultasInventario },
    { title: "Reportes", description: "Indicadores financieros y comerciales.", items: reportes },
];
</script>
