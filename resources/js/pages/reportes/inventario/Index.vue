<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        Reporte de productos y existencias
                    </h1>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Costos y precios del catálogo actual. En productos con
                        variantes se muestran promedios ponderados por
                        existencias.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <Loader2
                        v-if="cargando"
                        class="h-4 w-4 animate-spin text-emerald-600"
                    />
                    {{ agrupacionActual }}
                    <button
                        type="button"
                        :disabled="exportando"
                        @click="exportar('excel')"
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
                        :disabled="exportando"
                        @click="exportar('pdf')"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100 focus:outline-none focus:ring-4 focus:ring-red-100 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="exportando === 'pdf'"
                            class="h-4 w-4 animate-spin"
                        />
                        <FileText v-else class="h-4 w-4" />
                        PDF
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] space-y-4 px-4 py-4 sm:px-6">
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <ResumenDato
                    titulo="Invertido"
                    :valor="fmt(resumen.invertido)"
                    destacado
                />
                <ResumenDato
                    titulo="Existencias"
                    :valor="num(resumen.unidades)"
                />
                <ResumenDato
                    titulo="Valor venta"
                    :valor="fmt(resumen.valor_venta)"
                />
                <ResumenDato
                    titulo="Sin costo (todo el inventario)"
                    :valor="num(resumen.sin_costo)"
                    alerta
                />
            </section>

            <section class="border border-slate-200 bg-white">
                <div class="grid gap-3 p-3 md:grid-cols-2 xl:grid-cols-4">
                    <BaseSearchSelect
                        v-model="f.agrupar"
                        label="Agrupar por"
                        :items="agrupaciones"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />

                    <BaseInput
                        v-model="f.q"
                        label="Buscar"
                        placeholder="Producto, código, SKU, IMEI o serie"
                        @input="debounce"
                    />

                    <BaseSearchSelect
                        v-model="f.categoria_id"
                        label="Categoría"
                        placeholder="Todas las categorías"
                        :items="[
                            { id: null, nombre: 'Todas las categorías' },
                            ...categorias,
                        ]"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />

                    <BaseSearchSelect
                        v-model="f.filtro"
                        label="Filtro"
                        :items="filtros"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />
                </div>
                <div
                    class="flex flex-wrap items-end gap-3 border-t border-slate-100 p-3"
                >
                    <BaseSearchSelect
                        v-model="f.series"
                        label="Tipo de producto"
                        :items="filtrosSeries"
                        label-key="nombre"
                        value-key="id"
                        @change="buscar"
                    />
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        @click="limpiarFiltros"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </section>

            <div
                v-if="resumen.sin_costo > 0"
                class="flex items-center gap-2 border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
            >
                <TriangleAlert class="h-4 w-4 shrink-0" />
                <span
                    >{{ resumen.sin_costo }} articulos de todo el inventario
                    (incluye agotados) no tienen costo capturado; el total
                    invertido puede estar incompleto.</span
                >
            </div>

            <section class="overflow-hidden border border-slate-200 bg-white">
                <div
                    class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2"
                >
                    <div>
                        <h2 class="text-sm font-semibold">{{ tituloTabla }}</h2>
                        <p class="text-xs text-slate-500">
                            Sucursal activa. Pulsa una columna para ordenar.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <details
                            v-if="f.agrupar === 'producto'"
                            ref="selectorColumnas"
                            class="relative"
                        >
                            <summary
                                class="cursor-pointer list-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Columnas ({{ columnasVisibles.length }})
                            </summary>
                            <div
                                class="absolute right-0 z-20 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-3 shadow-xl"
                            >
                                <div class="mb-3 flex flex-wrap gap-1">
                                    <button
                                        v-for="preset in presetsColumnas"
                                        :key="preset.id"
                                        type="button"
                                        class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50"
                                        @click="aplicarPreset(preset.columnas)"
                                    >
                                        {{ preset.nombre }}
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label
                                        v-for="columna in columnasDisponibles"
                                        :key="columna.id"
                                        class="flex items-center gap-2 text-xs text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300"
                                            :checked="visible(columna.id)"
                                            :disabled="
                                                columna.id === 'producto'
                                            "
                                            @change="
                                                alternarColumna(
                                                    columna.id,
                                                    $event.target.checked,
                                                )
                                            "
                                        />
                                        {{ columna.nombre }}
                                    </label>
                                </div>
                                <p class="mt-3 text-[11px] text-slate-400">
                                    La selección también se usa en PDF y Excel.
                                </p>
                            </div>
                        </details>
                        <label
                            class="flex items-center gap-1.5 text-xs text-slate-500"
                        >
                            Por pagina
                            <select
                                v-model.number="f.por_pagina"
                                class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-medium text-slate-700"
                                @change="buscar"
                            >
                                <option
                                    v-for="cantidad in opcionesPorPagina"
                                    :key="cantidad"
                                    :value="cantidad"
                                >
                                    {{ cantidad }}
                                </option>
                            </select>
                        </label>
                        <span class="text-xs text-slate-400"
                            >Pagina {{ pag.current_page }} de
                            {{ pag.last_page }}</span
                        >
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white text-slate-500">
                            <tr v-if="f.agrupar === 'producto'">
                                <th
                                    v-if="visible('clave')"
                                    class="px-3 py-2 text-left font-medium"
                                >
                                    Clave
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-medium"
                                    :aria-sort="
                                        f.orden === 'producto'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('producto')"
                                    >
                                        Producto
                                        {{ indicadorOrden("producto") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('categoria')"
                                    class="px-3 py-2 text-left font-medium"
                                >
                                    Categoria
                                </th>
                                <th
                                    v-if="visible('proveedor')"
                                    class="px-3 py-2 text-left font-medium"
                                >
                                    Proveedor
                                </th>
                                <th
                                    v-if="visible('stock')"
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.orden === 'stock'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('stock')"
                                    >
                                        Existencia {{ indicadorOrden("stock") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('comprometido')"
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.orden === 'comprometido'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('comprometido')"
                                    >
                                        Comprometido
                                        {{ indicadorOrden("comprometido") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('disponible')"
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.orden === 'disponible'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('disponible')"
                                    >
                                        Disponible
                                        {{ indicadorOrden("disponible") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('variantes')"
                                    class="px-3 py-2 text-right font-medium"
                                >
                                    Variantes
                                </th>
                                <th
                                    v-if="visible('costo')"
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.orden === 'costo'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('costo')"
                                    >
                                        Costo actual
                                        {{ indicadorOrden("costo") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('precio_venta')"
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.orden === 'precio_venta'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('precio_venta')"
                                    >
                                        P. venta unit.
                                        {{ indicadorOrden("precio_venta") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('invertido')"
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.orden === 'invertido'
                                            ? f.direccion === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('invertido')"
                                    >
                                        Invertido
                                        {{ indicadorOrden("invertido") }}
                                    </button>
                                </th>
                                <th
                                    v-if="visible('valor_venta')"
                                    class="px-3 py-2 text-right font-medium"
                                >
                                    Valor venta
                                </th>
                                <th
                                    v-if="visible('margen')"
                                    class="px-3 py-2 text-right font-medium"
                                >
                                    Margen
                                </th>
                                <th
                                    v-if="visible('alertas')"
                                    class="px-3 py-2 text-left font-medium"
                                >
                                    Alertas
                                </th>
                            </tr>
                            <tr v-else>
                                <th class="px-3 py-2 text-left font-medium">
                                    {{
                                        f.agrupar === "categoria"
                                            ? "Categoria"
                                            : "Proveedor"
                                    }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Articulos
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Existencia
                                </th>
                                <th
                                    class="px-3 py-2 text-right font-medium"
                                    :aria-sort="
                                        f.direccion === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="ordenar('invertido')"
                                    >
                                        Invertido
                                        {{ indicadorOrden("invertido") }}
                                    </button>
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Valor venta
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Margen
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Alertas
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template
                                v-for="item in items"
                                :key="itemKey(item)"
                            >
                                <tr class="font-mono hover:bg-slate-50">
                                    <template v-if="f.agrupar === 'producto'">
                                        <td
                                            v-if="visible('clave')"
                                            class="whitespace-nowrap px-3 py-2 text-slate-500"
                                        >
                                            {{ item.clave || "-" }}
                                        </td>
                                        <td
                                            class="min-w-64 px-3 py-2 font-sans"
                                        >
                                            <div
                                                class="font-medium text-slate-900"
                                            >
                                                {{ item.producto }}
                                            </div>
                                            <button
                                                v-if="
                                                    visible('variantes') &&
                                                    item.variantes > 0
                                                "
                                                type="button"
                                                class="mt-1 text-xs font-medium text-indigo-600 hover:underline"
                                                :aria-expanded="
                                                    !!variantesExpandidas[
                                                        item.id
                                                    ]
                                                "
                                                @click="
                                                    variantesExpandidas[
                                                        item.id
                                                    ] =
                                                        !variantesExpandidas[
                                                            item.id
                                                        ]
                                                "
                                            >
                                                {{
                                                    variantesExpandidas[item.id]
                                                        ? "Ocultar variantes"
                                                        : `Ver variantes (${item.variantes})`
                                                }}
                                            </button>
                                            <div
                                                v-if="
                                                    visible('series') &&
                                                    item.series?.length
                                                "
                                                class="mt-1 max-w-md text-xs leading-relaxed text-slate-500"
                                            >
                                                IMEI / Series:
                                                <template
                                                    v-for="(
                                                        serie, index
                                                    ) in seriesExpandidas[
                                                        item.id
                                                    ]
                                                        ? item.series
                                                        : item.series.slice(
                                                              0,
                                                              3,
                                                          )"
                                                    :key="serie"
                                                >
                                                    <span v-if="index">, </span>
                                                    <button
                                                        type="button"
                                                        class="break-all font-mono hover:text-indigo-700 hover:underline"
                                                        :aria-label="`Copiar ${serie}`"
                                                        title="Copiar IMEI / serie"
                                                        @click="
                                                            copiarSerie(serie)
                                                        "
                                                    >
                                                        {{ serie }}
                                                    </button>
                                                </template>
                                                <button
                                                    v-if="
                                                        item.series.length > 3
                                                    "
                                                    type="button"
                                                    class="ml-2 font-medium text-indigo-600 hover:underline"
                                                    :aria-expanded="
                                                        !!seriesExpandidas[
                                                            item.id
                                                        ]
                                                    "
                                                    @click="
                                                        seriesExpandidas[
                                                            item.id
                                                        ] =
                                                            !seriesExpandidas[
                                                                item.id
                                                            ]
                                                    "
                                                >
                                                    {{
                                                        seriesExpandidas[
                                                            item.id
                                                        ]
                                                            ? "Ver menos"
                                                            : `Ver los ${item.series.length}`
                                                    }}
                                                </button>
                                            </div>
                                        </td>
                                        <td
                                            v-if="visible('categoria')"
                                            class="whitespace-nowrap px-3 py-2 font-sans text-slate-500"
                                        >
                                            {{ item.categoria }}
                                        </td>
                                        <td
                                            v-if="visible('proveedor')"
                                            class="whitespace-nowrap px-3 py-2 font-sans text-slate-500"
                                        >
                                            {{ item.proveedor }}
                                        </td>
                                        <td
                                            v-if="visible('stock')"
                                            class="px-3 py-2 text-right"
                                        >
                                            {{ num(item.stock) }}
                                        </td>
                                        <td
                                            v-if="visible('comprometido')"
                                            class="px-3 py-2 text-right text-amber-700"
                                        >
                                            {{ num(item.comprometido) }}
                                        </td>
                                        <td
                                            v-if="visible('disponible')"
                                            class="px-3 py-2 text-right font-semibold text-emerald-700"
                                        >
                                            {{ num(item.disponible) }}
                                        </td>
                                        <td
                                            v-if="visible('variantes')"
                                            class="px-3 py-2 text-right"
                                        >
                                            {{ num(item.variantes) }}
                                        </td>
                                        <td
                                            v-if="visible('costo')"
                                            class="px-3 py-2 text-right"
                                        >
                                            {{ fmt(item.costo) }}
                                        </td>
                                        <td
                                            v-if="visible('precio_venta')"
                                            class="px-3 py-2 text-right"
                                        >
                                            {{ fmt(item.precio_venta) }}
                                        </td>
                                        <td
                                            v-if="visible('invertido')"
                                            class="px-3 py-2 text-right font-semibold text-slate-900"
                                        >
                                            {{ fmt(item.invertido) }}
                                        </td>
                                        <td
                                            v-if="visible('valor_venta')"
                                            class="px-3 py-2 text-right text-slate-600"
                                        >
                                            {{ fmt(item.valor_venta) }}
                                        </td>
                                        <td
                                            v-if="visible('margen')"
                                            class="px-3 py-2 text-right font-semibold"
                                            :class="colorMargen(item.margen)"
                                        >
                                            {{ num(item.margen) }}%
                                        </td>
                                        <td
                                            v-if="visible('alertas')"
                                            class="px-3 py-2 font-sans"
                                        >
                                            <span
                                                v-if="item.sin_costo"
                                                class="mr-1 border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700"
                                                >Sin costo</span
                                            >
                                            <span
                                                v-if="item.bajo_minimo"
                                                class="border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700"
                                                >Bajo mínimo</span
                                            >
                                            <span
                                                v-if="
                                                    !item.sin_costo &&
                                                    !item.bajo_minimo
                                                "
                                                class="text-slate-400"
                                                >—</span
                                            >
                                        </td>
                                    </template>

                                    <template v-else>
                                        <td
                                            class="min-w-64 px-3 py-2 font-sans font-medium text-slate-900"
                                        >
                                            {{ item.nombre }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ num(item.articulos) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ num(item.unidades) }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-semibold text-slate-900"
                                        >
                                            {{ fmt(item.invertido) }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right text-slate-600"
                                        >
                                            {{ fmt(item.valor_venta) }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-semibold"
                                            :class="colorMargen(item.margen)"
                                        >
                                            {{ num(item.margen) }}%
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right text-slate-500"
                                        >
                                            {{ item.sin_costo }} sin costo /
                                            {{ item.bajo_minimo }} bajo minimo
                                        </td>
                                    </template>
                                </tr>
                                <tr
                                    v-if="
                                        visible('variantes') &&
                                        variantesExpandidas[item.id] &&
                                        item.detalle_variantes?.length
                                    "
                                >
                                    <td
                                        :colspan="columnasTabla"
                                        class="bg-slate-50 px-5 py-3"
                                    >
                                        <table class="w-full text-xs">
                                            <caption
                                                class="mb-2 text-left font-semibold"
                                            >
                                                Variantes de
                                                {{
                                                    item.producto
                                                }}
                                            </caption>
                                            <thead class="text-slate-500">
                                                <tr>
                                                    <th class="p-2 text-left">
                                                        Variante / IMEI
                                                    </th>
                                                    <th class="p-2 text-left">
                                                        SKU
                                                    </th>
                                                    <th
                                                        v-if="visible('stock')"
                                                        class="p-2 text-right"
                                                    >
                                                        Existencia
                                                    </th>
                                                    <th
                                                        v-if="
                                                            visible(
                                                                'comprometido',
                                                            )
                                                        "
                                                        class="p-2 text-right"
                                                    >
                                                        Comprometido
                                                    </th>
                                                    <th
                                                        v-if="
                                                            visible(
                                                                'disponible',
                                                            )
                                                        "
                                                        class="p-2 text-right"
                                                    >
                                                        Disponible
                                                    </th>
                                                    <th
                                                        v-if="visible('costo')"
                                                        class="p-2 text-right"
                                                    >
                                                        Costo actual
                                                    </th>
                                                    <th
                                                        v-if="
                                                            visible(
                                                                'precio_venta',
                                                            )
                                                        "
                                                        class="p-2 text-right"
                                                    >
                                                        P. venta unit.
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="divide-y divide-slate-200"
                                            >
                                                <tr
                                                    v-for="variante in item.detalle_variantes"
                                                    :key="variante.id"
                                                >
                                                    <td class="p-2">
                                                        <div
                                                            class="font-medium"
                                                        >
                                                            {{
                                                                variante.nombre
                                                            }}
                                                        </div>
                                                        <div
                                                            v-if="
                                                                visible(
                                                                    'series',
                                                                ) &&
                                                                variante.series
                                                                    ?.length
                                                            "
                                                            class="mt-1 max-w-lg text-slate-500"
                                                        >
                                                            IMEI / Series:
                                                            <template
                                                                v-for="(
                                                                    serie,
                                                                    indice
                                                                ) in variante.series"
                                                                :key="serie"
                                                                ><span
                                                                    v-if="
                                                                        indice
                                                                    "
                                                                    >, </span
                                                                ><button
                                                                    type="button"
                                                                    class="break-all font-mono hover:underline"
                                                                    title="Copiar IMEI / serie"
                                                                    @click="
                                                                        copiarSerie(
                                                                            serie,
                                                                        )
                                                                    "
                                                                >
                                                                    {{ serie }}
                                                                </button></template
                                                            >
                                                        </div>
                                                    </td>
                                                    <td class="p-2 font-mono">
                                                        {{
                                                            variante.sku || "—"
                                                        }}
                                                    </td>
                                                    <td
                                                        v-if="visible('stock')"
                                                        class="p-2 text-right"
                                                    >
                                                        {{
                                                            num(variante.stock)
                                                        }}
                                                    </td>
                                                    <td
                                                        v-if="
                                                            visible(
                                                                'comprometido',
                                                            )
                                                        "
                                                        class="p-2 text-right text-amber-700"
                                                    >
                                                        {{
                                                            num(
                                                                variante.comprometido,
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        v-if="
                                                            visible(
                                                                'disponible',
                                                            )
                                                        "
                                                        class="p-2 text-right font-semibold text-emerald-700"
                                                    >
                                                        {{
                                                            num(
                                                                variante.disponible,
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        v-if="visible('costo')"
                                                        class="p-2 text-right"
                                                    >
                                                        {{
                                                            fmt(variante.costo)
                                                        }}
                                                    </td>
                                                    <td
                                                        v-if="
                                                            visible(
                                                                'precio_venta',
                                                            )
                                                        "
                                                        class="p-2 text-right"
                                                    >
                                                        {{
                                                            fmt(
                                                                variante.precio_venta,
                                                            )
                                                        }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="errorCarga && !cargando"
                    class="flex items-center gap-2 border-t border-red-100 bg-red-50 px-4 py-8 text-center text-xs text-red-600"
                >
                    <span class="mx-auto">{{ errorCarga }}</span>
                </div>
                <EstadoVacio
                    v-else-if="!items.length && !cargando"
                    texto="No hay inventario para los filtros seleccionados."
                />

                <div
                    v-if="pag.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-200 px-3 py-2 text-xs"
                >
                    <button
                        class="border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40"
                        :disabled="pag.current_page <= 1"
                        @click="cambiarPagina(pag.current_page - 1)"
                    >
                        Anterior
                    </button>
                    <span class="text-slate-500"
                        >Pagina {{ pag.current_page }} de
                        {{ pag.last_page }}</span
                    >
                    <button
                        class="border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40"
                        :disabled="pag.current_page >= pag.last_page"
                        @click="cambiarPagina(pag.current_page + 1)"
                    >
                        Siguiente
                    </button>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import {
    computed,
    defineComponent,
    h,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import axios from "axios";
import { toastSuccess, toastError } from "@/lib/alert";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import {
    FileSpreadsheet,
    FileText,
    Loader2,
    TriangleAlert,
} from "lucide-vue-next";

const EstadoVacio = defineComponent({
    props: { texto: String },
    setup: (props) => () =>
        h(
            "div",
            {
                class: "border-t border-slate-100 px-4 py-8 text-center text-xs text-slate-400",
            },
            props.texto,
        ),
});

const ResumenDato = defineComponent({
    props: {
        titulo: String,
        valor: String,
        destacado: Boolean,
        alerta: Boolean,
    },
    setup: (props) => () =>
        h(
            "div",
            {
                class: [
                    "border bg-white px-3 py-3",
                    props.destacado
                        ? "border-emerald-200"
                        : props.alerta
                          ? "border-amber-200"
                          : "border-slate-200",
                ],
            },
            [
                h(
                    "div",
                    { class: "text-xs font-medium text-slate-500" },
                    props.titulo,
                ),
                h(
                    "div",
                    {
                        class: [
                            "mt-1 truncate font-mono text-xl font-semibold",
                            props.destacado
                                ? "text-emerald-700"
                                : props.alerta
                                  ? "text-amber-700"
                                  : "text-slate-900",
                        ],
                        title: props.valor,
                    },
                    props.valor,
                ),
            ],
        ),
});

const resumen = ref({
    articulos: 0,
    unidades: 0,
    comprometidas: 0,
    disponibles: 0,
    invertido: 0,
    valor_venta: 0,
    margen_potencial: 0,
    sin_costo: 0,
    bajo_minimo: 0,
});
const seriesExpandidas = ref({});
const variantesExpandidas = ref({});
async function copiarSerie(serie) {
    try {
        await navigator.clipboard.writeText(String(serie));
        toastSuccess("IMEI / serie copiado");
    } catch {
        toastError(
            "No se pudo copiar. Selecciona el identificador y cópialo manualmente.",
        );
    }
}
const items = ref([]);
const categorias = ref([]);
const selectorColumnas = ref(null);
let solicitud = 0;
const cargando = ref(false);
const exportando = ref(null);
const errorCarga = ref(null);
const pag = ref({ current_page: 1, last_page: 1 });
let timer;

const agrupaciones = [
    { id: "producto", nombre: "Producto" },
    { id: "categoria", nombre: "Categoria" },
    { id: "proveedor", nombre: "Proveedor" },
];

const filtros = [
    { id: "todos", nombre: "Todos" },
    { id: "con_existencia", nombre: "Con existencia" },
    { id: "agotados", nombre: "Agotados" },
    { id: "sin_costo", nombre: "Sin costo" },
    { id: "bajo_minimo", nombre: "Bajo minimo" },
];

const filtrosSeries = [
    { id: "todos", nombre: "Con y sin series" },
    { id: "con_series", nombre: "Con series" },
    { id: "sin_series", nombre: "Sin series" },
];

const columnasDisponibles = [
    { id: "clave", nombre: "Clave" },
    { id: "producto", nombre: "Producto" },
    { id: "categoria", nombre: "Categoría" },
    { id: "proveedor", nombre: "Proveedor" },
    { id: "stock", nombre: "Existencia" },
    { id: "comprometido", nombre: "Comprometido" },
    { id: "disponible", nombre: "Disponible" },
    { id: "variantes", nombre: "Variantes" },
    { id: "costo", nombre: "Costo actual" },
    { id: "precio_venta", nombre: "P. venta unit." },
    { id: "invertido", nombre: "Invertido" },
    { id: "valor_venta", nombre: "Valor venta" },
    { id: "margen", nombre: "Margen" },
    { id: "alertas", nombre: "Alertas" },
    { id: "series", nombre: "IMEI / Series" },
];
const idsColumnas = columnasDisponibles.map((columna) => columna.id);
const presetsColumnas = [
    {
        id: "existencias",
        nombre: "Existencias",
        columnas: [
            "clave",
            "producto",
            "categoria",
            "stock",
            "comprometido",
            "disponible",
            "variantes",
            "series",
        ],
    },
    {
        id: "precios",
        nombre: "Precios",
        columnas: ["producto", "categoria", "costo", "precio_venta"],
    },
    {
        id: "valuacion",
        nombre: "Valuación",
        columnas: [
            "producto",
            "stock",
            "costo",
            "invertido",
            "valor_venta",
            "margen",
        ],
    },
    { id: "completo", nombre: "Completo", columnas: idsColumnas },
];
function columnasGuardadas() {
    try {
        const guardadas = JSON.parse(
            localStorage.getItem("reporte-inventario-columnas") || "[]",
        );
        const validas = guardadas.filter((id) => idsColumnas.includes(id));
        return validas.length
            ? Array.from(new Set(["producto", ...validas]))
            : [...idsColumnas];
    } catch {
        return [...idsColumnas];
    }
}
const columnasVisibles = ref(columnasGuardadas());
const columnasTabla = computed(
    () => columnasVisibles.value.filter((id) => id !== "series").length,
);
watch(
    columnasVisibles,
    (columnas) =>
        localStorage.setItem(
            "reporte-inventario-columnas",
            JSON.stringify(columnas),
        ),
    { deep: true },
);

function visible(columna) {
    return columnasVisibles.value.includes(columna);
}
function alternarColumna(columna, mostrar) {
    columnasVisibles.value = mostrar
        ? Array.from(new Set([...columnasVisibles.value, columna]))
        : columnasVisibles.value.filter(
              (id) => id !== columna || id === "producto",
          );
}
function aplicarPreset(columnas) {
    columnasVisibles.value = Array.from(
        new Set(["producto", ...columnas]),
    ).filter((id) => idsColumnas.includes(id));
}

const opcionesPorPagina = [15, 30, 50, 100];

const f = reactive({
    q: "",
    categoria_id: null,
    orden: "invertido",
    direccion: "desc",
    agrupar: "producto",
    filtro: "con_existencia",
    series: "todos",
    page: 1,
    por_pagina: 30,
});

const agrupacionActual = computed(
    () => agrupaciones.find((a) => a.id === f.agrupar)?.nombre ?? "Producto",
);
const tituloTabla = computed(() =>
    f.agrupar === "producto"
        ? "Inventario por producto"
        : `Inventario por ${agrupacionActual.value.toLowerCase()}`,
);

function cerrarColumnasAlPulsarFuera(evento) {
    if (
        selectorColumnas.value?.open &&
        !selectorColumnas.value.contains(evento.target)
    ) {
        selectorColumnas.value.removeAttribute("open");
    }
}

onMounted(() => {
    document.addEventListener("pointerdown", cerrarColumnasAlPulsarFuera);
    cargar();
});
onBeforeUnmount(() =>
    document.removeEventListener("pointerdown", cerrarColumnasAlPulsarFuera),
);

function buscar() {
    seriesExpandidas.value = {};
    variantesExpandidas.value = {};
    f.page = 1;
    cargar();
}

function debounce() {
    clearTimeout(timer);
    timer = setTimeout(buscar, 350);
}

function ordenar(campo) {
    f.direccion = f.orden === campo && f.direccion === "asc" ? "desc" : "asc";
    f.orden = campo;
    buscar();
}

function limpiarFiltros() {
    Object.assign(f, {
        q: "",
        categoria_id: null,
        filtro: "con_existencia",
        series: "todos",
        orden: "invertido",
        direccion: "desc",
        page: 1,
    });
    buscar();
}
function indicadorOrden(campo) {
    return f.orden === campo ? (f.direccion === "asc" ? "↑" : "↓") : "↕";
}

async function mensajeError(error, porDefecto) {
    const datos = error?.response?.data;
    if (datos instanceof Blob) {
        try {
            const json = JSON.parse(await datos.text());
            return json.message || porDefecto;
        } catch {
            return porDefecto;
        }
    }
    return datos?.message || porDefecto;
}

async function cargar() {
    const actual = ++solicitud;
    cargando.value = true;
    errorCarga.value = null;
    try {
        const { data } = await axios.get("/api/reportes/inventario", {
            params: { ...f },
        });
        if (actual !== solicitud) return;
        categorias.value = data.categorias ?? [];
        resumen.value = data.resumen ?? resumen.value;
        items.value = data.items?.data ?? [];
        pag.value = {
            current_page: data.items?.current_page ?? 1,
            last_page: data.items?.last_page ?? 1,
        };
    } catch (error) {
        console.error("reporteInventario", error);
        if (actual !== solicitud) return;
        items.value = [];
        const mensaje = await mensajeError(
            error,
            "No se pudo cargar el reporte de inventario.",
        );
        errorCarga.value = mensaje;
        toastError(mensaje);
    } finally {
        if (actual === solicitud) cargando.value = false;
    }
}

function cambiarPagina(page) {
    f.page = page;
    cargar();
}

async function exportar(formato) {
    exportando.value = formato;
    try {
        const resp = await axios.get("/api/reportes/inventario/exportar", {
            params: { ...f, formato, columnas: columnasVisibles.value },
            responseType: "blob",
        });
        const ext = formato === "pdf" ? "pdf" : "xlsx";
        const url = URL.createObjectURL(new Blob([resp.data]));
        const a = document.createElement("a");
        a.href = url;
        a.download = `inventario_${f.agrupar}.${ext}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error("exportarInventario", error);
        toastError(
            await mensajeError(
                error,
                "No se pudo generar la exportación. Intenta de nuevo.",
            ),
        );
    } finally {
        exportando.value = null;
    }
}

function itemKey(item) {
    return `${f.agrupar}-${item.id ?? item.nombre}`;
}

function colorMargen(valor) {
    if (+valor < 0) return "text-red-600";
    if (+valor < 15) return "text-amber-600";
    return "text-emerald-700";
}

const moneda = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});
const numero = new Intl.NumberFormat("es-MX", { maximumFractionDigits: 2 });
function fmt(valor) {
    return moneda.format(+valor || 0);
}
function num(valor) {
    return numero.format(+valor || 0);
}
</script>
