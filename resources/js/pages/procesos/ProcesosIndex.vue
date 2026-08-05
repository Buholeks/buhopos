<template>
    <HubPage
        title="Procesos"
        description="Operaciones frecuentes organizadas por área."
        :groups="grupos"
        search-placeholder="Buscar proceso"
    />
</template>

<script setup>
import HubPage from "@/components/hub/HubPage.vue";

import {
    ShoppingCart,
    ReceiptText,
    ClipboardList,
    FileText,
    Wallet,
    RotateCcw,
    ClipboardCheck,
    Wrench,
    Landmark,
    PackageCheck,
    Repeat2,
    Undo2,
    Upload,
    PackageMinus,
} from "lucide-vue-next";

const ventasPedidos = [
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
];

const caja = [
    {
        label: "Corte de Caja",
        icon: Landmark,
        to: { name: "caja" },
        badge: "Disponible",
        permiso: "caja.abrir",
    },
];

const compras = [
    {
        label: "Compras",
        icon: ReceiptText,
        to: { name: "compras" },
        badge: "Disponible",
        permiso: "compras.crear",
    },
    {
        label: "Pagos a proveedores",
        icon: Wallet,
        to: { name: "reportes-pagos-proveedores" },
        badge: "Disponible",
        permiso: "compras.ver",
    },
    {
        label: "Devolución a proveedor",
        icon: Undo2,
        to: { name: "devoluciones-proveedor" },
        badge: "Disponible",
        permiso: "compras.crear",
    },
];

const inventario = [
    {
        label: "Exhibición",
        icon: PackageCheck,
        to: { name: "exhibicion" },
        badge: "Disponible",
        permiso: "inventario.ver",
    },
    {
        label: "Conteo físico",
        icon: ClipboardCheck,
        to: { name: "conteo-inventario" },
        badge: "Disponible",
        permiso: "inventario.conteos.ver",
    },
    {
        label: "Ajuste rápido",
        icon: PackageMinus,
        to: { name: "ajuste-rapido-inventario" },
        badge: "Disponible",
        permiso: "inventario.ajustes.crear",
    },
    {
        label: "Nuevo traspaso",
        icon: Repeat2,
        to: { name: "traspasos-nuevo" },
        badge: "Disponible",
        permiso: "inventario.traspasos",
    },
];

const herramientas = [
    {
        label: "Importar productos",
        icon: Upload,
        to: { name: "importar-productos" },
        badge: "Disponible",
        permiso: "productos.editar",
    },
];

const proximamente = [
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

const grupos = [
    { title: "Ventas y pedidos", description: "Atención al cliente y encargos.", items: ventasPedidos },
    { title: "Caja", description: "Operación diaria de efectivo y terminal.", items: caja },
    { title: "Compras y proveedores", description: "Entradas, pagos y devoluciones.", items: compras },
    { title: "Inventario", description: "Existencias, conteos y movimientos.", items: inventario },
    { title: "Herramientas", description: "Utilidades para operaciones masivas.", items: herramientas },
    { title: "Próximamente", description: "Funciones planeadas para versiones posteriores.", items: proximamente },
];
</script>
