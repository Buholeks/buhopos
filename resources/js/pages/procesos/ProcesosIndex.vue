<template>
    <main class="space-y-6 p-2 sm:p-6">
        <HubSection title="Procesos" :items="procesos" />
        <!-- <HubSection title="Consultas y reportes" :items="consultas" /> -->
    </main>
</template>

<script setup>
import HubSection from "@/components/hub/HubSection.vue";

import {
    ShoppingCart,
    ReceiptText,
    ClipboardList,
    FileText,
    Wallet,
    RotateCcw,
    ArrowDownUp,
    Wrench,
    Landmark,
    PackageCheck,
    Repeat2,
} from "lucide-vue-next";

const procesos = [
    {
        label: "Ventas",
        icon: ShoppingCart,
        to: { name: "ventas" },
        badge: "Disponible",
    },
    {
        label: "Compras",
        icon: ReceiptText,
        to: { name: "compras" },
        badge: "Disponible",
    },
    {
        label: "Corte de Caja",
        icon: Landmark,
        to: { name: "caja" },
        badge: "Disponible",
    },
      {
        label: "Pagos a proveedores",
        icon: Wallet,
        to: { name: "reportes-pagos-proveedores" },
        badge: "Disponible",
    },
    {
        label: "Exhibición",
        icon: PackageCheck,
        to: { name: "exhibicion" },
        badge: "Disponible",
    },
    {
        label: "Nuevo traspaso",
        icon: Repeat2,
        to: { name: "traspasos-nuevo" },
        badge: "Disponible",
    },
    {
        label: "Pedidos",
        icon: ClipboardList,
        to: null,
        badge: "Próximamente",
    },
    {
        label: "Cotizaciones",
        icon: FileText,
        to: null,
        badge: "Próximamente",
    },
    {
        label: "Pagos / Abonos",
        icon: Wallet,
        to: null,
        badge: "Próximamente",
    },
    {
        label: "Cancelaciones y devoluciones",
        icon: RotateCcw,
        to: { name: "cancelaciones-devoluciones" },
        badge: "Disponible",
    },
    {
        label: "Entradas / Salidas",
        icon: ArrowDownUp,
        to: null,
        badge: "Próximamente",
    },
    {
        label: "Garantías",
        icon: Wrench,
        to: null,
        badge: "Próximamente",
    },
];
</script>
