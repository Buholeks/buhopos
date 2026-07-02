<template>
    <div
        style="
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.25;
            color: #0f172a;
        "
    >
        <!-- Encabezado empresa -->
        <div
            style="
                text-align: center;
                padding-bottom: 6px;
                border-bottom: 1px dashed #0f172a;
                margin-bottom: 6px;
            "
        >
            <div
                style="
                    font-size: 14px;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 0.4px;
                "
            >
                {{ ticket.empresa?.nombre ?? "MI EMPRESA" }}
            </div>
            <div
                v-if="cfg.mostrar_rfc && ticket.empresa?.rfc"
                style="font-size: 9px"
            >
                RFC: {{ ticket.empresa.rfc }}
            </div>
            <div
                v-if="ticket.sucursal?.nombre"
                style="font-weight: 700; margin-top: 2px"
            >
                {{ ticket.sucursal.nombre }}
            </div>
            <div
                v-if="ticket.reimpresion"
                style="
                    font-size: 8px;
                    font-weight: 800;
                    letter-spacing: 1.5px;
                    text-transform: uppercase;
                    margin-top: 3px;
                "
            >
                COPIA / REIMPRESION
            </div>
            <div
                v-if="cfg.mostrar_direccion && ticket.sucursal?.direccion"
                style="font-size: 8px; color: #475569"
            >
                {{ ticket.sucursal.direccion }}
            </div>
            <div
                v-if="cfg.mostrar_telefono && ticket.sucursal?.telefono"
                style="font-size: 8px; color: #475569"
            >
                Tel: {{ ticket.sucursal.telefono }}
            </div>
        </div>

        <!-- Datos -->
        <div
            style="
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 3px 6px;
                margin-bottom: 6px;
                font-size: 9px;
            "
        >
            <div>
                <span
                    style="
                        display: block;
                        color: #64748b;
                        font-size: 7px;
                        text-transform: uppercase;
                    "
                    >Fecha</span
                ><span style="font-weight: 700">{{
                    fmtFecha(ticket.fecha)
                }}</span>
            </div>
            <div v-if="cfg.mostrar_folio">
                <span
                    style="
                        display: block;
                        color: #64748b;
                        font-size: 7px;
                        text-transform: uppercase;
                    "
                    >Folio</span
                ><span style="font-weight: 700">{{ ticket.folio }}</span>
            </div>
            <div v-if="cfg.mostrar_vendedor && ticket.vendedor">
                <span
                    style="
                        display: block;
                        color: #64748b;
                        font-size: 7px;
                        text-transform: uppercase;
                    "
                    >Vendedor</span
                ><span style="font-weight: 700">{{
                    ticket.vendedor.name
                }}</span>
            </div>
            <div v-if="cfg.mostrar_cliente">
                <span
                    style="
                        display: block;
                        color: #64748b;
                        font-size: 7px;
                        text-transform: uppercase;
                    "
                    >Cliente</span
                ><span style="font-weight: 700">{{
                    ticket.cliente?.nombre ?? "Público general"
                }}</span>
            </div>
        </div>

        <!-- Productos -->
        <div
            style="
                border-top: 1px dashed #0f172a;
                padding-top: 5px;
                margin-top: 5px;
            "
        >
            <div
                style="
                    font-size: 7px;
                    font-weight: 800;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                "
            >
                Productos
            </div>
            <div
                v-for="(p, i) in ticket.productos"
                :key="i"
                style="padding: 4px 0; border-bottom: 1px dotted #cbd5e1"
            >
                <div
                    style="
                        display: flex;
                        justify-content: space-between;
                        gap: 6px;
                    "
                >
                    <span
                        style="
                            font-weight: 800;
                            text-transform: uppercase;
                            overflow-wrap: anywhere;
                        "
                        >{{ p.nombre }}</span
                    >
                    <span style="font-weight: 800; white-space: nowrap">{{
                        fmt(p.importe)
                    }}</span>
                </div>
                <div v-if="p.variante" style="font-size: 8px; color: #475569">
                    {{ p.variante }}
                </div>
                <div style="font-size: 8px; color: #475569">
                    {{ fmtCantidad(p.cantidad) }} x {{ fmt(p.precio_unitario) }}
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div
            style="
                border-top: 1px dashed #0f172a;
                padding-top: 5px;
                margin-top: 5px;
                display: grid;
                gap: 2px;
                font-size: 9px;
            "
        >
            <div
                style="
                    font-size: 7px;
                    font-weight: 800;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    margin-bottom: 3px;
                "
            >
                Resumen
            </div>
            <div style="display: flex; justify-content: space-between">
                <span>Subtotal</span
                ><span style="font-weight: 700">{{
                    fmt(ticket.subtotal)
                }}</span>
            </div>
            <div
                v-if="Number(ticket.descuento ?? 0) > 0"
                style="display: flex; justify-content: space-between"
            >
                <span>Desc. general</span
                ><span style="font-weight: 700">{{
                    fmt(ticket.descuento)
                }}</span>
            </div>
            <div
                style="
                    display: flex;
                    justify-content: space-between;
                    background: #0f172a;
                    color: #fff;
                    padding: 4px 5px;
                    font-weight: 800;
                    font-size: 12px;
                    margin: 3px 0;
                "
            >
                <span>Total</span><span>{{ fmt(ticket.total) }}</span>
            </div>
            <div
                v-for="(p, i) in ticket.pagos"
                :key="i"
                style="
                    display: flex;
                    justify-content: space-between;
                    border-top: 1px dotted #cbd5e1;
                    padding-top: 3px;
                "
            >
                <span>{{ labelPago(p.forma_pago) }}</span
                ><span style="font-weight: 700">{{ fmt(p.monto) }}</span>
            </div>
            <template v-if="Number(ticket.cambio ?? 0) > 0">
                <div style="display: flex; justify-content: space-between">
                    <span>Recibido</span
                    ><span style="font-weight: 700">{{
                        fmt(ticket.monto_recibido)
                    }}</span>
                </div>
                <div style="display: flex; justify-content: space-between">
                    <span>Cambio</span
                    ><span style="font-weight: 700">{{
                        fmt(ticket.cambio)
                    }}</span>
                </div>
            </template>
        </div>

        <!-- Pie -->
        <div style="text-align: center; margin-top: 8px">
            <div
                v-if="cfg.pie_mensaje"
                style="
                    font-size: 11px;
                    font-weight: 800;
                    text-transform: uppercase;
                "
            >
                {{ cfg.pie_mensaje }}
            </div>
            <div style="font-size: 8px; color: #475569">
                Conserve este ticket
            </div>
            <div
                style="
                    font-family: &quot;Courier New&quot;, monospace;
                    font-size: 9px;
                    letter-spacing: 1px;
                    margin-top: 4px;
                "
            >
                {{ ticket.folio }}
            </div>
            <div
                v-if="cfg.pie_extra"
                style="font-size: 8px; color: #475569; margin-top: 3px"
            >
                {{ cfg.pie_extra }}
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    ticket: { type: Object, required: true },
    cfg: { type: Object, default: () => ({}) },
});

const fmt = (v) =>
    new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v ?? 0));
const fmtCantidad = (v) => {
    const n = Number(v ?? 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(3);
};
const fmtFecha = (v) =>
    new Date(v).toLocaleString("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
const labelPago = (v) =>
    ({
        efectivo: "Efectivo",
        transferencia: "Transferencia",
        tarjeta: "Tarjeta",
        saldo_favor: "Saldo a favor",
    })[v] ??
    v ??
    "-";
</script>
