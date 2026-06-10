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
    Undo2,
} from "lucide-vue-next";

const procesos = [
    {
        label: "Ventas",
        icon: ShoppingCart,
        to: { name: "ventas" },
        badge: "Disponible",
        permiso: "ventas.crear",
    },
    {
        label: "Cancelacion de Venta",
        icon: RotateCcw,
        to: { name: "cancelaciones-devoluciones" },
        badge: "Disponible",
        permiso: "ventas.cancelar",
    },
    {
        label: "Compras",
        icon: ReceiptText,
        to: { name: "compras" },
        badge: "Disponible",
        permiso: "compras.crear",
    },
    {
        label: "Corte de Caja",
        icon: Landmark,
        to: { name: "caja" },
        badge: "Disponible",
        permiso: "caja.abrir",
    },
    {
        label: "Pagos a proveedores",
        icon: Wallet,
        to: { name: "reportes-pagos-proveedores" },
        badge: "Disponible",
        permiso: "compras.ver",
    },
    {
        label: "Exhibición",
        icon: PackageCheck,
        to: { name: "exhibicion" },
        badge: "Disponible",
        permiso: "inventario.ver",
    },
    {
        label: "Nuevo traspaso",
        icon: Repeat2,
        to: { name: "traspasos-nuevo" },
        badge: "Disponible",
        permiso: "inventario.traspasos",
    },
    {
        label: "Nuevo pedido",
        icon: ClipboardList,
        to: { name: "pedidos-nuevo" },
        badge: "Disponible",
        permiso: "pedidos.crear",
    },
    {
        label: "Nuevo apartado",
        icon: ClipboardList,
        to: { name: "apartados-nuevo" },
        badge: "Disponible",
        permiso: "pedidos.crear",
    },
    {
        label: "Devolución a proveedor",
        icon: Undo2,
        to: { name: "devoluciones-proveedor" },
        badge: "Disponible",
        permiso: "compras.crear",
    },
    {
        label: "Cotizaciones",
        icon: FileText,
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
