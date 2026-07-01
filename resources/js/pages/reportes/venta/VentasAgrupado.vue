<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- TOPBAR -->
        <header
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"
                    >
                        <BarChart3 class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-lg font-semibold tracking-tight text-slate-950"
                        >
                            Ventas agrupadas
                        </h1>
                        <p class="truncate text-xs text-slate-500">
                            {{ sucursalNombre }} · Análisis por cliente,
                            producto y proveedor
                        </p>
                    </div>
                </div>

                <div
                    v-if="ejecutado"
                    class="hidden items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 sm:inline-flex"
                >
                    <Rows3 class="h-3.5 w-3.5" />
                    {{ pag.total }} registros
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl space-y-4 px-4 py-5 sm:px-6 lg:px-8">
            <!-- TABS -->
            <section
                class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm"
            >
                <div class="flex gap-1 overflow-x-auto">
                    <button
                        v-for="t in TABS"
                        :key="t.key"
                        type="button"
                        @click="cambiarTab(t.key)"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition"
                        :class="
                            tabActiva === t.key
                                ? 'bg-slate-900 text-white shadow-sm'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <component :is="t.icon" class="h-4 w-4" />
                        {{ t.label }}
                    </button>
                </div>
            </section>

            <!-- FILTROS -->
            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Filtros
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ descripcionFiltro }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="limpiarFiltrosTab"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        Limpiar
                    </button>
                </div>

                <div
                    class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(240px,1.4fr)_repeat(2,minmax(150px,0.8fr))_repeat(2,minmax(150px,0.9fr))_150px]"
                >
                    <!-- Entidad / buscador -->
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            {{ tabActual.labelEntidad }}
                        </label>
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="estado.q"
                                :placeholder="
                                    tabActual.usaAutocomplete
                                        ? `Buscar ${tabActual.labelEntidad.toLowerCase()}...`
                                        : 'Buscar por clave, nombre o SKU...'
                                "
                                type="text"
                                autocomplete="off"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                @input="
                                    tabActual.usaAutocomplete
                                        ? buscarEntidad()
                                        : null
                                "
                                @focus="
                                    tabActual.usaAutocomplete
                                        ? (mostrarSugerencias = true)
                                        : null
                                "
                                @blur="
                                    tabActual.usaAutocomplete
                                        ? ocultarSugerencias()
                                        : null
                                "
                            />

                            <!-- Dropdown autocomplete -->
                            <div
                                v-if="
                                    tabActual.usaAutocomplete &&
                                    mostrarSugerencias
                                "
                                class="absolute left-0 right-0 top-[calc(100%+6px)] z-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                            >
                                <div
                                    v-if="buscandoEntidad"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-slate-500"
                                >
                                    <Loader2
                                        class="h-4 w-4 animate-spin text-emerald-600"
                                    />
                                    Buscando...
                                </div>
                                <ul
                                    v-else-if="sugerencias.length"
                                    class="max-h-64 overflow-y-auto py-2"
                                >
                                    <li
                                        v-for="s in sugerencias"
                                        :key="s.id"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700"
                                        @mousedown.prevent="
                                            seleccionarEntidad(s)
                                        "
                                    >
                                        {{ s.nombre }}
                                    </li>
                                </ul>
                                <div
                                    v-else-if="estado.q.length >= 1"
                                    class="px-4 py-3 text-sm text-slate-500"
                                >
                                    Sin resultados
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="estado.entidadSeleccionada"
                            class="mt-2 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                        >
                            {{ estado.entidadSeleccionada.nombre }}
                            <button
                                type="button"
                                class="rounded-full text-emerald-700 hover:text-emerald-900"
                                @click="limpiarEntidad"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <BaseInput
                        v-model="estado.fecha_desde"
                        label="Desde"
                        type="date"
                    />
                    <BaseInput
                        v-model="estado.fecha_hasta"
                        label="Hasta"
                        type="date"
                    />

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-500"
                            >Forma de pago</label
                        >
                        <select
                            v-model="estado.forma_pago"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        >
                            <option value="">Todas</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-500"
                            >Estado</label
                        >
                        <select
                            v-model="estado.estado"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        >
                            <option value="">Todos</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button
                            type="button"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!puedeEjecutar || cargando"
                            @click="ejecutar"
                        >
                            <Loader2
                                v-if="cargando"
                                class="h-4 w-4 animate-spin"
                            />
                            <Search v-else class="h-4 w-4" />
                            Consultar
                        </button>
                    </div>
                </div>

                <p
                    v-if="intentoBuscar && !puedeEjecutar"
                    class="mt-3 text-xs font-medium text-red-600"
                >
                    Selecciona un rango de fechas para consultar.
                </p>
            </section>

            <!-- ESTADO INICIAL -->
            <section
                v-if="!ejecutado && !cargando"
                class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm"
            >
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                >
                    <Filter class="h-6 w-6" />
                </div>
                <h3 class="text-base font-semibold text-slate-900">
                    Configura los filtros y consulta
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Selecciona fechas y presiona
                    <span class="font-medium text-emerald-700">Consultar</span>.
                </p>
            </section>

            <!-- SKELETON -->
            <section v-else-if="cargando" class="space-y-3">
                <div
                    v-for="n in 6"
                    :key="n"
                    class="h-12 animate-pulse rounded-xl bg-slate-100"
                />
            </section>

            <!-- RESULTADOS -->
            <section
                v-else-if="filas.length"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            {{ tabActual.label }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            Resultados agrupados por
                            {{ tabActual.labelEntidad.toLowerCase() }}.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"
                        >
                            {{ pag.total }} registros
                        </span>
                        <span
                            class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                        >
                            Total: {{ fmt(totalPrincipal) }}
                        </span>

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
                        <span
                            v-if="tabActual.tieneMargen"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="
                                suma('margen') >= 0
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-red-50 text-red-700'
                            "
                        >
                            Margen: {{ fmt(suma("margen")) }}
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead
                            class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                        >
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>

                                <!-- Artículos -->
                                <template
                                    v-if="tabActual.tipoReporte === 'articulos'"
                                >
                                    <th class="px-4 py-3 text-left">Clave</th>
                                    <th class="px-4 py-3 text-left">
                                        Artículo
                                    </th>
                                    <th class="px-4 py-3 text-left">
                                        Categoría
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Cantidad vendida
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Importe
                                    </th>
                                </template>

                                <!-- Detalle artículos -->
                                <template
                                    v-else-if="
                                        tabActual.tipoReporte ===
                                        'detalle_articulos'
                                    "
                                >
                                    <th class="px-4 py-3 text-left">Clave</th>
                                    <th class="px-4 py-3 text-left">
                                        Artículo
                                    </th>
                                    <th class="px-4 py-3 text-left">
                                        Categoría
                                    </th>
                                    <th class="px-4 py-3 text-left">
                                        Variante / SKU
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Cantidad
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Precio venta
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Importe
                                    </th>
                                </template>

                                <!-- Agrupado -->
                                <template v-else>
                                    <th class="px-4 py-3 text-left">
                                        {{ tabActual.colNombre }}
                                    </th>
                                    <th class="px-4 py-3 text-right">Ventas</th>
                                    <th
                                        v-if="tabActual.tieneUnidades"
                                        class="px-4 py-3 text-right"
                                    >
                                        Unidades
                                    </th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th
                                        v-if="tabActual.tieneTicket"
                                        class="px-4 py-3 text-right"
                                    >
                                        Ticket prom.
                                    </th>
                                    <th
                                        v-if="tabActual.tieneMargen"
                                        class="px-4 py-3 text-right"
                                    >
                                        Margen
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Efectivo
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Tarjeta
                                    </th>
                                    <th class="px-4 py-3 text-right">Trans.</th>
                                    <th class="px-4 py-3 text-right">
                                        Crédito
                                    </th>
                                </template>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(row, i) in filas"
                                :key="i"
                                class="transition hover:bg-slate-50"
                            >
                                <td
                                    class="px-4 py-3 text-xs font-semibold text-slate-400"
                                >
                                    {{ (pag.current_page - 1) * 30 + i + 1 }}
                                </td>

                                <!-- Artículos -->
                                <template
                                    v-if="tabActual.tipoReporte === 'articulos'"
                                >
                                    <td
                                        class="px-4 py-3 font-mono text-xs font-semibold text-emerald-700"
                                    >
                                        {{ row.clave || "—" }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-semibold text-slate-800"
                                    >
                                        {{ row.articulo || "Sin nombre" }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ row.categoria || "Sin categoría" }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-slate-700"
                                    >
                                        {{ row.cantidad_vendida }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-bold text-emerald-700"
                                    >
                                        {{ fmt(row.importe_total) }}
                                    </td>
                                </template>

                                <!-- Detalle artículos -->
                                <template
                                    v-else-if="
                                        tabActual.tipoReporte ===
                                        'detalle_articulos'
                                    "
                                >
                                    <td
                                        class="px-4 py-3 font-mono text-xs font-semibold text-emerald-700"
                                    >
                                        {{ row.clave || "—" }}
                                    </td>
                                    <td
                                        class="px-4 py-3 font-semibold text-slate-800"
                                    >
                                        {{ row.articulo || "Sin nombre" }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ row.categoria || "Sin categoría" }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
                                        >
                                            <ListTree class="h-3.5 w-3.5" />
                                            {{
                                                row.nombre_variante ||
                                                row.sku_variante ||
                                                "Sin variante"
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold text-slate-700"
                                    >
                                        {{ row.cantidad_vendida }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-600"
                                    >
                                        {{ fmt(row.precio_venta) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-bold text-emerald-700"
                                    >
                                        {{ fmt(row.importe_subtotal) }}
                                    </td>
                                </template>

                                <!-- Agrupado -->
                                <template v-else>
                                    <td
                                        class="px-4 py-3 font-semibold text-slate-800"
                                    >
                                        {{
                                            row[tabActual.campoNombre] ||
                                            "Sin nombre"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                    >
                                        {{ row.num_ventas }}
                                    </td>
                                    <td
                                        v-if="tabActual.tieneUnidades"
                                        class="px-4 py-3 text-right font-mono text-slate-700"
                                    >
                                        {{ row.unidades }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-bold text-emerald-700"
                                    >
                                        {{ fmt(row.total) }}
                                    </td>
                                    <td
                                        v-if="tabActual.tieneTicket"
                                        class="px-4 py-3 text-right font-mono text-slate-500"
                                    >
                                        {{ fmt(row.ticket_prom) }}
                                    </td>
                                    <td
                                        v-if="tabActual.tieneMargen"
                                        class="px-4 py-3 text-right font-mono font-semibold"
                                        :class="
                                            Number(row.margen) >= 0
                                                ? 'text-emerald-600'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{ fmt(row.margen) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-600"
                                    >
                                        {{
                                            Number(row.efectivo) > 0
                                                ? fmt(row.efectivo)
                                                : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-600"
                                    >
                                        {{
                                            Number(row.tarjeta) > 0
                                                ? fmt(row.tarjeta)
                                                : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-600"
                                    >
                                        {{
                                            Number(row.transferencia) > 0
                                                ? fmt(row.transferencia)
                                                : "—"
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-600"
                                    >
                                        {{
                                            Number(row.credito) > 0
                                                ? fmt(row.credito)
                                                : "—"
                                        }}
                                    </td>
                                </template>
                            </tr>
                        </tbody>

                        <!-- TFOOT — solo para reportes agrupados -->
                        <tfoot
                            v-if="tabActual.tipoReporte === 'agrupado'"
                            class="border-t border-slate-200 bg-slate-50 font-semibold text-slate-900"
                        >
                            <tr>
                                <td colspan="2" class="px-4 py-3">
                                    Subtotal página
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ suma("num_ventas") }}
                                </td>
                                <td
                                    v-if="tabActual.tieneUnidades"
                                    class="px-4 py-3 text-right font-mono"
                                >
                                    {{ suma("unidades") }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono text-emerald-700"
                                >
                                    {{ fmt(suma("total")) }}
                                </td>
                                <td
                                    v-if="tabActual.tieneTicket"
                                    class="px-4 py-3"
                                ></td>
                                <td
                                    v-if="tabActual.tieneMargen"
                                    class="px-4 py-3 text-right font-mono"
                                    :class="
                                        suma('margen') >= 0
                                            ? 'text-emerald-600'
                                            : 'text-red-600'
                                    "
                                >
                                    {{ fmt(suma("margen")) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ fmt(suma("efectivo")) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ fmt(suma("tarjeta")) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ fmt(suma("transferencia")) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ fmt(suma("credito")) }}
                                </td>
                            </tr>
                        </tfoot>

                        <!-- TFOOT artículos -->
                        <tfoot
                            v-else-if="tabActual.tipoReporte === 'articulos'"
                            class="border-t border-slate-200 bg-slate-50 font-semibold text-slate-900"
                        >
                            <tr>
                                <td colspan="4" class="px-4 py-3">
                                    Subtotal página
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ suma("cantidad_vendida") }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono text-emerald-700"
                                >
                                    {{ fmt(suma("importe_total")) }}
                                </td>
                            </tr>
                        </tfoot>

                        <!-- TFOOT detalle artículos -->
                        <tfoot
                            v-else-if="
                                tabActual.tipoReporte === 'detalle_articulos'
                            "
                            class="border-t border-slate-200 bg-slate-50 font-semibold text-slate-900"
                        >
                            <tr>
                                <td colspan="5" class="px-4 py-3">
                                    Subtotal página
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ suma("cantidad_vendida") }}
                                </td>
                                <td class="px-4 py-3"></td>
                                <td
                                    class="px-4 py-3 text-right font-mono text-emerald-700"
                                >
                                    {{ fmt(suma("importe_subtotal")) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- PAGINACIÓN -->
                <div
                    class="flex items-center justify-center gap-3 border-t border-slate-200 px-5 py-4"
                >
                    <button
                        type="button"
                        :disabled="pag.current_page <= 1"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="cambiarPag(pag.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm text-slate-600">
                        Pág. {{ pag.current_page }} / {{ pag.last_page }} ·
                        {{ pag.total }} registros
                    </span>
                    <button
                        type="button"
                        :disabled="pag.current_page >= pag.last_page"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="cambiarPag(pag.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </section>

            <!-- VACÍO -->
            <section
                v-else-if="ejecutado && !cargando"
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
                <p class="mt-1 text-sm text-slate-500">
                    Cambia el rango de fechas o limpia la entidad seleccionada.
                </p>
            </section>
        </main>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";

import {
    BarChart3,
    ChevronLeft,
    ChevronRight,
    FileSpreadsheet,
    FileText,
    Filter,
    Inbox,
    ListTree,
    Loader2,
    RotateCcw,
    Rows3,
    Search,
    X,
    Barcode,
    Boxes,
    Factory,
    Grid2x2,
    PackageSearch,
    Tags,
    Users,
} from "lucide-vue-next";

const TABS = [
    {
        key: "clientes",
        label: "Clientes",
        icon: Users,
        endpoint: "clientes",
        labelEntidad: "Cliente",
        colNombre: "Cliente",
        campoNombre: "cliente",
        tieneUnidades: false,
        tieneMargen: false,
        tieneTicket: true,
        tipoReporte: "agrupado",
        usaAutocomplete: true,
    },
    {
        key: "categorias",
        label: "Categorías",
        icon: Grid2x2,
        endpoint: "categorias",
        labelEntidad: "Categoría",
        colNombre: "Categoría",
        campoNombre: "categoria",
        tieneUnidades: true,
        tieneMargen: true,
        tieneTicket: false,
        tipoReporte: "agrupado",
        usaAutocomplete: true,
    },
    {
        key: "marcas",
        label: "Marcas",
        icon: Tags,
        endpoint: "marcas",
        labelEntidad: "Marca",
        colNombre: "Marca",
        campoNombre: "marca",
        tieneUnidades: true,
        tieneMargen: true,
        tieneTicket: false,
        tipoReporte: "agrupado",
        usaAutocomplete: true,
    },
    {
        key: "modelos",
        label: "Modelos",
        icon: PackageSearch,
        endpoint: "modelos",
        labelEntidad: "Modelo",
        colNombre: "Modelo",
        campoNombre: "modelo",
        tieneUnidades: true,
        tieneMargen: true,
        tieneTicket: false,
        tipoReporte: "agrupado",
        usaAutocomplete: true,
    },
    {
        key: "proveedores",
        label: "Proveedores",
        icon: Factory,
        endpoint: "proveedores",
        labelEntidad: "Proveedor",
        colNombre: "Proveedor",
        campoNombre: "proveedor",
        tieneUnidades: true,
        tieneMargen: true,
        tieneTicket: false,
        tipoReporte: "agrupado",
        usaAutocomplete: true,
    },
    {
        key: "articulos",
        label: "Artículos",
        icon: Boxes,
        endpoint: "articulos",
        labelEntidad: "Artículo",
        colNombre: "Artículo",
        campoNombre: "articulo",
        tieneUnidades: false,
        tieneMargen: false,
        tieneTicket: false,
        tipoReporte: "articulos",
        usaAutocomplete: false,
    },
    {
        key: "articulos_detalle",
        label: "Detalle artículos",
        icon: Barcode,
        endpoint: "articulos/detalle",
        labelEntidad: "Producto",
        colNombre: "Producto / Variante",
        campoNombre: "articulo",
        tieneUnidades: false,
        tieneMargen: false,
        tieneTicket: false,
        tipoReporte: "detalle_articulos",
        usaAutocomplete: false,
    },
];

// ── Props ─────────────────────────────────────────────────────────────────
const props = defineProps({
    sucursalNombre: { type: String, default: "Principal" },
    apiBase: { type: String, default: "/api/reportes/ventas-agrupado" },
});

// ── Estado ────────────────────────────────────────────────────────────────
const tabActiva = ref("clientes");
const cargando = ref(false);
const exportando = ref(null);
const filas = ref([]);
const pag = ref({ current_page: 1, last_page: 1, total: 0 });
const ejecutado = ref(false);
const intentoBuscar = ref(false);
const sugerencias = ref([]);
const mostrarSugerencias = ref(false);
const buscandoEntidad = ref(false);
let timerBusqueda = null;

const crearEstado = () => ({
    fecha_desde: fechaLocal(),
    fecha_hasta: fechaLocal(),
    forma_pago: "",
    estado: "",
    q: "",
    entidadSeleccionada: null,
    pagina: 1,
});

const estadosPorTab = reactive(
    Object.fromEntries(TABS.map((t) => [t.key, crearEstado()])),
);

// ── Computed ──────────────────────────────────────────────────────────────
const tabActual = computed(() => TABS.find((t) => t.key === tabActiva.value));
const estado = computed(() => estadosPorTab[tabActiva.value]);
const puedeEjecutar = computed(
    () => !!estado.value.fecha_desde && !!estado.value.fecha_hasta,
);

const totalPrincipal = computed(() => {
    if (tabActual.value.tipoReporte === "articulos")
        return suma("importe_total");
    if (tabActual.value.tipoReporte === "detalle_articulos")
        return suma("importe_subtotal");
    return suma("total");
});

const descripcionFiltro = computed(() => {
    if (tabActual.value.tipoReporte === "articulos")
        return "Resumen de productos vendidos por artículo.";
    if (tabActual.value.tipoReporte === "detalle_articulos")
        return "Detalle por producto, variante y precio de venta.";
    return `Agrupando por ${tabActual.value.labelEntidad.toLowerCase()}.`;
});

// ── Helpers ───────────────────────────────────────────────────────────────
// ✅ Fix timezone: usa fecha local en vez de UTC
function fechaLocal() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
}

const mxn = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});
function fmt(v) {
    return mxn.format(+v || 0);
}
function suma(col) {
    return filas.value.reduce((a, r) => a + (+r[col] || 0), 0);
}

// ── Métodos ───────────────────────────────────────────────────────────────
function cambiarTab(key) {
    tabActiva.value = key;
    filas.value = [];
    pag.value = { current_page: 1, last_page: 1, total: 0 };
    ejecutado.value = false;
    intentoBuscar.value = false;
    sugerencias.value = [];
    mostrarSugerencias.value = false;
}

async function buscarEntidad() {
    clearTimeout(timerBusqueda);
    sugerencias.value = [];

    if (estado.value.entidadSeleccionada) {
        if (estado.value.q !== estado.value.entidadSeleccionada.nombre) {
            estado.value.entidadSeleccionada = null;
        }
    }

    if (estado.value.q.length < 1) return;

    timerBusqueda = setTimeout(async () => {
        buscandoEntidad.value = true;
        try {
            const { data } = await axios.get(
                `${props.apiBase}/buscar/${tabActual.value.endpoint}`,
                { params: { q: estado.value.q } },
            );
            sugerencias.value = data;
        } catch (e) {
            console.error("buscarEntidad", e);
        } finally {
            buscandoEntidad.value = false;
        }
    }, 300);
}

function seleccionarEntidad(s) {
    estado.value.entidadSeleccionada = s;
    estado.value.q = s.nombre;
    sugerencias.value = [];
    mostrarSugerencias.value = false;
}

function limpiarEntidad() {
    estado.value.entidadSeleccionada = null;
    estado.value.q = "";
    sugerencias.value = [];
}

function ocultarSugerencias() {
    setTimeout(() => {
        mostrarSugerencias.value = false;
    }, 150);
}

async function ejecutar() {
    intentoBuscar.value = true;
    if (!puedeEjecutar.value) return;
    estado.value.pagina = 1;
    await cargar();
}

async function cargar() {
    cargando.value = true;
    ejecutado.value = true;
    try {
        const { data } = await axios.get(
            `${props.apiBase}/${tabActual.value.endpoint}`,
            { params: buildParams() },
        );
        filas.value = data.data ?? [];
        pag.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
        };
    } catch (e) {
        console.error("cargar", e);
    } finally {
        cargando.value = false;
    }
}

function cambiarPag(p) {
    estado.value.pagina = p;
    cargar();
}

async function exportar(formato) {
    exportando.value = formato;
    const e = estado.value;
    try {
        const resp = await axios.get(`${props.apiBase}/exportar`, {
            params: {
                tab: tabActiva.value,
                fecha_desde: e.fecha_desde,
                fecha_hasta: e.fecha_hasta,
                forma_pago: e.forma_pago || undefined,
                estado: e.estado || undefined,
                entidad_id: tabActual.value.usaAutocomplete ? (e.entidadSeleccionada?.id || undefined) : undefined,
                producto: !tabActual.value.usaAutocomplete ? (e.q || undefined) : undefined,
                formato,
            },
            responseType: 'blob',
        });
        const ext  = formato === 'excel' ? 'xlsx' : 'pdf';
        const mime = formato === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/pdf';
        const url = URL.createObjectURL(new Blob([resp.data], { type: mime }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `ventas_${tabActiva.value}_${e.fecha_desde}_${e.fecha_hasta}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error('exportar agrupado', err);
    } finally {
        exportando.value = null;
    }
}

function limpiarFiltrosTab() {
    estadosPorTab[tabActiva.value] = crearEstado();
    filas.value = [];
    pag.value = { current_page: 1, last_page: 1, total: 0 };
    ejecutado.value = false;
    intentoBuscar.value = false;
    sugerencias.value = [];
    mostrarSugerencias.value = false;
}

function buildParams() {
    const e = estado.value;
    return {
        fecha_desde: e.fecha_desde,
        fecha_hasta: e.fecha_hasta,
        forma_pago: e.forma_pago || undefined,
        estado: e.estado || undefined,
        page: e.pagina,
        por_pagina: 30,
        ...(tabActual.value.usaAutocomplete && {
            entidad_id: e.entidadSeleccionada?.id || undefined,
        }),
        ...(["articulos", "articulos_detalle"].includes(tabActiva.value) && {
            producto: e.q || undefined,
        }),
    };
}
</script>
