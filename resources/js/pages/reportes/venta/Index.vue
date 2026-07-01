<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- TOPBAR -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-sm"
                    >
                        <ReceiptText class="h-5 w-5" />
                    </div>

                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900 sm:text-xl"
                        >
                            Consulta de ventas
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ sucursalNombre }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="!f.por_dia"
                    class="hidden items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 sm:inline-flex"
                >
                    <Rows3 class="h-4 w-4" />
                    {{ pag.total }} registros
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- FILTROS -->
            <section
                class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Filtros
                        </h2>
                        <p class="text-sm text-slate-500">
                            Filtra por fecha, cajero, forma de pago, estado,
                            folio o producto.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                        <button
                            type="button"
                            @click="togglePorDia"
                            class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition"
                            :class="
                                f.por_dia
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                            "
                        >
                            <CalendarDays class="h-4 w-4" />
                            {{ f.por_dia ? "Agrupado por día" : "Vista individual" }}
                        </button>

                        <button
                            type="button"
                            :disabled="exportando"
                            @click="exportar('excel')"
                            class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:opacity-50"
                        >
                            <Loader2 v-if="exportando === 'excel'" class="h-4 w-4 animate-spin" />
                            <FileSpreadsheet v-else class="h-4 w-4" />
                            Excel
                        </button>

                        <button
                            type="button"
                            :disabled="exportando"
                            @click="exportar('pdf')"
                            class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                        >
                            <Loader2 v-if="exportando === 'pdf'" class="h-4 w-4 animate-spin" />
                            <FileText v-else class="h-4 w-4" />
                            PDF
                        </button>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-4"
                >
                    <BaseInput
                        v-model="f.fecha_desde"
                        label="Desde"
                        type="date"
                        @change="buscar"
                    />

                    <BaseInput
                        v-model="f.fecha_hasta"
                        label="Hasta"
                        type="date"
                        @change="buscar"
                    />

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Cajero
                        </label>
                        <div class="relative">
                            <Users
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="f.user_id"
                                @change="buscar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todos</option>
                                <option
                                    v-for="u in cajerosDisponibles"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Forma de pago
                        </label>
                        <div class="relative">
                            <Wallet
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="f.forma_pago"
                                @change="buscar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todas</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">
                                    Transferencia
                                </option>
                                <option value="credito">Crédito</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Estado
                        </label>
                        <div class="relative">
                            <ShieldCheck
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="f.estado"
                                @change="buscar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todos</option>
                                <option value="confirmada">Confirmada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <BaseInput
                        v-model="f.folio"
                        label="Folio"
                        placeholder="TKT-…"
                        @input="debounce"
                    >
                        <template #icon>
                            <Hash class="h-4 w-4" />
                        </template>
                    </BaseInput>

                    <BaseInput
                        v-model="f.producto"
                        label="Producto"
                        placeholder="Nombre o código…"
                        @input="debounce"
                    >
                        <template #icon>
                            <Search class="h-4 w-4" />
                        </template>
                    </BaseInput>

                    <div class="flex items-end">
                        <button
                            type="button"
                            @click="limpiarFiltros"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- TOTALES -->
                <div v-if="totales" class="border-t border-slate-100 px-5 py-5">
                    <div
                        class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5"
                    >
                        <div
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700"
                            >
                                Venta neta
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-emerald-800"
                            >
                                {{ fmt(totales.total) }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                            >
                                Ventas confirmadas
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-slate-900"
                            >
                                {{ totales.confirmadas }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                            >
                                Canceladas
                            </p>
                            <p class="mt-2 text-lg font-semibold text-red-600">
                                {{ totales.canceladas || 0 }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                            >
                                Venta promedio
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-slate-900"
                            >
                                {{ fmt(totales.ticket_prom) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Total vendido / ventas confirmadas
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                            >
                                Descuentos
                            </p>
                            <p class="mt-2 text-lg font-semibold text-red-600">
                                {{ fmt(totales.descuentos) }}
                            </p>
                        </div>

                        <div
                            v-for="fp in formasPago"
                            :key="fp.key"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                            >
                                {{ fp.label }}
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-slate-900"
                            >
                                {{ fmt(totales[fp.key]) }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AGRUPADO POR DÍA -->
            <section v-if="f.por_dia" class="space-y-4">
                <div v-if="cargando" class="space-y-3">
                    <div
                        v-for="n in 5"
                        :key="n"
                        class="h-12 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>

                <div
                    v-else-if="datosAgrupados.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Resumen por día
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-slate-500">
                                    <th class="px-4 py-3 font-medium">Fecha</th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Ventas
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Cancel.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Efectivo
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Tarjeta
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Trans.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Crédito
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Desc.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Total
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Venta prom.
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="d in datosAgrupados"
                                    :key="d.fecha"
                                    class="transition hover:bg-slate-50"
                                >
                                    <td
                                        class="whitespace-nowrap px-4 py-3 font-medium text-slate-700"
                                    >
                                        {{ fmtFecha(d.fecha) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-slate-700"
                                    >
                                        {{ d.num_ventas }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right"
                                        :class="
                                            d.canceladas > 0
                                                ? 'text-red-600'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{ d.canceladas || "—" }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono"
                                        :class="
                                            d.efectivo > 0
                                                ? 'text-slate-700'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{
                                            d.efectivo > 0
                                                ? fmt(d.efectivo)
                                                : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono"
                                        :class="
                                            d.tarjeta > 0
                                                ? 'text-slate-700'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{
                                            d.tarjeta > 0 ? fmt(d.tarjeta) : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono"
                                        :class="
                                            d.transferencia > 0
                                                ? 'text-slate-700'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{
                                            d.transferencia > 0
                                                ? fmt(d.transferencia)
                                                : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono"
                                        :class="
                                            d.credito > 0
                                                ? 'text-slate-700'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{
                                            d.credito > 0 ? fmt(d.credito) : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-red-600"
                                    >
                                        {{
                                            d.descuentos > 0
                                                ? fmt(d.descuentos)
                                                : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-emerald-700"
                                    >
                                        {{ fmt(d.total) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-500"
                                    >
                                        {{ fmt(d.ticket_prom) }}
                                    </td>
                                </tr>
                            </tbody>

                            <tfoot
                                v-if="totales"
                                class="border-t border-slate-200 bg-slate-50"
                            >
                                <tr class="font-semibold text-slate-900">
                                    <td class="px-4 py-3">Total</td>
                                    <td class="px-4 py-3 text-right">
                                        {{ totales.confirmadas }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-red-600"
                                    >
                                        {{ totales.canceladas || "—" }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totales.efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totales.tarjeta) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totales.transferencia) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totales.credito) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-red-600"
                                    >
                                        {{ fmt(totales.descuentos) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-emerald-700"
                                    >
                                        {{ fmt(totales.total) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-500"
                                    >
                                        {{ fmt(totales.ticket_prom) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm"
                >
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                    >
                        <Inbox class="h-6 w-6" />
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Sin ventas para los filtros seleccionados
                    </h3>
                </div>
            </section>

            <!-- LISTADO INDIVIDUAL -->
            <section v-else class="space-y-4">
                <div v-if="cargando && !ventas.length" class="space-y-3">
                    <div
                        v-for="n in 8"
                        :key="n"
                        class="h-12 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>

                <div
                    v-else-if="ventas.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Ventas
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-slate-500">
                                    <th class="px-4 py-3 font-medium">Folio</th>
                                    <th class="px-4 py-3 font-medium">
                                        Fecha / Hora
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Cajero
                                    </th>
                                    <th class="px-4 py-3 font-medium">Forma</th>
                                    <th class="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Subtotal
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Desc.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Total
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Acciones
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <template v-for="v in ventas" :key="v.id">
                                    <tr
                                        class="cursor-pointer transition hover:bg-slate-50"
                                        :class="[
                                            expandido === v.id
                                                ? 'bg-slate-50'
                                                : '',
                                            v.estado === 'cancelada'
                                                ? 'opacity-60'
                                                : '',
                                        ]"
                                        @click="toggleDetalle(v.id)"
                                    >
                                        <td
                                            class="whitespace-nowrap px-4 py-3 font-semibold text-emerald-700"
                                        >
                                            {{ v.folio }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-4 py-3 text-slate-500"
                                        >
                                            {{ fmtVentaDatetime(v) }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">
                                            {{ v.user?.name ?? "—" }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                                :class="
                                                    badgeFormaPagoClass(
                                                        v.forma_pago,
                                                    )
                                                "
                                            >
                                                {{ v.forma_pago }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                                :class="
                                                    badgeEstadoClass(v.estado)
                                                "
                                            >
                                                {{ v.estado }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono text-slate-700"
                                        >
                                            {{ fmt(v.subtotal) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono text-red-600"
                                        >
                                            {{
                                                +v.descuento > 0
                                                    ? "−" + fmt(v.descuento)
                                                    : "—"
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono font-semibold text-emerald-700"
                                        >
                                            {{ fmt(v.total) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-slate-400"
                                        >
                                            <div
                                                class="flex items-center justify-end gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-emerald-600 disabled:cursor-not-allowed disabled:opacity-60"
                                                    :disabled="reimprimiendoVentaId === v.id"
                                                    :title="reimprimiendoVentaId === v.id ? 'Imprimiendo ticket' : 'Reimprimir ticket'"
                                                    @click.stop="
                                                        reimprimirVenta(v.id)
                                                    "
                                                >
                                                    <Loader2 v-if="reimprimiendoVentaId === v.id" class="h-4 w-4 animate-spin" />
                                                    <Printer v-else class="h-4 w-4" />
                                                </button>

                                                <ChevronDown
                                                    class="h-4 w-4 transition"
                                                    :class="
                                                        expandido === v.id
                                                            ? 'rotate-180'
                                                            : ''
                                                    "
                                                />
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- DETALLE -->
                                    <tr
                                        v-if="expandido === v.id"
                                        class="bg-slate-50"
                                    >
                                        <td
                                            colspan="9"
                                            class="border-t border-slate-100 px-0 py-0"
                                        >
                                            <div
                                                v-if="detallesCargando"
                                                class="flex items-center gap-3 px-5 py-4 text-sm text-slate-500"
                                            >
                                                <Loader2
                                                    class="h-4 w-4 animate-spin text-emerald-600"
                                                />
                                                Cargando detalle…
                                            </div>

                                            <div
                                                v-else-if="detalleActual"
                                                class="space-y-4 px-5 py-5"
                                            >
                                                <!-- META -->
                                                <div
                                                    class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:flex-row md:items-center md:justify-between"
                                                >
                                                    <div class="space-y-1">
                                                        <p
                                                            v-if="
                                                                detalleActual.notas
                                                            "
                                                            class="text-sm italic text-slate-600"
                                                        >
                                                            Nota:
                                                            {{
                                                                detalleActual.notas
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-xs text-slate-400"
                                                        >
                                                            ID
                                                            {{
                                                                detalleActual.id
                                                            }}
                                                        </p>
                                                    </div>

                                                </div>

                                                <!-- DETALLE DE LÍNEAS -->
                                                <div
                                                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                                                >
                                                    <div
                                                        class="overflow-x-auto"
                                                    >
                                                        <table
                                                            class="min-w-full text-sm"
                                                        >
                                                            <thead
                                                                class="bg-slate-50"
                                                            >
                                                                <tr
                                                                    class="text-left text-slate-500"
                                                                >
                                                                    <th
                                                                        class="px-4 py-3 font-medium"
                                                                    >
                                                                        Producto
                                                                    </th>
                                                                    <th
                                                                        class="px-4 py-3 font-medium"
                                                                    >
                                                                        Código
                                                                    </th>
                                                                    <th
                                                                        class="px-4 py-3 font-medium"
                                                                    >
                                                                        SKU /
                                                                        Variante
                                                                    </th>
                                                                    <th
                                                                        class="px-4 py-3 text-right font-medium"
                                                                    >
                                                                        Cant.
                                                                    </th>
                                                                    <th
                                                                        class="px-4 py-3 text-right font-medium"
                                                                    >
                                                                        Precio
                                                                        venta
                                                                    </th>
                                                                    <th
                                                                        class="px-4 py-3 text-right font-medium"
                                                                    >
                                                                        Subtotal
                                                                    </th>
                                                                </tr>
                                                            </thead>

                                                            <tbody
                                                                class="divide-y divide-slate-100"
                                                            >
                                                                <tr
                                                                    v-for="d in detalleActual.detalles"
                                                                    :key="d.id"
                                                                >
                                                                    <td
                                                                        class="px-4 py-3"
                                                                    >
                                                                        <div
                                                                            class="font-medium text-slate-900"
                                                                        >
                                                                            {{
                                                                                d
                                                                                    .producto
                                                                                    ?.nombre ??
                                                                                "—"
                                                                            }}
                                                                        </div>
                                                                        <div
                                                                            v-if="
                                                                                d.motivo_precio
                                                                            "
                                                                            class="mt-0.5 text-xs italic text-slate-500"
                                                                        >
                                                                            {{
                                                                                d.motivo_precio
                                                                            }}
                                                                        </div>
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-3 text-slate-500"
                                                                    >
                                                                        {{
                                                                            d
                                                                                .producto
                                                                                ?.codigo ??
                                                                            "—"
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-3 text-slate-500"
                                                                    >
                                                                      
                                                                        <span
                                                                            v-if="
                                                                                d.variante
                                                                            "
                                                                        >
                                                                            {{
                                                                                d
                                                                                    .variante
                                                                                    .sku ??
                                                                                "—"
                                                                            }}
                                                                            <em
                                                                                v-if="
                                                                                    d.nombre_variante
                                                                                "
                                                                            >
                                                                                ·
                                                                                {{
                                                                                    d.nombre_variante
                                                                                }}</em
                                                                            >
                                                                        </span>
                                                                        <span
                                                                            v-else
                                                                            >—</span
                                                                        >
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                                                    >
                                                                        {{
                                                                            d.cantidad
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                                                    >
                                                                        {{
                                                                            fmt(
                                                                                d.precio_venta,
                                                                            )
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-3 text-right font-mono font-semibold text-emerald-700"
                                                                    >
                                                                        {{
                                                                            fmt(
                                                                                d.subtotal,
                                                                            )
                                                                        }}
                                                                    </td>
                                                                </tr>
                                                            </tbody>

                                                            <tfoot
                                                                class="border-t border-slate-200 bg-slate-50"
                                                            >
                                                                <tr
                                                                    class="font-semibold text-slate-900"
                                                                >
                                                                    <td
                                                                        colspan="4"
                                                                        class="px-4 py-3"
                                                                    >
                                                                        Totales
                                                                    </td>
                                                                    <td
                                                                        class="px-4 py-3 text-right font-mono text-emerald-700"
                                                                    >
                                                                        {{
                                                                            fmt(
                                                                                detalleActual.total,
                                                                            )
                                                                        }}
                                                                    </td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div
                        class="flex items-center justify-center gap-3 border-t border-slate-100 px-5 py-4"
                    >
                        <button
                            type="button"
                            :disabled="pag.current_page <= 1"
                            @click="cambiarPag(pag.current_page - 1)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <span class="text-sm text-slate-600">
                            Página {{ pag.current_page }} de
                            {{ pag.last_page }} · {{ pag.total }} registros
                        </span>

                        <button
                            type="button"
                            :disabled="pag.current_page >= pag.last_page"
                            @click="cambiarPag(pag.current_page + 1)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm"
                >
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                    >
                        <Inbox class="h-6 w-6" />
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Sin ventas para los filtros seleccionados
                    </h3>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import {
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileSpreadsheet,
    FileText,
    Hash,
    Inbox,
    Loader2,
    Printer,
    ReceiptText,
    RotateCcw,
    Rows3,
    Search,
    ShieldCheck,
    Users,
    Wallet,
} from "lucide-vue-next";
import { crearTicketVenta } from "@/helpers/tickets/ticketVenta";
import { imprimirTicketVenta } from "@/helpers/tickets/imprimirTicketVenta";
import { obtenerImpresoraTicket } from "@/helpers/qzTray";
import { toastError } from "@/lib/alert";

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
    sucursalNombre: { type: String, default: "Principal" },
    apiBase: { type: String, default: "/api/reportes/ventas" },
    cajeros: { type: Array, default: () => [] },
});
const reimprimiendoVentaId = ref(null);
const exportando = ref(null);

// ── Config estática ────────────────────────────────────────────────────────
const formasPago = [
    { key: "efectivo", label: "Efectivo" },
    { key: "tarjeta", label: "Tarjeta" },
    { key: "transferencia", label: "Transferencia" },
    { key: "credito", label: "Crédito" },
];

// ── Estado ─────────────────────────────────────────────────────────────────
const cargando = ref(false);
const ventas = ref([]);
const datosAgrupados = ref([]);
const totales = ref(null);
const cajerosDisponibles = ref(props.cajeros);
const pag = ref({ current_page: 1, last_page: 1, total: 0 });
const expandido = ref(null);
const detalleActual = ref(null);
const detallesCargando = ref(false);

let timer = null;

const f = reactive({
    fecha_desde: hoy(),
    fecha_hasta: hoy(),
    user_id: "",
    forma_pago: "",
    estado: "",
    folio: "",
    producto: "",
    por_dia: false,
    pagina: 1,
});

// ── Ciclo de vida ──────────────────────────────────────────────────────────
onMounted(() => cargarVentas());

// ── Métodos ────────────────────────────────────────────────────────────────
function buscar() {
    f.pagina = 1;
    expandido.value = null;
    detalleActual.value = null;
    cargarVentas();
}

function debounce() {
    clearTimeout(timer);
    timer = setTimeout(buscar, 380);
}

async function cargarVentas() {
    cargando.value = true;

    try {
        const { data } = await axios.get(props.apiBase, { params: params() });

        totales.value = data.totales;
        cajerosDisponibles.value = data.cajeros ?? cajerosDisponibles.value;

        if (f.por_dia) {
            datosAgrupados.value = data.datos ?? [];
            ventas.value = [];
        } else {
            ventas.value = data.ventas?.data ?? [];
            datosAgrupados.value = [];
            pag.value = {
                current_page: data.ventas?.current_page ?? 1,
                last_page: data.ventas?.last_page ?? 1,
                total: data.ventas?.total ?? 0,
            };
        }
    } catch (e) {
        console.error("cargarVentas", e);
    } finally {
        cargando.value = false;
    }
}

async function toggleDetalle(id) {
    if (expandido.value === id) {
        expandido.value = null;
        detalleActual.value = null;
        return;
    }

    expandido.value = id;
    detalleActual.value = null;
    detallesCargando.value = true;

    try {
        const { data } = await axios.get(`${props.apiBase}/${id}`);
        detalleActual.value = data;
    } catch (e) {
        console.error("toggleDetalle", e);
    } finally {
        detallesCargando.value = false;
    }
}

async function reimprimirVenta(id) {
    if (reimprimiendoVentaId.value) return;
    reimprimiendoVentaId.value = id;
    try {
        const { data } = await axios.get(`${props.apiBase}/${id}`);
        await imprimirTicketVenta(crearTicketVenta({ ...data, reimpresion: true }), obtenerImpresoraTicket());
    } catch (e) {
        console.error("reimprimirVenta", e);
        toastError("No se pudo reimprimir el ticket.");
    } finally {
        reimprimiendoVentaId.value = null;
    }
}

async function exportar(formato) {
    exportando.value = formato;
    try {
        const resp = await axios.get(`${props.apiBase}/exportar`, {
            params: { ...params(), formato, por_pagina: undefined, page: undefined },
            responseType: 'blob',
        });
        const ext  = formato === 'excel' ? 'xlsx' : 'pdf';
        const mime = formato === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/pdf';
        const url = URL.createObjectURL(new Blob([resp.data], { type: mime }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `ventas_${f.fecha_desde ?? ''}_${f.fecha_hasta ?? ''}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('exportar ventas', e);
    } finally {
        exportando.value = null;
    }
}

function cambiarPag(p) {
    f.pagina = p;
    cargarVentas();
}

function togglePorDia() {
    f.por_dia = !f.por_dia;
    buscar();
}

function limpiarFiltros() {
    f.fecha_desde = hoy();
    f.fecha_hasta = hoy();
    f.user_id = "";
    f.forma_pago = "";
    f.estado = "";
    f.folio = "";
    f.producto = "";
    f.por_dia = false;
    f.pagina = 1;
    expandido.value = null;
    detalleActual.value = null;
    cargarVentas();
}

// ── Helpers ────────────────────────────────────────────────────────────────
function params() {
    return {
        fecha_desde: f.fecha_desde || undefined,
        fecha_hasta: f.fecha_hasta || undefined,
        user_id: f.user_id || undefined,
        forma_pago: f.forma_pago || undefined,
        estado: f.estado || undefined,
        folio: f.folio || undefined,
        producto: f.producto || undefined,
        por_dia: f.por_dia ? 1 : 0,
        page: f.pagina,
        por_pagina: 30,
    };
}

function badgeFormaPagoClass(forma) {
    if (forma === "efectivo") return "bg-emerald-50 text-emerald-700";
    if (forma === "tarjeta") return "bg-sky-50 text-sky-700";
    if (forma === "transferencia") return "bg-violet-50 text-violet-700";
    if (forma === "credito") return "bg-amber-50 text-amber-700";
    return "bg-slate-100 text-slate-700";
}

function badgeEstadoClass(estado) {
    if (estado === "confirmada") return "bg-emerald-50 text-emerald-700";
    if (estado === "cancelada") return "bg-red-50 text-red-700";
    return "bg-slate-100 text-slate-700";
}

// ── Formatters ─────────────────────────────────────────────────────────────
const mxn = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});

function fmt(v) {
    return mxn.format(+v || 0);
}

function fmtVentaDatetime(venta) {
    if (!venta?.fecha) return "—";

    const fecha = String(venta.fecha).slice(0, 10);
    const fechaFormateada = new Date(`${fecha}T12:00:00`).toLocaleDateString(
        "es-MX",
        {
            day: "2-digit",
            month: "short",
            year: "numeric",
        },
    );

    if (!venta.created_at) return fechaFormateada;

    const hora = new Date(venta.created_at).toLocaleTimeString("es-MX", {
        hour: "2-digit",
        minute: "2-digit",
    });

    return `${fechaFormateada}, ${hora}`;
}

function fmtFecha(s) {
    if (!s) return "—";
    return new Date(s + "T12:00:00").toLocaleDateString("es-MX", {
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function hoy() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, "0");
    const d = String(now.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}
</script>
