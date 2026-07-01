<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-3 sm:px-6 py-3 sm:py-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"
                    >
                        <PackageSearch class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">
                            {{ tituloPagina }}
                        </h1>
                        <p class="text-xs text-slate-500">
                            {{ subtituloPagina }}
                        </p>
                    </div>
                </div>

                <BtnActualizar
                    size="sm"
                    :disabled="cargando"
                    :loading="cargando"
                    @click="refrescar"
                >
                    Actualizar
                </BtnActualizar>
            </div>
        </div>

        <main class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 py-4 sm:py-5">
            <!-- Filtros -->
            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <BaseInput
                        v-model="filtros.fecha_inicio"
                        type="date"
                        label="Desde"
                        @change="aplicarFiltros"
                    />

                    <BaseInput
                        v-model="filtros.fecha_fin"
                        type="date"
                        label="Hasta"
                        @change="aplicarFiltros"
                    />

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Proveedor
                        </label>
                        <select
                            v-model="filtros.proveedor_id"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="aplicarFiltros"
                        >
                            <option value="">Todos</option>
                            <option
                                v-for="p in proveedores"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.nombre_comercial ?? p.nombre }}
                            </option>
                        </select>
                    </div>

                    <div v-if="esVistaCompras">
                        <label
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Estado
                        </label>
                        <select
                            v-model="filtros.estado"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="aplicarFiltros"
                        >
                            <option value="">Todos</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="borrador">Borrador</option>
                            <option value="devuelta_parcial">Devuelta parcial</option>
                            <option value="devuelta">Devuelta</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Forma de pago
                        </label>
                        <select
                            v-model="filtros.forma_pago"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="aplicarFiltros"
                        >
                            <option value="">Todas</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta_debito">T. Débito</option>
                            <option value="tarjeta_credito">T. Crédito</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div
                v-if="cargando"
                class="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white py-12 text-sm text-slate-500 shadow-sm"
            >
                <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                Cargando reporte...
            </div>

            <template v-else-if="datos">
                <!-- Resumen compacto -->
                <section
                    v-if="esVistaCompras"
                    class="grid grid-cols-2 gap-3 md:grid-cols-5"
                >
                    <div
                        v-for="item in resumenCompras"
                        :key="item.label"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-medium text-slate-500">
                                {{ item.label }}
                            </p>
                            <component
                                :is="item.icon"
                                class="h-4 w-4"
                                :class="item.iconClass"
                            />
                        </div>
                        <p
                            class="mt-2 truncate text-lg font-semibold"
                            :class="item.valueClass"
                        >
                            {{ item.value }}
                        </p>
                        <p
                            v-if="item.sub"
                            class="mt-0.5 truncate text-[11px] text-slate-400"
                        >
                            {{ item.sub }}
                        </p>
                    </div>
                </section>

                <!-- Tabs -->
                <div v-if="esVistaMixta" class="flex items-center gap-2">
                    <button
                        type="button"
                        :class="
                            tabActivo === 'compras' ? tabActivoClass : tabClass
                        "
                        @click="tabActivo = 'compras'"
                    >
                        <ShoppingCart class="h-4 w-4" />
                        Compras
                    </button>

                    <button
                        type="button"
                        :class="
                            tabActivo === 'cuentas' ? tabActivoClass : tabClass
                        "
                        @click="activarCuentas"
                    >
                        <WalletCards class="h-4 w-4" />
                        Cuentas por pagar
                    </button>
                </div>

                <!-- Tabla compras -->
                <section
                    v-if="esVistaCompras"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Compras registradas
                            </h2>
                            <p class="text-xs text-slate-500">
                                Doble clic sobre una fila para ver el detalle.
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="exportandoLista"
                                @click="exportarLista('excel')"
                                class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:opacity-50"
                            >
                                <Loader2 v-if="exportandoLista === 'excel'" class="h-4 w-4 animate-spin" />
                                <FileSpreadsheet v-else class="h-4 w-4" />
                                Excel
                            </button>
                            <button
                                type="button"
                                :disabled="exportandoLista"
                                @click="exportarLista('pdf')"
                                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                            >
                                <Loader2 v-if="exportandoLista === 'pdf'" class="h-4 w-4 animate-spin" />
                                <FileText v-else class="h-4 w-4" />
                                PDF
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead
                                class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                            >
                                <tr>
                                    <th class="px-4 py-3 text-left">Folio</th>
                                    <th class="px-4 py-3 text-left">Fecha</th>
                                    <th class="px-4 py-3 text-left">
                                        Proveedor
                                    </th>
                                    <th class="px-4 py-3 text-left">Pago</th>
                                    <th class="px-4 py-3 text-right">
                                        Subtotal
                                    </th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-right">Saldo</th>
                                    <th class="px-4 py-3 text-left">Estado</th>
                                    <th class="px-4 py-3 text-left">Estatus</th>
                                    <th class="px-4 py-3 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="c in datos.compras.data"
                                    :key="c.id"
                                    class="transition hover:bg-slate-50"
                                    :class="
                                        getEstatusPago(c) === 'vencido'
                                            ? 'bg-red-50/40'
                                            : ''
                                    "
                                    @dblclick="verDetalle(c)"
                                >
                                    <td
                                        class="px-4 py-3 font-mono text-xs font-semibold text-emerald-600"
                                    >
                                        {{ c.folio }}
                                    </td>

                                    <td class="px-4 py-3 text-slate-500">
                                        {{ fmtFecha(c.fecha) }}
                                    </td>

                                    <td
                                        class="px-4 py-3 font-medium text-slate-800"
                                    >
                                        {{
                                            c.proveedor?.nombre ??
                                            c.proveedor?.nombre_comercial ??
                                            "-"
                                        }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span :class="badgePago(c.forma_pago)">
                                            {{ labelFormaPago(c.forma_pago) }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-4 py-3 text-right text-slate-600"
                                    >
                                        {{ fmt(c.subtotal) }}
                                    </td>

                                    <td
                                        class="px-4 py-3 text-right font-semibold text-slate-900"
                                    >
                                        {{ fmt(c.total) }}
                                    </td>

                                    <td
                                        class="px-4 py-3 text-right font-semibold"
                                        :class="
                                            Number(c.saldo ?? 0) > 0
                                                ? 'text-red-600'
                                                : 'text-emerald-600'
                                        "
                                    >
                                        {{ fmt(c.saldo ?? 0) }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span :class="badgeEstado(c.estado)">
                                            {{ labelEstado(c.estado) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            :class="
                                                badgeEstatusPago(
                                                    getEstatusPago(c),
                                                )
                                            "
                                        >
                                            {{ getEstatusPago(c) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                                title="Ver detalle"
                                                @click.stop="verDetalle(c)"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </button>
                                            <RouterLink
                                                v-if="['confirmada', 'devuelta_parcial'].includes(c.estado)"
                                                :to="{ name: 'devoluciones-proveedor', query: { compra_id: c.id } }"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-amber-600 transition hover:bg-amber-50"
                                                title="Devolver al proveedor"
                                                @click.stop
                                            >
                                                <Undo2 class="h-4 w-4" />
                                            </RouterLink>
                                            <RouterLink
                                                v-if="c.estado === 'confirmada'"
                                                :to="{ name: 'devoluciones-proveedor', query: { compra_id: c.id, modo: 'cancelar' } }"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                                                title="Cancelar compra"
                                                @click.stop
                                            >
                                                <Ban class="h-4 w-4" />
                                            </RouterLink>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!datos.compras.data.length">
                                    <td
                                        colspan="10"
                                        class="px-4 py-10 text-center text-sm text-slate-400"
                                    >
                                        No hay compras en este periodo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="datos.compras.last_page > 1"
                        class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500"
                    >
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="datos.compras.current_page === 1"
                            @click="
                                cambiarPagina(datos.compras.current_page - 1)
                            "
                        >
                            Anterior
                        </button>

                        <span>
                            Página {{ datos.compras.current_page }} de
                            {{ datos.compras.last_page }}
                        </span>

                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="
                                datos.compras.current_page ===
                                datos.compras.last_page
                            "
                            @click="
                                cambiarPagina(datos.compras.current_page + 1)
                            "
                        >
                            Siguiente
                        </button>
                    </div>
                </section>

                <!-- Cuentas por pagar -->
                <section
                    v-if="esVistaPagos"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Cuentas por pagar
                            </h2>
                            <p class="text-xs text-slate-500">
                                Saldos pendientes, pagados y vencidos.
                            </p>
                        </div>

                        <div class="w-full md:w-52">
                            <select
                                v-model="filtrosCuentas.estatus"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @change="aplicarFiltrosCuentas"
                            >
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="pagado">Pagado</option>
                                <option value="vencido">Vencido</option>
                            </select>
                        </div>
                    </div>

                    <div
                        v-if="cargandoCuentas"
                        class="flex items-center justify-center gap-2 py-12 text-sm text-slate-500"
                    >
                        <Loader2
                            class="h-5 w-5 animate-spin text-emerald-600"
                        />
                        Cargando cuentas...
                    </div>

                    <template v-else-if="datosCuentas">
                        <div
                            class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 md:grid-cols-3"
                        >
                            <div class="rounded-2xl bg-red-50 px-4 py-3">
                                <p class="text-xs font-medium text-red-700">
                                    Total deuda
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-red-700"
                                >
                                    {{ fmt(datosCuentas.totales.total_deuda) }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-emerald-50 px-4 py-3">
                                <p class="text-xs font-medium text-emerald-700">
                                    Total pagado
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-emerald-700"
                                >
                                    {{ fmt(datosCuentas.totales.total_pagado) }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-amber-50 px-4 py-3">
                                <p class="text-xs font-medium text-amber-700">
                                    Saldo pendiente
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-amber-700"
                                >
                                    {{
                                        fmt(
                                            datosCuentas.totales
                                                .total_saldo_pendiente,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                                >
                                    <tr>
                                        <th class="px-4 py-3 text-left">
                                            Folio
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            Proveedor
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            Compra
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            Vencimiento
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            Pago
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Total
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Pagado
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Saldo
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            Estatus
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="ct in datosCuentas.cuentas.data"
                                        :key="ct.id"
                                        class="transition hover:bg-slate-50"
                                        :class="
                                            getEstatusPago(ct) === 'vencido'
                                                ? 'bg-red-50/40'
                                                : ''
                                        "
                                    >
                                        <td
                                            class="px-4 py-3 font-mono text-xs font-semibold text-emerald-600"
                                        >
                                            {{ ct.folio }}
                                        </td>

                                        <td
                                            class="px-4 py-3 font-medium text-slate-800"
                                        >
                                            {{
                                                ct.proveedor?.nombre ??
                                                ct.proveedor
                                                    ?.nombre_comercial ??
                                                "-"
                                            }}
                                        </td>

                                        <td class="px-4 py-3 text-slate-500">
                                            {{ fmtFecha(ct.fecha) }}
                                        </td>

                                        <td
                                            class="px-4 py-3"
                                            :class="
                                                getEstatusPago(ct) === 'vencido'
                                                    ? 'font-medium text-red-600'
                                                    : 'text-slate-500'
                                            "
                                        >
                                            {{
                                                ct.fecha_vencimiento
                                                    ? fmtFecha(
                                                          ct.fecha_vencimiento,
                                                      )
                                                    : "-"
                                            }}
                                        </td>

                                        <td class="px-4 py-3">
                                            <span
                                                :class="
                                                    badgePago(ct.forma_pago)
                                                "
                                            >
                                                {{
                                                    labelFormaPago(
                                                        ct.forma_pago,
                                                    )
                                                }}
                                            </span>
                                        </td>

                                        <td
                                            class="px-4 py-3 text-right font-semibold text-slate-900"
                                        >
                                            {{ fmt(ct.total) }}
                                        </td>

                                        <td
                                            class="px-4 py-3 text-right font-semibold text-emerald-600"
                                        >
                                            {{ fmt(ct.pagado ?? 0) }}
                                        </td>

                                        <td
                                            class="px-4 py-3 text-right font-semibold"
                                            :class="
                                                Number(ct.saldo ?? 0) > 0
                                                    ? 'text-red-600'
                                                    : 'text-emerald-600'
                                            "
                                        >
                                            {{ fmt(ct.saldo ?? 0) }}
                                        </td>

                                        <td class="px-4 py-3">
                                            <span
                                                :class="
                                                    badgeEstatusPago(
                                                        getEstatusPago(ct),
                                                    )
                                                "
                                            >
                                                {{ getEstatusPago(ct) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-3">
                                            <div class="flex justify-end">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100"
                                                    @click.stop="abrirPagos(ct)"
                                                >
                                                    <Wallet
                                                        class="h-3.5 w-3.5"
                                                    />
                                                    {{
                                                        Number(ct.saldo ?? 0) <=
                                                        0
                                                            ? "Ver"
                                                            : "Pagar"
                                                    }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="!datosCuentas.cuentas.data.length"
                                    >
                                        <td
                                            colspan="10"
                                            class="px-4 py-10 text-center text-sm text-slate-400"
                                        >
                                            No hay cuentas por pagar.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-if="datosCuentas.cuentas.last_page > 1"
                            class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500"
                        >
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-2 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="
                                    datosCuentas.cuentas.current_page === 1
                                "
                                @click="
                                    cambiarPaginaCuentas(
                                        datosCuentas.cuentas.current_page - 1,
                                    )
                                "
                            >
                                Anterior
                            </button>

                            <span>
                                Página
                                {{ datosCuentas.cuentas.current_page }} de
                                {{ datosCuentas.cuentas.last_page }}
                            </span>

                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-2 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="
                                    datosCuentas.cuentas.current_page ===
                                    datosCuentas.cuentas.last_page
                                "
                                @click="
                                    cambiarPaginaCuentas(
                                        datosCuentas.cuentas.current_page + 1,
                                    )
                                "
                            >
                                Siguiente
                            </button>
                        </div>
                    </template>
                </section>
            </template>
        </main>

        <!-- Modal detalle -->
        <div
            v-if="modalAbierto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="cerrarModal"
        >
            <div
                class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4"
                >
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Compra {{ detalle?.compra?.folio ?? "" }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{
                                detalle?.compra?.proveedor_nombre ??
                                "Cargando..."
                            }}
                            <span v-if="detalle?.compra?.fecha">
                                · {{ fmtFecha(detalle.compra.fecha) }}
                            </span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="detalle?.compra?.id"
                            type="button"
                            :disabled="exportandoDetalle"
                            @click="exportarDetalleCompra"
                            class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                        >
                            <Loader2 v-if="exportandoDetalle" class="h-4 w-4 animate-spin" />
                            <FileText v-else class="h-4 w-4" />
                            PDF
                        </button>
                        <RouterLink
                            v-if="detalle?.compra?.id"
                            :to="{ name: 'compra-etiquetas', params: { compraId: detalle.compra.id } }"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white"
                        >
                            Etiquetas
                        </RouterLink>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                            @click="cerrarModal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div
                    v-if="cargandoDetalle"
                    class="flex items-center justify-center gap-2 py-14 text-sm text-slate-500"
                >
                    <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                    Cargando detalle...
                </div>

                <div v-else-if="detalle" class="space-y-5 p-5">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <InfoItem
                            label="Proveedor"
                            :value="detalle.compra.proveedor_nombre"
                        />
                        <InfoItem
                            label="Fecha"
                            :value="fmtFecha(detalle.compra.fecha)"
                        />
                        <InfoItem
                            label="Registró"
                            :value="detalle.compra.usuario_nombre"
                        />
                        <InfoItem
                            label="Subtotal"
                            :value="fmt(detalle.compra.subtotal)"
                        />
                        <InfoItem
                            label="Total"
                            :value="fmt(detalle.compra.total)"
                            strong
                        />
                        <InfoItem
                            label="Pagado"
                            :value="fmt(detalle.compra.pagado ?? 0)"
                            tone="emerald"
                        />
                        <InfoItem
                            v-if="Number(detalle.compra.saldo_favor_aplicado ?? 0) > 0"
                            label="Saldo a favor aplicado"
                            :value="fmt(detalle.compra.saldo_favor_aplicado)"
                            tone="emerald"
                        />
                        <InfoItem
                            label="Saldo"
                            :value="fmt(detalle.compra.saldo ?? 0)"
                            :tone="
                                Number(detalle.compra.saldo ?? 0) > 0
                                    ? 'red'
                                    : 'emerald'
                            "
                        />
                        <InfoItem
                            label="Vencimiento"
                            :value="
                                detalle.compra.fecha_vencimiento
                                    ? fmtFecha(detalle.compra.fecha_vencimiento)
                                    : 'N/A'
                            "
                        />
                    </div>

                    <div
                        v-if="detalle.compra.notas"
                        class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                    >
                        <span class="font-semibold">Notas:</span>
                        {{ detalle.compra.notas }}
                    </div>

                    <div>
                        <h3
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Detalle de productos
                        </h3>

                        <div
                            class="overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                                    >
                                        <tr>
                                            <th class="px-4 py-3 text-left">
                                                Producto
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                SKU
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Cant.
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Costo
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                P. Venta
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Subtotal
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="d in detalle.detalles"
                                            :key="d.id"
                                        >
                                            <td
                                                class="px-4 py-3 font-medium text-slate-800"
                                            >
                                                {{ d.producto }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ d.sku ?? "-" }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center text-slate-600"
                                            >
                                                {{ d.cantidad }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right text-slate-600"
                                            >
                                                {{ fmt(d.precio_compra) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right text-slate-600"
                                            >
                                                {{ fmt(d.precio_venta) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-semibold text-slate-900"
                                            >
                                                {{ fmt(d.subtotal) }}
                                            </td>
                                        </tr>

                                        <tr v-if="!detalle.detalles.length">
                                            <td
                                                colspan="6"
                                                class="px-4 py-8 text-center text-sm text-slate-400"
                                            >
                                                Sin detalles.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="detalle.pagos?.length">
                        <h3
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Historial de pagos
                        </h3>

                        <div
                            class="overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                                    >
                                        <tr>
                                            <th class="px-4 py-3 text-left">
                                                Fecha
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Forma
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Referencia
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Monto
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Registró
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Notas
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="p in detalle.pagos"
                                            :key="p.id"
                                        >
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ fmtFecha(p.fecha_pago) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    :class="
                                                        badgePago(p.forma_pago)
                                                    "
                                                >
                                                    {{
                                                        labelFormaPago(
                                                            p.forma_pago,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ p.referencia ?? "-" }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-semibold text-emerald-600"
                                            >
                                                {{ fmt(p.monto) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ p.usuario_nombre ?? "-" }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ p.notas ?? "-" }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal pagos -->
        <div
            v-if="modalPagosAbierto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="cerrarModalPagos"
        >
            <div
                class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4"
                >
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Pagos · {{ compraActual?.folio }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{
                                compraActual?.proveedor?.nombre ??
                                compraActual?.proveedor?.nombre_comercial ??
                                ""
                            }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                        @click="cerrarModalPagos"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div
                    v-if="cargandoPagos"
                    class="flex items-center justify-center gap-2 py-14 text-sm text-slate-500"
                >
                    <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                    Cargando pagos...
                </div>

                <div v-else-if="datosPagos" class="space-y-5 p-5">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <InfoItem
                            label="Total compra"
                            :value="fmt(datosPagos.compra.total)"
                            strong
                        />
                        <InfoItem
                            label="Pagado"
                            :value="fmt(datosPagos.compra.pagado)"
                            tone="emerald"
                        />
                        <InfoItem
                            label="Saldo"
                            :value="fmt(datosPagos.compra.saldo)"
                            :tone="
                                Number(datosPagos.compra.saldo) > 0
                                    ? 'red'
                                    : 'emerald'
                            "
                        />
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                        >
                            <p class="text-xs font-medium text-slate-500">
                                Estatus
                            </p>
                            <span
                                class="mt-2 inline-flex"
                                :class="
                                    badgeEstatusPago(
                                        datosPagos.compra.estatus_pago,
                                    )
                                "
                            >
                                {{ datosPagos.compra.estatus_pago }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="Number(datosPagos.compra.saldo) > 0"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <h3 class="mb-3 text-sm font-semibold text-slate-900">
                            Registrar pago
                        </h3>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <BaseInput
                                v-model="formPago.monto"
                                type="number"
                                label="Monto"
                                min="0.01"
                                step="0.01"
                                :max="datosPagos.compra.saldo"
                                placeholder="0.00"
                            />

                            <BaseInput
                                v-model="formPago.fecha_pago"
                                type="date"
                                label="Fecha de pago"
                            />

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-500"
                                >
                                    Forma de pago
                                </label>
                                <select
                                    v-model="formPago.forma_pago"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">
                                        Transferencia
                                    </option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>

                            <BaseInput
                                v-model="formPago.referencia"
                                label="Referencia"
                                placeholder="No. transferencia, cheque..."
                            />

                            <div class="md:col-span-2">
                                <BaseInput
                                    v-model="formPago.notas"
                                    label="Notas"
                                    placeholder="Observaciones opcionales"
                                />
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <BtnGuardar
                                :disabled="guardandoPago"
                                :loading="guardandoPago"
                                @click="registrarPago"
                            >
                                {{
                                    guardandoPago
                                        ? "Guardando..."
                                        : "Registrar pago"
                                }}
                            </BtnGuardar>
                        </div>
                    </div>

                    <div>
                        <h3
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Historial de pagos
                        </h3>

                        <div
                            v-if="datosPagos.pagos.length"
                            class="overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                                    >
                                        <tr>
                                            <th class="px-4 py-3 text-left">
                                                Fecha
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Forma
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Referencia
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Monto
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Registró
                                            </th>
                                            <th
                                                class="px-4 py-3 text-right"
                                            ></th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="p in datosPagos.pagos"
                                            :key="p.id"
                                        >
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ fmtFecha(p.fecha_pago) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    :class="
                                                        badgePago(p.forma_pago)
                                                    "
                                                >
                                                    {{
                                                        labelFormaPago(
                                                            p.forma_pago,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ p.referencia ?? "-" }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-semibold text-emerald-600"
                                            >
                                                {{ fmt(p.monto) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-slate-500"
                                            >
                                                {{ p.user?.name ?? "-" }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                                    @click="eliminarPago(p)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400"
                        >
                            Sin pagos registrados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import axios from "axios";
import { confirm, toastError, toastWarning } from "@/lib/alert";

import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    Eye,
    FileSpreadsheet,
    FileText,
    Loader2,
    PackageSearch,
    ShoppingCart,
    Trash2,
    Wallet,
    WalletCards,
    X,
    Undo2,
    Ban,
} from "lucide-vue-next";

import BaseInput from "@/components/ui/BaseInput.vue";

const InfoItem = defineComponent({
    name: "InfoItem",
    props: {
        label: { type: String, required: true },
        value: { type: [String, Number], default: "-" },
        strong: { type: Boolean, default: false },
        tone: { type: String, default: "slate" },
    },
    setup(props) {
        return () =>
            h(
                "div",
                {
                    class: "rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3",
                },
                [
                    h(
                        "p",
                        {
                            class: "text-xs font-medium text-slate-500",
                        },
                        props.label,
                    ),
                    h(
                        "p",
                        {
                            class: [
                                "mt-1 truncate text-sm",
                                props.strong ? "font-semibold" : "font-medium",
                                props.tone === "emerald"
                                    ? "text-emerald-700"
                                    : props.tone === "red"
                                      ? "text-red-600"
                                      : "text-slate-800",
                            ],
                        },
                        props.value ?? "-",
                    ),
                ],
            );
    },
});

const props = defineProps({
    vista: { type: String, default: "mixta" },
});

const esVistaMixta = computed(() => props.vista === "mixta");
const esVistaCompras = computed(() => props.vista !== "pagos");
const esVistaPagos = computed(() => props.vista !== "compras");

const tituloPagina = computed(() =>
    props.vista === "pagos" ? "Pagos a proveedores" : "Consulta de compras",
);

const subtituloPagina = computed(() =>
    props.vista === "pagos"
        ? "Cuentas por pagar, saldos vencidos y registro de pagos"
        : "Historial y detalle de compras a proveedores",
);

const cargando = ref(false);
const cargandoCuentas = ref(false);
const exportandoLista = ref(null);
const exportandoDetalle = ref(false);
const cargandoDetalle = ref(false);
const cargandoPagos = ref(false);
const guardandoPago = ref(false);

const modalAbierto = ref(false);
const modalPagosAbierto = ref(false);

const tabActivo = ref(props.vista === "pagos" ? "cuentas" : "compras");
const datos = ref(null);
const datosCuentas = ref(null);
const detalle = ref(null);
const datosPagos = ref(null);
const compraActual = ref(null);
const proveedores = ref([]);

const filtros = ref({
    fecha_inicio: fechaOffset(-30),
    fecha_fin: fechaOffset(0),
    proveedor_id: "",
    estado: "",
    forma_pago: "",
    page: 1,
});

const filtrosCuentas = ref({
    estatus: "",
    page: 1,
});

const formPago = ref({
    monto: "",
    fecha_pago: fechaOffset(0),
    forma_pago: "efectivo",
    referencia: "",
    notas: "",
});

const tabClass =
    "inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-slate-500 transition hover:bg-white hover:text-slate-800";

const tabActivoClass =
    "inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm ring-1 ring-slate-200";

const resumenCompras = computed(() => {
    const t = datos.value?.totales ?? {};

    return [
        {
            label: "Compras",
            value: t.total_compras ?? 0,
            sub: "registros",
            icon: ShoppingCart,
            iconClass: "text-slate-400",
            valueClass: "text-slate-900",
        },
        {
            label: "Total",
            value: fmt(t.total_general),
            sub: `Subtotal ${fmt(t.total_subtotal)}`,
            icon: CheckCircle2,
            iconClass: "text-emerald-600",
            valueClass: "text-emerald-700",
        },
        {
            label: "Efectivo",
            value: fmt(t.total_efectivo),
            sub: "pagos directos",
            icon: Wallet,
            iconClass: "text-slate-500",
            valueClass: "text-slate-900",
        },
        {
            label: "Crédito",
            value: fmt(t.total_credito),
            sub: "compras a crédito",
            icon: Clock3,
            iconClass: "text-amber-600",
            valueClass: "text-amber-700",
        },
        {
            label: "Saldo",
            value: fmt(t.total_saldo_pendiente),
            sub: "pendiente",
            icon: AlertTriangle,
            iconClass: "text-red-600",
            valueClass: "text-red-600",
        },
    ];
});

function fechaOffset(dias) {
    const d = new Date();
    d.setDate(d.getDate() + dias);
    // Usar fecha local en vez de UTC
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}

function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
        minimumFractionDigits: 2,
    }).format(Number(v ?? 0));
}

function fmtFecha(f) {
    if (!f) return "-";

    const solo = f.toString().substring(0, 10);
    const [y, m, d] = solo.split("-");

    if (!y || !m || !d) return "-";

    const meses = [
        "ene",
        "feb",
        "mar",
        "abr",
        "may",
        "jun",
        "jul",
        "ago",
        "sep",
        "oct",
        "nov",
        "dic",
    ];

    return `${d} ${meses[Number(m) - 1]} ${y}`;
}

function getEstatusPago(c) {
    const saldo = Number(c?.saldo ?? c?.total ?? 0);
    if (saldo <= 0) return "pagado";
    if (c?.fecha_vencimiento) {
        const hoy = fechaOffset(0);
        if (c.fecha_vencimiento.toString().substring(0, 10) < hoy)
            return "vencido";
    }
    return "pendiente";
}

function labelFormaPago(fp) {
    const labels = {
        efectivo: "Efectivo",
        transferencia: "Transferencia",
        tarjeta: "Tarjeta",
        tarjeta_debito: "Tarjeta de débito",
        tarjeta_credito: "Tarjeta de crédito",
        credito: "Crédito",
        cheque: "Cheque",
    };

    return labels[fp] ?? fp ?? "-";
}

function badgePago(fp) {
    const base =
        "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize";

    const tonos = {
        efectivo: "bg-emerald-50 text-emerald-700",
        transferencia: "bg-sky-50 text-sky-700",
        tarjeta: "bg-indigo-50 text-indigo-700",
        tarjeta_debito: "bg-indigo-50 text-indigo-700",
        tarjeta_credito: "bg-purple-50 text-purple-700",
        credito: "bg-amber-50 text-amber-700",
        cheque: "bg-slate-100 text-slate-700",
    };

    return `${base} ${tonos[fp] ?? "bg-slate-100 text-slate-600"}`;
}

function badgeEstado(e) {
    const base =
        "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize";

    const tonos = {
        confirmada: "bg-emerald-50 text-emerald-700",
        borrador: "bg-slate-100 text-slate-600",
        devuelta_parcial: "bg-amber-50 text-amber-700",
        devuelta: "bg-violet-50 text-violet-700",
        cancelada: "bg-red-50 text-red-700",
    };

    return `${base} ${tonos[e] ?? "bg-slate-100 text-slate-600"}`;
}

function labelEstado(estado) {
    return {
        confirmada: "Confirmada",
        borrador: "Borrador",
        devuelta_parcial: "Devuelta parcial",
        devuelta: "Devuelta",
        cancelada: "Cancelada",
    }[estado] ?? estado;
}

function badgeEstatusPago(e) {
    const base =
        "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize";

    const tonos = {
        pendiente: "bg-amber-50 text-amber-700",
        pagado: "bg-emerald-50 text-emerald-700",
        vencido: "bg-red-50 text-red-700",
    };

    return `${base} ${tonos[e] ?? "bg-slate-100 text-slate-600"}`;
}

function aplicarFiltros() {
    filtros.value.page = 1;

    if (esVistaCompras.value) {
        buscar();
    }

    if (esVistaPagos.value) {
        filtrosCuentas.value.page = 1;
        cargarCuentas();
    }
}

function aplicarFiltrosCuentas() {
    filtrosCuentas.value.page = 1;
    cargarCuentas();
}

function refrescar() {
    if (esVistaCompras.value) {
        buscar();
    }

    if (esVistaPagos.value) {
        cargarCuentas();
    }
}

async function buscar() {
    cargando.value = true;

    try {
        const { data } = await axios.get("/api/reportes/compras", {
            params: filtros.value,
        });

        datos.value = data;
    } catch (e) {
        console.error(e);
        toastError("Error al cargar compras");
    } finally {
        cargando.value = false;
    }
}

async function cambiarPagina(page) {
    if (!datos.value?.compras) return;
    if (page < 1 || page > datos.value.compras.last_page) return;

    filtros.value.page = page;
    await buscar();
}

async function activarCuentas() {
    tabActivo.value = "cuentas";

    if (!datosCuentas.value) {
        await cargarCuentas();
    }
}

async function cargarCuentas() {
    cargandoCuentas.value = true;

    try {
        const params = {
            proveedor_id: filtros.value.proveedor_id,
            fecha_inicio: filtros.value.fecha_inicio,
            fecha_fin: filtros.value.fecha_fin,
            estatus: filtrosCuentas.value.estatus,
            page: filtrosCuentas.value.page,
        };

        const { data } = await axios.get(
            "/api/reportes/compras/cuentas-por-pagar",
            { params },
        );

        datosCuentas.value = data;
    } catch (e) {
        console.error(e);
        toastError("Error al cargar cuentas por pagar");
    } finally {
        cargandoCuentas.value = false;
    }
}

async function cambiarPaginaCuentas(page) {
    if (!datosCuentas.value?.cuentas) return;
    if (page < 1 || page > datosCuentas.value.cuentas.last_page) return;

    filtrosCuentas.value.page = page;
    await cargarCuentas();
}

async function verDetalle(compra) {
    modalAbierto.value = true;
    cargandoDetalle.value = true;
    detalle.value = null;

    try {
        const { data } = await axios.get(`/api/reportes/compras/${compra.id}`);
        detalle.value = data;
    } catch (e) {
        console.error(e);
        toastError("Error al cargar el detalle");
    } finally {
        cargandoDetalle.value = false;
    }
}

function cerrarModal() {
    modalAbierto.value = false;
    detalle.value = null;
}

async function exportarLista(formato) {
    exportandoLista.value = formato;
    try {
        const resp = await axios.get('/api/reportes/compras/exportar', {
            params: { ...filtros.value, formato },
            responseType: 'blob',
        });
        const ext  = formato === 'excel' ? 'xlsx' : 'pdf';
        const mime = formato === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/pdf';
        const url = URL.createObjectURL(new Blob([resp.data], { type: mime }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `compras_${filtros.value.fecha_inicio}_${filtros.value.fecha_fin}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('exportarLista', e);
        toastError('Error al exportar');
    } finally {
        exportandoLista.value = null;
    }
}

async function exportarDetalleCompra() {
    if (!detalle.value?.compra?.id) return;
    exportandoDetalle.value = true;
    try {
        const resp = await axios.get(`/api/reportes/compras/${detalle.value.compra.id}/exportar-pdf`, {
            responseType: 'blob',
        });
        const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `compra_${detalle.value.compra.folio ?? detalle.value.compra.id}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('exportarDetalle', e);
        toastError('Error al exportar el detalle');
    } finally {
        exportandoDetalle.value = false;
    }
}

async function cargarProveedores() {
    try {
        const { data } = await axios.get("/api/proveedores?per_page=200");
        proveedores.value = data.data ?? data;
    } catch (e) {
        console.error(e);
        toastError("Error al cargar proveedores");
    }
}

function resetFormPago() {
    formPago.value = {
        monto: "",
        fecha_pago: fechaOffset(0),
        forma_pago: "efectivo",
        referencia: "",
        notas: "",
    };
}

async function abrirPagos(compra) {
    compraActual.value = compra;
    modalPagosAbierto.value = true;
    cargandoPagos.value = true;
    datosPagos.value = null;
    resetFormPago();

    try {
        const { data } = await axios.get(`/api/compras/${compra.id}/pagos`);
        datosPagos.value = data;
    } catch (e) {
        console.error(e);
        toastError("Error al cargar los pagos");
    } finally {
        cargandoPagos.value = false;
    }
}

function cerrarModalPagos() {
    modalPagosAbierto.value = false;
    datosPagos.value = null;
    compraActual.value = null;
}

function actualizarFilas(data) {
    if (!compraActual.value) return;

    const actualizar = (lista) => {
        const fila = lista?.find((c) => c.id === compraActual.value.id);

        if (fila) {
            fila.pagado = data.compra.pagado;
            fila.saldo = data.compra.saldo;
            fila.estatus_pago = data.compra.estatus_pago;
        }
    };

    actualizar(datos.value?.compras?.data);
    actualizar(datosCuentas.value?.cuentas?.data);
}

async function registrarPago() {
    const monto = Number(formPago.value.monto);

    if (!monto || monto <= 0) {
        toastWarning("Ingresa un monto válido");
        return;
    }

    guardandoPago.value = true;

    try {
        await axios.post(
            `/api/compras/${compraActual.value.id}/pagos`,
            formPago.value,
        );

        const { data } = await axios.get(
            `/api/compras/${compraActual.value.id}/pagos`,
        );

        datosPagos.value = data;
        actualizarFilas(data);
        resetFormPago();

        if (esVistaCompras.value) {
            await buscar();
        }

        if (esVistaPagos.value) {
            await cargarCuentas();
        }
    } catch (e) {
        toastError(e.response?.data?.message ?? "Error al registrar el pago");
    } finally {
        guardandoPago.value = false;
    }
}

async function eliminarPago(pago) {
    const ok = await confirm({
        title: "¿Eliminar pago?",
        text: `¿Eliminar el pago de ${fmt(pago.monto)} del ${fmtFecha(pago.fecha_pago)}?`,
        confirmText: "Sí, eliminar",
    });

    if (!ok) return;

    try {
        await axios.delete(
            `/api/compras/${compraActual.value.id}/pagos/${pago.id}`,
        );

        const { data } = await axios.get(
            `/api/compras/${compraActual.value.id}/pagos`,
        );

        datosPagos.value = data;
        actualizarFilas(data);

        if (esVistaCompras.value) {
            await buscar();
        }

        if (esVistaPagos.value) {
            await cargarCuentas();
        }
    } catch (e) {
        toastError("Error al eliminar el pago");
    }
}

onMounted(async () => {
    await cargarProveedores();

    if (esVistaCompras.value) {
        await buscar();
    } else {
        datos.value = {};
    }

    if (esVistaPagos.value) {
        await cargarCuentas();
    }
});
</script>
