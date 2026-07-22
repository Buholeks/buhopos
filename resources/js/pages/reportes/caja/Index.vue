<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- TOPBAR -->
        <div
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-sm"
                    >
                        <Wallet class="h-5 w-5" />
                    </div>

                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900 sm:text-xl"
                        >
                            Reportes de caja
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Control de turnos ·
                            <span class="font-medium text-slate-700">
                                {{ sucursalNombre }}
                            </span>
                        </p>
                    </div>
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
                            Ajusta el rango, cajero y estado del corte.
                        </p>
                    </div>

                </div>

                <div
                    class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-5"
                >
                    <BaseInput
                        v-model="filtros.fecha_desde"
                        label="Desde"
                        type="date"
                        @change="reiniciarYCargar"
                    />

                    <BaseInput
                        v-model="filtros.fecha_hasta"
                        label="Hasta"
                        type="date"
                        @change="reiniciarYCargar"
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
                                v-model="filtros.user_id"
                                @change="reiniciarYCargar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todos</option>
                                <option
                                    v-for="u in cajeros"
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
                            Estado
                        </label>
                        <div class="relative">
                            <ShieldCheck
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <select
                                v-model="filtros.estado"
                                @change="reiniciarYCargar"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            >
                                <option value="">Todos</option>
                                <option value="abierto">Abierto</option>
                                <option value="cerrado">Cerrado</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-end gap-2">
                        <button
                            type="button"
                            @click="limpiarFiltros"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Limpiar
                        </button>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-5 py-4"
                >
                    <button
                        type="button"
                        @click="exportar('excel')"
                        :disabled="exportando"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="exportando === 'excel'"
                            class="h-4 w-4 animate-spin"
                        />
                        <FileSpreadsheet v-else class="h-4 w-4" />
                        Excel
                    </button>
                    <button
                        type="button"
                        @click="exportar('pdf')"
                        :disabled="exportando"
                        class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="exportando === 'pdf'"
                            class="h-4 w-4 animate-spin"
                        />
                        <FileText v-else class="h-4 w-4" />
                        PDF
                    </button>
                </div>
            </section>

            <!-- VISTA COMPARATIVO -->
            <section v-if="!corteDetalle" class="space-y-4">
                <!-- LISTADO DE CORTES -->
                <div
                    v-if="cortesData.length"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                    >
                        <h2 class="text-sm font-semibold text-slate-900">
                            Listado de cortes
                        </h2>

                        <div
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600"
                        >
                            <Rows3 class="h-4 w-4" />
                            {{ cortesData.length }} registros
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-slate-500">
                                    <th class="px-4 py-3 font-medium">#</th>
                                    <th class="px-4 py-3 font-medium">
                                        Cajero
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Apertura
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Cierre
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Esperado ef.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        &Delta; Ef.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Contado ef.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Cobrado caja
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Total vendido
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Acci&oacute;n
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="c in cortesData"
                                    :key="c.id"
                                    class="cursor-pointer transition hover:bg-slate-50"
                                    @click="abrirDetalle(c.id)"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-slate-500"
                                    >
                                        {{ c.id }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ c.user?.name || "Sin cajero" }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="font-medium text-slate-700">
                                            {{ fmtDate(fechaAperturaCorte(c)) }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ fmtTime(fechaAperturaCorte(c)) }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <template v-if="c.fecha_cierre">
                                            <div class="font-medium text-slate-700">
                                                {{ fmtDate(c.fecha_cierre) }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{ fmtTime(c.fecha_cierre) }}
                                            </div>
                                        </template>
                                        <span v-else class="text-slate-400">
                                            Sin cierre
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                            :class="badgeEstadoClass(c.estado)"
                                        >
                                            {{ c.estado }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                    >
                                        {{ fmt(c.esperado_efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="inline-flex min-w-[104px] justify-end rounded-full px-2.5 py-1 font-mono text-xs font-semibold"
                                            :class="diferenciaClass(c.dif_efectivo)"
                                        >
                                            {{ fmtDif(c.dif_efectivo) }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                    >
                                        {{ fmt(c.contado_efectivo) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-emerald-700"
                                    >
                                        {{ fmt(c.total_cobrado_caja ?? c.total_ventas) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-slate-900"
                                    >
                                        {{ fmt(c.total_vendido ?? c.total_ventas) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                            @click.stop="abrirDetalle(c.id)"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            Ver
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot
                                v-if="cortesData.length"
                                class="border-t-2 border-slate-300 bg-slate-100 font-semibold text-slate-900"
                            >
                                <tr>
                                    <td class="px-4 py-3" colspan="5">
                                        Totales del listado filtrado
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totalesListado.esperado_efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="inline-flex min-w-[104px] justify-end rounded-full px-2.5 py-1 font-mono text-xs font-semibold"
                                            :class="diferenciaClass(totalesListado.dif_efectivo)"
                                        >
                                            {{ fmtDif(totalesListado.dif_efectivo) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ fmt(totalesListado.contado_efectivo) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-700">
                                        {{ fmt(totalesListado.total_cobrado_caja) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-900">
                                        {{ fmt(totalesListado.total_vendido) }}
                                    </td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div
                        v-if="paginacion"
                        class="flex items-center justify-center gap-3 border-t border-slate-100 px-5 py-4"
                    >
                        <button
                            type="button"
                            :disabled="paginacion.current_page <= 1"
                            @click="cambiarPagina(paginacion.current_page - 1)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <span class="text-sm text-slate-600">
                            {{ paginacion.current_page }} /
                            {{ paginacion.last_page }}
                        </span>

                        <button
                            type="button"
                            :disabled="
                                paginacion.current_page >= paginacion.last_page
                            "
                            @click="cambiarPagina(paginacion.current_page + 1)"
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
                        Sin datos para mostrar
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Prueba ajustando los filtros del reporte.
                    </p>
                </div>
            </section>

            <!-- VISTA DETALLE -->
            <section
                v-if="corteDetalle"
                class="space-y-6"
            >
                <div class="flex items-center justify-between gap-3">
                    <button
                        type="button"
                        @click="cerrarDetalle"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Volver
                    </button>

                    <span
                        class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold capitalize"
                        :class="badgeEstadoClass(corteDetalle.corte.estado)"
                    >
                        {{ corteDetalle.corte.estado }}
                    </span>
                </div>

                <!-- HERO DETALLE -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600"
                            >
                                <Receipt class="h-5 w-5" />
                            </div>

                            <div>
                                <h2
                                    class="text-xl font-semibold tracking-tight text-slate-900"
                                >
                                    Corte #{{ corteDetalle.corte.id }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        corteDetalle.corte.user?.name ||
                                        "Sin cajero"
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Apertura:
                                    {{
                                        fmtDatetime(
                                            fechaAperturaCorte(
                                                corteDetalle.corte,
                                            ),
                                        )
                                    }}
                                    <span class="mx-1 text-slate-300">|</span>
                                    Cierre:
                                    {{
                                        corteDetalle.corte.fecha_cierre
                                            ? fmtDatetime(
                                                  corteDetalle.corte
                                                      .fecha_cierre,
                                              )
                                            : "Sin cierre"
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <div
                            v-for="m in metricasDetalle"
                            :key="m.label"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400"
                            >
                                {{ m.label }}
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold tracking-tight"
                                :class="metricaColorClass(m.color)"
                            >
                                {{ m.valor }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <!-- VENTAS POR FORMA -->
                    <div
                        class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-slate-900">
                                Ventas por forma de pago
                            </h3>
                            <p class="text-sm text-slate-500">
                                Distribución del total vendido.
                            </p>
                        </div>

                        <div class="space-y-5 px-5 py-5">
                            <div
                                v-for="fp in formasPago"
                                :key="fp.key"
                                class="space-y-2"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        {{ fp.label }}
                                    </span>
                                    <span
                                        class="font-mono text-sm text-slate-600"
                                    >
                                        {{
                                            fmt(
                                                corteDetalle.corte[
                                                    "ventas_" + fp.key
                                                ],
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="h-2.5 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="fp.barClass"
                                        :style="{
                                            width: pctVentas(fp.key) + '%',
                                        }"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        Saldo a favor
                                    </span>
                                    <span
                                        class="font-mono text-sm text-slate-600"
                                    >
                                        {{
                                            fmt(
                                                corteDetalle.corte.ventas_saldo_favor,
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="h-2.5 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-cyan-500 transition-all duration-500"
                                        :style="{
                                            width: pctVentas('saldo_favor') + '%',
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ARQUEO -->
                    <div
                        class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-slate-900">
                                Arqueo al cierre
                            </h3>
                            <p class="text-sm text-slate-500">
                                Esperado, contado y diferencia por forma.
                            </p>
                        </div>

                        <div class="overflow-x-auto px-5 py-5">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            Forma
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            Esperado
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            Dif.
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            Contado
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="fp in formasPago" :key="fp.key">
                                        <td class="px-3 py-2 text-slate-700">
                                            {{ fp.label }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-mono text-slate-700"
                                        >
                                            {{
                                                fmt(
                                                    corteDetalle.corte[
                                                        "esperado_" + fp.key
                                                    ],
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-mono font-semibold"
                                            :class="
                                                Number(
                                                    corteDetalle.corte[
                                                        'dif_' + fp.key
                                                    ],
                                                ) < 0
                                                    ? 'text-red-600'
                                                    : 'text-emerald-600'
                                            "
                                        >
                                            {{
                                                fmtDif(
                                                    corteDetalle.corte[
                                                        "dif_" + fp.key
                                                    ],
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-mono text-slate-700"
                                        >
                                            {{
                                                fmt(
                                                    corteDetalle.corte[
                                                        "contado_" + fp.key
                                                    ],
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <template v-if="corteDetalle.corte.desglose">
                            <div class="border-t border-slate-100 px-5 py-5">
                                <div class="mb-3 flex items-center gap-2">
                                    <Coins class="h-4 w-4 text-emerald-600" />
                                    <h4
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Desglose de billetes y monedas
                                    </h4>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="d in desgloseItems(
                                            corteDetalle.corte.desglose,
                                        )"
                                        :key="d.key"
                                        v-show="Number(d.cantidad) > 0"
                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                    >
                                        <span
                                            class="font-semibold text-emerald-700"
                                        >
                                            {{ d.label }}
                                        </span>
                                        <span class="text-slate-500">
                                            × {{ d.cantidad }}
                                        </span>
                                        <span class="font-mono text-slate-700">
                                            = {{ fmt(d.subtotal) }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3"
                                >
                                    <p class="text-sm text-emerald-800">
                                        Total contado:
                                        <span class="font-semibold">
                                            {{
                                                fmt(
                                                    corteDetalle.corte.desglose
                                                        .total_calculado,
                                                )
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- MOVIMIENTOS -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Movimientos extra
                            </h3>
                            <p class="text-sm text-slate-500">
                                Ingresos y egresos manuales del turno.
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-600"
                        >
                            <ClipboardList class="h-4 w-4" />
                            {{ corteDetalle.corte.movimientos?.length ?? 0 }}
                            movimientos
                        </span>
                    </div>

                    <div
                        v-if="corteDetalle.mov_resumen?.length"
                        class="flex flex-wrap gap-2 px-5 pt-5"
                    >
                        <div
                            v-for="mr in corteDetalle.mov_resumen"
                            :key="mr.forma_pago"
                            class="inline-flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                        >
                            <span class="capitalize text-slate-700">
                                {{ mr.forma_pago }}
                            </span>
                            <span class="font-mono text-emerald-600">
                                +{{ fmt(mr.ingresos) }}
                            </span>
                            <span class="font-mono text-red-600">
                                -{{ fmt(mr.egresos) }}
                            </span>
                            <span
                                class="font-mono font-semibold"
                                :class="
                                    Number(mr.neto) >= 0
                                        ? 'text-emerald-600'
                                        : 'text-red-600'
                                "
                            >
                                = {{ fmtDif(mr.neto) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="corteDetalle.corte.movimientos?.length"
                        class="overflow-x-auto px-5 py-5"
                    >
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Fecha/Hora
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Usuario
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Tipo
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Forma
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Monto
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Concepto
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="m in corteDetalle.corte.movimientos"
                                    :key="m.id"
                                >
                                    <td
                                        class="whitespace-nowrap px-3 py-3 text-slate-500"
                                    >
                                        {{ fmtDatetime(m.fecha || m.fecha) }}
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">
                                        {{ m.user?.name || "—" }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                            :class="
                                                m.tipo === 'ingreso'
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-red-50 text-red-700'
                                            "
                                        >
                                            {{ m.tipo }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-3 py-3 capitalize text-slate-700"
                                    >
                                        {{ m.forma_pago }}
                                        <div
                                            v-if="m.cuenta_bancaria || m.terminal_pago"
                                            class="text-[11px] normal-case text-slate-500"
                                        >
                                            {{ (m.cuenta_bancaria ?? m.terminal_pago)?.nombre }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-mono font-semibold"
                                        :class="
                                            m.tipo === 'ingreso'
                                                ? 'text-emerald-600'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{ m.tipo === "ingreso" ? "+" : "-"
                                        }}{{ fmt(m.monto) }}
                                    </td>
                                    <td
                                        class="max-w-[260px] truncate px-3 py-3 text-slate-600"
                                    >
                                        {{ m.concepto || "—" }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-else
                        class="px-6 py-10 text-center text-sm text-slate-500"
                    >
                        Sin movimientos en este turno.
                    </div>
                </div>

                <!-- VENTAS DEL TURNO -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Ventas del turno
                            </h3>
                            <p class="text-sm text-slate-500">
                                Detalle de ventas registradas en este corte.
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-600"
                        >
                            <ShoppingCart class="h-4 w-4" />
                            {{
                                ventasPaginacion?.total ??
                                corteDetalle.corte.num_ventas ??
                                0
                            }}
                            ventas
                        </span>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <!-- FILTRO FORMA DE PAGO -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in FP_OPTS"
                                :key="opt"
                                type="button"
                                @click="
                                    ventasFiltroFP = opt;
                                    cargarVentasCorte(corteDetalle.corte.id, 1);
                                "
                                class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold capitalize transition"
                                :class="
                                    ventasFiltroFP === opt
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                "
                            >
                                {{ opt === "" ? "Todas" : opt }}
                            </button>
                        </div>

                        <!-- SKELETON -->
                        <div v-if="ventasCargando" class="space-y-3">
                            <div
                                v-for="n in 4"
                                :key="n"
                                class="h-10 animate-pulse rounded-xl bg-slate-100"
                            />
                        </div>

                        <!-- TABLA -->
                        <template v-else-if="ventasCorte.length">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Fecha/Hora
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Cajero
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Forma
                                            </th>
                                            <th
                                                class="px-3 py-2 text-right font-medium"
                                            >
                                                Total
                                            </th>
                                            <th
                                                class="px-3 py-2 text-left font-medium"
                                            >
                                                Productos
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="v in ventasCorte"
                                            :key="v.id"
                                        >
                                            <td
                                                class="whitespace-nowrap px-3 py-3 text-slate-500"
                                            >
                                                {{ fmtDatetime(v.created_at) }}
                                            </td>

                                            <td
                                                class="px-3 py-3 text-slate-700"
                                            >
                                                {{ v.user?.name ?? "—" }}
                                            </td>

                                            <td class="px-3 py-3">
                                                <span
                                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                                    :class="
                                                        badgeFormaPagoClass(
                                                            formaPagoResumen(v).clave,
                                                        )
                                                    "
                                                >
                                                    {{ formaPagoResumen(v).label }}
                                                </span>
                                                <div
                                                    v-if="formaPagoResumen(v).detalle"
                                                    class="mt-1 text-[11px] text-slate-500"
                                                >
                                                    {{ formaPagoResumen(v).detalle }}
                                                </div>
                                            </td>

                                            <td
                                                class="px-3 py-3 text-right font-mono font-semibold text-emerald-700"
                                            >
                                                {{ fmt(v.total) }}
                                            </td>

                                            <td class="px-3 py-3">
                                                <div
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <span
                                                        v-for="d in v.detalles"
                                                        :key="d.id"
                                                        class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700"
                                                    >
                                                        {{
                                                            d.producto
                                                                ?.nombre ??
                                                            "Producto"
                                                        }}
                                                        <span
                                                            v-if="d.variante"
                                                            class="text-slate-400"
                                                        >
                                                            ·
                                                            {{ d.variante.sku }}
                                                        </span>
                                                        <span
                                                            class="text-slate-500"
                                                        >
                                                            ×{{ d.cantidad }}
                                                        </span>
                                                        <span
                                                            class="font-mono font-semibold text-emerald-700"
                                                        >
                                                            {{
                                                                fmt(d.subtotal)
                                                            }}
                                                        </span>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- PAGINACIÓN -->
                            <div
                                v-if="
                                    ventasPaginacion &&
                                    ventasPaginacion.last_page > 1
                                "
                                class="mt-4 flex items-center justify-center gap-3"
                            >
                                <button
                                    type="button"
                                    :disabled="
                                        ventasPaginacion.current_page <= 1
                                    "
                                    @click="
                                        cambiarPaginaVentas(
                                            ventasPaginacion.current_page - 1,
                                        )
                                    "
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </button>

                                <span class="text-sm text-slate-600">
                                    {{ ventasPaginacion.current_page }} /
                                    {{ ventasPaginacion.last_page }}
                                </span>

                                <button
                                    type="button"
                                    :disabled="
                                        ventasPaginacion.current_page >=
                                        ventasPaginacion.last_page
                                    "
                                    @click="
                                        cambiarPaginaVentas(
                                            ventasPaginacion.current_page + 1,
                                        )
                                    "
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </button>
                            </div>
                        </template>

                        <div
                            v-else
                            class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500"
                        >
                            Sin ventas en este turno.
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- LOADING -->
        <Transition name="fade">
            <div
                v-if="cargando"
                class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/25 backdrop-blur-[2px]"
            >
                <div
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-xl"
                >
                    <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                    <span class="text-sm font-medium text-slate-700">
                        Cargando reporte...
                    </span>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Coins,
    Eye,
    FileSpreadsheet,
    FileText,
    Inbox,
    Loader2,
    Receipt,
    RotateCcw,
    Rows3,
    ShieldCheck,
    ShoppingCart,
    Users,
    Wallet,
} from "lucide-vue-next";

// ── Tipos de formas de pago para el filtro de ventas ──────────────────────
const FP_OPTS = ["", "efectivo", "tarjeta", "transferencia"];

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
    sucursalNombre: { type: String, default: "Principal" },
    apiBase: { type: String, default: "/api/reportes/caja" },
    cajeros: { type: Array, default: () => [] },
});

// ── Estado ─────────────────────────────────────────────────────────────────
const cargando = ref(false);
const cortesData = ref([]);
const paginacion = ref(null);
const corteDetalle = ref(null);

// ventas del corte seleccionado
const ventasCorte = ref([]);
const ventasPaginacion = ref(null);
const ventasCargando = ref(false);
const ventasFiltroFP = ref("");
const ventasPagina = ref(1);
const exportando = ref(null);

const filtros = reactive({
    fecha_desde: hoy(),
    fecha_hasta: hoy(),
    user_id: "",
    estado: "",
    pagina: 1,
});

// ── Config ─────────────────────────────────────────────────────────────────
const formasPago = [
    {
        key: "efectivo",
        label: "Efectivo",
        barClass: "bg-emerald-500",
    },
    {
        key: "tarjeta",
        label: "Tarjeta",
        barClass: "bg-sky-500",
    },
    {
        key: "transferencia",
        label: "Transferencia",
        barClass: "bg-violet-500",
    },
];

// ── Ciclo de vida ──────────────────────────────────────────────────────────
onMounted(async () => {
    await cargarCortes();
});

// ── Métodos de carga ───────────────────────────────────────────────────────
async function cargarCortes() {
    cargando.value = true;

    try {
        const params = {
            fecha_desde: filtros.fecha_desde || undefined,
            fecha_hasta: filtros.fecha_hasta || undefined,
            user_id: filtros.user_id || undefined,
            estado: filtros.estado || undefined,
            page: filtros.pagina,
            por_pagina: 25,
        };

        const { data } = await axios.get(props.apiBase, { params });

        cortesData.value = data.data ?? [];
        paginacion.value = {
            current_page: data.current_page,
            last_page: data.last_page,
        };
    } catch (e) {
        console.error(e);
    } finally {
        cargando.value = false;
    }
}

async function abrirDetalle(id) {
    cargando.value = true;

    ventasCorte.value = [];
    ventasPaginacion.value = null;
    ventasFiltroFP.value = "";
    ventasPagina.value = 1;

    try {
        const { data } = await axios.get(`${props.apiBase}/${id}`);
        corteDetalle.value = data;
        cargarVentasCorte(id);
    } catch (e) {
        console.error(e);
    } finally {
        cargando.value = false;
    }
}

async function cargarVentasCorte(id, pagina = 1) {
    ventasCargando.value = true;
    ventasPagina.value = pagina;

    try {
        const { data } = await axios.get(`${props.apiBase}/${id}/ventas`, {
            params: {
                forma_pago: ventasFiltroFP.value || undefined,
                page: pagina,
                por_pagina: 30,
            },
        });

        ventasCorte.value = data.data ?? [];
        ventasPaginacion.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
        };
    } catch (e) {
        console.error("cargarVentasCorte", e);
    } finally {
        ventasCargando.value = false;
    }
}

function cerrarDetalle() {
    corteDetalle.value = null;
    ventasCorte.value = [];
    ventasPaginacion.value = null;
    ventasFiltroFP.value = "";
    ventasPagina.value = 1;
}

function cambiarPaginaVentas(p) {
    if (!corteDetalle.value?.corte?.id) return;
    cargarVentasCorte(corteDetalle.value.corte.id, p);
}

function cambiarPagina(p) {
    filtros.pagina = p;
    cargarCortes();
}

function reiniciarYCargar() {
    filtros.pagina = 1;
    cargarCortes();
}

function limpiarFiltros() {
    filtros.fecha_desde = hoy();
    filtros.fecha_hasta = hoy();
    filtros.user_id = "";
    filtros.estado = "";
    filtros.pagina = 1;
    cargarCortes();
}

async function exportar(formato) {
    exportando.value = formato;
    try {
        const resp = await axios.get(`${props.apiBase}/exportar`, {
            params: {
                fecha_desde: filtros.fecha_desde,
                fecha_hasta: filtros.fecha_hasta,
                user_id: filtros.user_id,
                estado: filtros.estado,
                formato,
            },
            responseType: "blob",
        });
        const ext = formato === "excel" ? "xlsx" : "pdf";
        const url = URL.createObjectURL(new Blob([resp.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = `reporte_caja_${new Date().toISOString().slice(0, 10)}.${ext}`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error(e);
        alert("No se pudo generar el archivo.");
    } finally {
        exportando.value = null;
    }
}

// ── Computed ───────────────────────────────────────────────────────────────
const totalesListado = computed(() => {
    const base = {
        ventas_efectivo: 0,
        ventas_tarjeta: 0,
        ventas_transferencia: 0,
        ventas_saldo_favor: 0,
        total_cobrado_caja: 0,
        total_vendido: 0,
        esperado_efectivo: 0,
        dif_efectivo: 0,
        contado_efectivo: 0,
    };

    return cortesData.value.reduce((totales, corte) => {
        const cobradoCaja = Number(corte.total_cobrado_caja ?? corte.total_ventas ?? 0);
        const saldoFavor = Number(corte.ventas_saldo_favor || 0);

        totales.ventas_efectivo += Number(corte.ventas_efectivo || 0);
        totales.ventas_tarjeta += Number(corte.ventas_tarjeta || 0);
        totales.ventas_transferencia += Number(corte.ventas_transferencia || 0);
        totales.ventas_saldo_favor += saldoFavor;
        totales.total_cobrado_caja += cobradoCaja;
        totales.total_vendido += Number(corte.total_vendido ?? (cobradoCaja + saldoFavor) ?? 0);
        totales.esperado_efectivo += Number(corte.esperado_efectivo || 0);
        totales.dif_efectivo += Number(corte.dif_efectivo || 0);
        totales.contado_efectivo += Number(corte.contado_efectivo || 0);

        return totales;
    }, { ...base });
});

const metricasDetalle = computed(() => {
    if (!corteDetalle.value) return [];

    const c = corteDetalle.value.corte;

    return [
        {
            label: "Total vendido",
            valor: fmt(c.total_vendido ?? totalVendidoCorte(c)),
            color: "",
        },
        {
            label: "Cobrado caja",
            valor: fmt(
                c.total_cobrado_caja ?? totalCobradoCajaCorte(c),
            ),
            color: "",
        },
        {
            label: "# Ventas",
            valor: c.num_ventas,
            color: "",
        },
        {
            label: "Fondo inicial",
            valor: fmt(c.fondo_inicial_efectivo),
            color: "",
        },
        {
            label: "Esperado efectivo",
            valor: fmt(c.esperado_efectivo),
            color: "",
        },
        {
            label: "Contado ef.",
            valor: fmt(c.contado_efectivo),
            color: "",
        },
        {
            label: "Dif. efectivo",
            valor: fmtDif(c.dif_efectivo),
            color: c.dif_efectivo < 0 ? "neg" : c.dif_efectivo > 0 ? "pos" : "",
        },
        {
            label: "Movs. ef.",
            valor: fmtDif(c.movs_efectivo),
            color: c.movs_efectivo < 0 ? "rojo" : "verde",
        },
    ];
});

// ── Helpers visuales ───────────────────────────────────────────────────────
function pctVentas(key) {
    if (!corteDetalle.value) return 0;

    const c = corteDetalle.value.corte;
    const total = c.total_vendido ?? totalVendidoCorte(c);

    if (!total) return 0;

    return Math.round(((+c["ventas_" + key] || 0) / total) * 100);
}

function totalCobradoCajaCorte(c) {
    return (+c.ventas_efectivo || 0) + (+c.ventas_tarjeta || 0) + (+c.ventas_transferencia || 0);
}

function totalVendidoCorte(c) {
    return totalCobradoCajaCorte(c) + (+c.ventas_saldo_favor || 0);
}

function desgloseItems(d) {
    return [
        {
            key: "b1000",
            label: "$1,000",
            cantidad: d.billetes_1000,
            subtotal: d.billetes_1000 * 1000,
        },
        {
            key: "b500",
            label: "$500",
            cantidad: d.billetes_500,
            subtotal: d.billetes_500 * 500,
        },
        {
            key: "b200",
            label: "$200",
            cantidad: d.billetes_200,
            subtotal: d.billetes_200 * 200,
        },
        {
            key: "b100",
            label: "$100",
            cantidad: d.billetes_100,
            subtotal: d.billetes_100 * 100,
        },
        {
            key: "b50",
            label: "$50",
            cantidad: d.billetes_50,
            subtotal: d.billetes_50 * 50,
        },
        {
            key: "b20",
            label: "$20",
            cantidad: d.billetes_20,
            subtotal: d.billetes_20 * 20,
        },
        {
            key: "m20",
            label: "$20",
            cantidad: d.monedas_20,
            subtotal: d.monedas_20 * 20,
        },
        {
            key: "m10",
            label: "$10",
            cantidad: d.monedas_10,
            subtotal: d.monedas_10 * 10,
        },
        {
            key: "m5",
            label: "$5",
            cantidad: d.monedas_5,
            subtotal: d.monedas_5 * 5,
        },
        {
            key: "m2",
            label: "$2",
            cantidad: d.monedas_2,
            subtotal: d.monedas_2 * 2,
        },
        {
            key: "m1",
            label: "$1",
            cantidad: d.monedas_1,
            subtotal: d.monedas_1 * 1,
        },
        {
            key: "m050",
            label: "$0.50",
            cantidad: d.monedas_050,
            subtotal: d.monedas_050 * 0.5,
        },
    ];
}

function badgeEstadoClass(estado) {
    if (estado === "abierto") return "bg-emerald-50 text-emerald-700";
    if (estado === "cerrado") return "bg-slate-100 text-slate-700";
    if (estado === "anulado") return "bg-red-50 text-red-700";
    return "bg-slate-100 text-slate-600";
}

function badgeFormaPagoClass(forma) {
    if (forma === "efectivo") return "bg-emerald-50 text-emerald-700";
    if (forma === "tarjeta") return "bg-sky-50 text-sky-700";
    if (forma === "transferencia") return "bg-violet-50 text-violet-700";
    if (forma === "mixto") return "bg-amber-50 text-amber-700";
    return "bg-slate-100 text-slate-700";
}

function diferenciaClass(valor) {
    const n = Number(valor || 0);

    if (n < 0) return "bg-red-50 text-red-700";
    if (n > 0) return "bg-emerald-50 text-emerald-700";
    return "bg-slate-100 text-slate-600";
}

const ETIQUETAS_FORMA_PAGO = {
    efectivo: "Efectivo",
    tarjeta: "Tarjeta",
    transferencia: "Transferencia",
    saldo_favor: "Saldo a favor",
};

function formaPagoResumen(venta) {
    const pagos = Array.isArray(venta?.pagos) ? venta.pagos : [];
    const metodos = pagos.filter((p) => p.forma_pago !== "saldo_favor");
    const tieneSaldoFavor = pagos.some((p) => p.forma_pago === "saldo_favor");

    if (metodos.length === 0) {
        return {
            clave: tieneSaldoFavor ? "saldo_favor" : "—",
            label: tieneSaldoFavor ? "Saldo a favor" : "—",
            detalle: "",
        };
    }

    if (metodos.length > 1) {
        return {
            clave: "mixto",
            label: "Mixto",
            detalle: metodos
                .map((p) => `${ETIQUETAS_FORMA_PAGO[p.forma_pago] ?? p.forma_pago}: ${fmt(p.monto)}`)
                .join(" · "),
        };
    }

    const unico = metodos[0];
    const cuentaTerminal = unico.cuenta_bancaria?.nombre ?? unico.terminal_pago?.nombre ?? "";

    return {
        clave: unico.forma_pago,
        label: ETIQUETAS_FORMA_PAGO[unico.forma_pago] ?? unico.forma_pago,
        detalle: cuentaTerminal,
    };
}

function metricaColorClass(color) {
    if (color === "neg" || color === "rojo") return "text-red-600";
    if (color === "pos" || color === "verde") return "text-emerald-600";
    return "text-slate-900";
}

// ── Formatters ─────────────────────────────────────────────────────────────
function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(+v || 0);
}

function fmtDif(v) {
    const n = +v || 0;
    return (
        (n >= 0 ? "+" : "") +
        new Intl.NumberFormat("es-MX", {
            style: "currency",
            currency: "MXN",
        }).format(n)
    );
}

function fmtFecha(str) {
    return new Date(str + "T12:00:00").toLocaleDateString("es-MX", {
        weekday: "short",
        day: "2-digit",
        month: "short",
    });
}

function fechaAperturaCorte(corte) {
    if (
        corte?.fecha_apertura &&
        corte?.fecha_cierre &&
        corte?.created_at &&
        new Date(corte.fecha_apertura).getTime() ===
            new Date(corte.fecha_cierre).getTime() &&
        new Date(corte.created_at).getTime() !==
            new Date(corte.fecha_cierre).getTime()
    ) {
        return corte.created_at;
    }

    return corte?.fecha_apertura;
}

function fmtDate(str) {
    if (!str) return "Sin fecha";

    return new Date(str).toLocaleDateString("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function fmtTime(str) {
    if (!str) return "";

    return new Date(str).toLocaleTimeString("es-MX", {
        hour: "2-digit",
        minute: "2-digit",
    });
}

function fmtDatetime(str) {
    if (!str) return "Sin fecha";
    return new Date(str).toLocaleString("es-MX", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
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

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
