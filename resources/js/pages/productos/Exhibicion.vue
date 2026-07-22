<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- TOAST -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="toast"
                class="fixed right-5 top-5 z-[9999] flex max-w-md items-start gap-2 rounded-2xl border px-4 py-3 text-sm shadow-lg backdrop-blur"
                :class="
                    toast.tipo === 'error'
                        ? 'border-rose-200 bg-rose-50/90 text-rose-700'
                        : 'border-emerald-200 bg-emerald-50/90 text-emerald-700'
                "
            >
                <component
                    :is="toast.tipo === 'error' ? XCircle : CheckCircle2"
                    class="mt-0.5 h-4 w-4 shrink-0"
                />
                <p class="leading-relaxed">{{ toast.mensaje }}</p>
            </div>
        </Transition>

        <!-- HEADER -->
        <header
            class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"
                    >
                        <Store class="h-5 w-5" />
                    </div>
                    <div>
                        <h1
                            class="text-lg font-semibold tracking-tight text-slate-900"
                        >
                            Exhibición
                        </h1>
                        <p class="text-xs text-slate-500">
                            Control de productos entre exhibición y bodega
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="cargando"
                    @click="cargarDatos()"
                >
                    <Loader2 v-if="cargando" class="h-4 w-4 animate-spin" />
                    <RefreshCw v-else class="h-4 w-4" />
                    <span class="hidden sm:inline">Actualizar</span>
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
            <!-- STATS -->
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="t in tarjetas"
                    :key="t.key"
                    type="button"
                    @click="aplicarFiltro(t.key)"
                    class="group rounded-xl border bg-white px-4 py-3 text-left shadow-sm transition hover:border-slate-300 hover:shadow focus:outline-none focus:ring-4"
                    :class="
                        filtroAct === t.key
                            ? t.active
                            : 'border-slate-200 focus:ring-slate-100'
                    "
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wider text-slate-500"
                            >
                                {{ t.label }}
                            </p>
                            <p
                                class="mt-1 text-2xl font-bold leading-none"
                                :class="t.num"
                            >
                                {{ t.valor }}
                            </p>
                        </div>
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl ring-1 ring-inset"
                            :class="t.iconBox"
                        >
                            <component :is="t.icon" class="h-5 w-5" />
                        </div>
                    </div>

                    <p
                        v-if="filtroAct === t.key"
                        class="mt-2 text-xs font-semibold text-emerald-700"
                    >
                        Filtro activo
                    </p>
                </button>
            </div>

            <!-- TOOLBAR -->
            <div
                class="mt-4 rounded-xl border border-slate-200 bg-white p-3 shadow-sm"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="min-w-0 flex-1">
                        <BaseInput
                            v-model="busqueda"
                            label="Buscar producto"
                            placeholder="Nombre, código o SKU…"
                            @input="onBusqueda"
                        />
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2 lg:justify-end"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                            :aria-expanded="mostrarFiltros"
                            aria-controls="filtros-exhibicion"
                            @click="mostrarFiltros = !mostrarFiltros"
                        >
                            <ListFilter class="h-4 w-4" />
                            Filtros
                            <span
                                v-if="cantidadFiltros"
                                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald-100 px-1.5 text-[11px] font-bold text-emerald-700"
                            >
                                {{ cantidadFiltros }}
                            </span>
                            <ChevronDown
                                class="h-4 w-4 transition-transform"
                                :class="mostrarFiltros ? 'rotate-180' : ''"
                            />
                        </button>

                        <span class="text-sm text-slate-500">
                            <strong class="font-semibold text-slate-700">{{
                                paginacion?.total ?? 0
                            }}</strong>
                            resultado{{
                                (paginacion?.total ?? 0) !== 1 ? "s" : ""
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- FILTROS AVANZADOS -->
            <div
                v-show="mostrarFiltros"
                id="filtros-exhibicion"
                class="mt-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div
                    class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4"
                >
                    <label class="block">
                        <span
                            class="mb-1 block text-sm font-medium text-slate-700"
                            >Categoría</span
                        >
                        <select
                            v-model="filtros.categoria_id"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="actualizarFiltros"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="c in catalogos.categorias"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.nombre }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span
                            class="mb-1 block text-sm font-medium text-slate-700"
                            >Marca</span
                        >
                        <select
                            v-model="filtros.marca_id"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="cambiarMarca"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="m in catalogos.marcas"
                                :key="m.id"
                                :value="m.id"
                            >
                                {{ m.nombre }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span
                            class="mb-1 block text-sm font-medium text-slate-700"
                            >Modelo</span
                        >
                        <select
                            v-model="filtros.modelo_id"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="actualizarFiltros"
                        >
                            <option value="">Todos</option>
                            <option
                                v-for="m in modelosFiltrados"
                                :key="m.id"
                                :value="m.id"
                            >
                                {{ m.nombre }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span
                            class="mb-1 block text-sm font-medium text-slate-700"
                            >Orden</span
                        >
                        <select
                            v-model="filtros.orden"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            @change="actualizarFiltros"
                        >
                            <option value="prioridad">Prioridad</option>
                            <option value="categoria">Categoría</option>
                            <option value="marca">Marca / modelo</option>
                            <option value="stock">Mayor stock</option>
                            <option value="nombre">Nombre A-Z</option>
                        </select>
                    </label>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4"
                >
                    <span
                        v-if="hayFiltros"
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
                    >
                        Filtros activos
                    </span>
                    <span v-else class="text-xs text-slate-500">
                        Ordenado para revisar primero lo pendiente de exhibir.
                    </span>

                    <button
                        v-if="hayFiltros"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                        @click="limpiarFiltros"
                    >
                        <XCircle class="h-4 w-4" />
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <!-- TABLA -->
            <div
                class="mt-4 max-h-[68vh] overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    v-if="cargando"
                    class="divide-y divide-slate-100"
                    aria-label="Cargando productos"
                >
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="flex animate-pulse items-center gap-4 px-4 py-4"
                    >
                        <div class="h-10 w-10 rounded-lg bg-slate-200" />
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="h-3 w-48 rounded bg-slate-200" />
                            <div class="h-2.5 w-32 rounded bg-slate-100" />
                        </div>
                        <div class="h-7 w-24 rounded-lg bg-slate-100" />
                        <div class="h-7 w-20 rounded-lg bg-slate-100" />
                        <div class="h-8 w-24 rounded-lg bg-slate-200" />
                    </div>
                </div>

                <div v-else>
                    <table class="min-w-[1060px] w-full text-sm">
                        <thead
                            class="sticky top-0 z-10 bg-slate-50 shadow-[0_1px_0_0_rgb(226_232_240)]"
                        >
                            <tr
                                class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                <th class="w-[30%] px-4 py-3">Producto</th>
                                <th class="w-[16%] px-4 py-3">
                                    Categoría / marca
                                </th>
                                <th class="px-4 py-3 text-center">
                                    Existencias
                                </th>
                                <th class="px-4 py-3 text-center">Cobertura</th>
                                <th class="px-4 py-3 text-center">Condición</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3 text-center">Acción</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="items.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    <div
                                        class="flex flex-col items-center gap-3"
                                    >
                                        <PackageSearch
                                            class="h-9 w-9 text-slate-300"
                                        />
                                        <div>
                                            <p
                                                class="font-semibold text-slate-700"
                                            >
                                                No encontramos productos
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                Prueba con otra búsqueda o
                                                cambia los filtros.
                                            </p>
                                        </div>
                                        <button
                                            v-if="hayFiltros"
                                            type="button"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                            @click="limpiarFiltros"
                                        >
                                            Limpiar filtros
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <template
                                v-for="item in items"
                                :key="item.producto_id"
                            >
                                <tr
                                    class="transition hover:bg-slate-50/60"
                                    :class="
                                        item.exhibido ? 'bg-emerald-50/40' : ''
                                    "
                                    @dblclick="toggleDetalle(item)"
                                >
                                    <!-- Producto -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-10 w-10 overflow-hidden rounded-xl ring-1 ring-slate-200 bg-slate-100"
                                            >
                                                <img
                                                    v-if="item.producto?.imagen"
                                                    :src="`/storage/${item.producto.imagen}`"
                                                    class="h-full w-full object-cover"
                                                    alt=""
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-full w-full items-center justify-center text-slate-400"
                                                >
                                                    <Package class="h-4 w-4" />
                                                </div>
                                            </div>

                                            <button
                                                v-if="
                                                    item.tiene_variantes ||
                                                    item.exhibiciones_count > 1
                                                "
                                                type="button"
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-100"
                                                :aria-expanded="
                                                    detalleAbierto ===
                                                    item.producto_id
                                                "
                                                :aria-label="
                                                    detalleAbierto ===
                                                    item.producto_id
                                                        ? 'Ocultar variantes'
                                                        : 'Mostrar variantes'
                                                "
                                                @click="toggleDetalle(item)"
                                            >
                                                <ChevronDown
                                                    class="h-4 w-4 transition-transform"
                                                    :class="
                                                        detalleAbierto ===
                                                        item.producto_id
                                                            ? 'rotate-180'
                                                            : ''
                                                    "
                                                />
                                            </button>

                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="truncate font-semibold text-slate-900"
                                                >
                                                    {{
                                                        item.producto?.nombre ??
                                                        "Producto"
                                                    }}
                                                </p>
                                                <p
                                                    class="truncate text-xs text-slate-500"
                                                >
                                                    #{{ item.producto_id }} ·
                                                    {{ resumenProducto(item) }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Categoría / marca -->
                                    <td class="px-4 py-3">
                                        <div class="min-w-[8rem] text-xs">
                                            <p
                                                class="truncate font-semibold text-slate-700"
                                            >
                                                {{ categoriaItem(item) }}
                                            </p>
                                            <p class="truncate text-slate-500">
                                                {{ marcaItem(item) }} ·
                                                {{ modeloItem(item) }}
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Existencias -->
                                    <td class="px-4 py-3 text-center">
                                        <p class="font-bold text-slate-800">
                                            {{ parseFloat(item.stock) }} total
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs text-slate-500"
                                        >
                                            {{ stockBodega(item) }} en bodega
                                        </p>
                                    </td>

                                    <!-- Cobertura -->
                                    <td class="px-4 py-3 text-center">
                                        <div
                                            v-if="item.exhibido"
                                            class="mx-auto flex max-w-[15rem] flex-wrap justify-center gap-1.5"
                                        >
                                            <span
                                                v-for="exh in exhibicionesVisibles(
                                                    item,
                                                )"
                                                :key="exh.id"
                                                class="inline-flex max-w-[10rem] items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold ring-1"
                                                :class="
                                                    coberturaBadge(exh.tipo)
                                                "
                                            >
                                                <Tag
                                                    class="h-3.5 w-3.5 shrink-0"
                                                />
                                                <span class="truncate">{{
                                                    exh.label
                                                }}</span>
                                            </span>
                                            <span
                                                v-if="
                                                    exhibicionesRestantes(
                                                        item,
                                                    ) > 0
                                                "
                                                class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200"
                                            >
                                                +{{
                                                    exhibicionesRestantes(item)
                                                }}
                                            </span>
                                        </div>
                                        <span
                                            v-else
                                            class="text-sm text-slate-400"
                                            >—</span
                                        >
                                    </td>

                                    <!-- Condición -->
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="
                                                item.exhibido &&
                                                item.condicion_resumen
                                            "
                                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="
                                                estadoBadgeKey(
                                                    item.condicion_resumen.key,
                                                ).badge
                                            "
                                        >
                                            <span
                                                class="h-2 w-2 rounded-full"
                                                :class="
                                                    estadoBadgeKey(
                                                        item.condicion_resumen
                                                            .key,
                                                    ).dot
                                                "
                                            />
                                            {{ item.condicion_resumen.label }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-sm text-slate-400"
                                            >—</span
                                        >
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="estadoFila(item).cls"
                                        >
                                            <span
                                                class="h-2 w-2 rounded-full"
                                                :class="estadoFila(item).dot"
                                            />
                                            <component
                                                :is="estadoFila(item).icon"
                                                class="h-4 w-4"
                                            />
                                            {{ estadoFila(item).text }}
                                        </span>
                                    </td>

                                    <!-- Acción -->
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            v-if="item.exhibido"
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="
                                                item.exhibiciones_count > 1
                                                    ? 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-100'
                                                    : 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-100'
                                            "
                                            :disabled="
                                                procesando ===
                                                item.exhibicion_id
                                            "
                                            @click="
                                                item.exhibiciones_count > 1
                                                    ? toggleDetalle(item)
                                                    : abrirModalQuitar(item)
                                            "
                                        >
                                            <Loader2
                                                v-if="
                                                    procesando ===
                                                    item.exhibicion_id
                                                "
                                                class="h-4 w-4 animate-spin"
                                            />
                                            <Boxes
                                                v-else-if="
                                                    item.exhibiciones_count > 1
                                                "
                                                class="h-4 w-4"
                                            />
                                            <EyeOff v-else class="h-4 w-4" />
                                            {{
                                                item.exhibiciones_count > 1
                                                    ? "Gestionar"
                                                    : "Quitar"
                                            }}
                                        </button>

                                        <button
                                            v-else
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="
                                                parseFloat(item.stock) <= 0 ||
                                                procesando ===
                                                    item.inventario_id
                                            "
                                            @click="
                                                item.resultado_especifico
                                                    ? abrirModalExhibir(
                                                          item,
                                                          varianteEspecifica(
                                                              item,
                                                          ),
                                                      )
                                                    : item.tiene_variantes
                                                      ? toggleDetalle(item)
                                                      : abrirModalExhibir(item)
                                            "
                                        >
                                            <Loader2
                                                v-if="
                                                    procesando ===
                                                    item.inventario_id
                                                "
                                                class="h-4 w-4 animate-spin"
                                            />
                                            <Eye v-else class="h-4 w-4" />
                                            Exhibir
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="detalleAbierto === item.producto_id">
                                    <td
                                        colspan="7"
                                        class="bg-slate-50 px-4 py-4"
                                    >
                                        <div
                                            class="grid gap-4 lg:grid-cols-[16rem_1fr]"
                                        >
                                            <div
                                                class="rounded-xl border border-slate-200 bg-white p-4"
                                            >
                                                <p
                                                    class="text-xs font-semibold uppercase text-slate-500"
                                                >
                                                    Cobertura
                                                </p>
                                                <p
                                                    class="mt-1 text-sm font-semibold text-slate-900"
                                                >
                                                    {{ item.cobertura_label }}
                                                </p>
                                                <div
                                                    v-if="
                                                        item.exhibiciones
                                                            ?.length
                                                    "
                                                    class="mt-3 space-y-2"
                                                >
                                                    <div
                                                        v-for="exh in item.exhibiciones"
                                                        :key="exh.id"
                                                        class="rounded-lg bg-slate-50 px-3 py-2 text-xs ring-1 ring-slate-200"
                                                    >
                                                        <div
                                                            class="flex items-start justify-between gap-2"
                                                        >
                                                            <div
                                                                class="min-w-0"
                                                            >
                                                                <p
                                                                    class="truncate font-semibold text-slate-800"
                                                                >
                                                                    {{
                                                                        exh.label
                                                                    }}
                                                                </p>
                                                                <p
                                                                    class="mt-0.5 text-slate-500"
                                                                >
                                                                    {{
                                                                        descripcionCobertura(
                                                                            exh,
                                                                        )
                                                                    }}
                                                                </p>
                                                                <p
                                                                    class="mt-0.5 font-medium text-slate-600"
                                                                >
                                                                    {{
                                                                        exh.estado_label
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-white px-2 py-1 font-semibold text-rose-700 ring-1 ring-rose-200 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                                :disabled="
                                                                    procesando ===
                                                                    exh.id
                                                                "
                                                                @click="
                                                                    abrirModalQuitarExhibicion(
                                                                        item,
                                                                        exh,
                                                                    )
                                                                "
                                                            >
                                                                <EyeOff
                                                                    class="h-3.5 w-3.5"
                                                                />
                                                                Quitar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="
                                                        coberturaRedundante(
                                                            item,
                                                        )
                                                    "
                                                    class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200"
                                                >
                                                    La cobertura completa ya
                                                    cubre colores y tallas.
                                                    Quita esa cobertura para
                                                    marcar solo color o talla.
                                                </div>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                    :disabled="
                                                        parseFloat(
                                                            item.stock,
                                                        ) <= 0
                                                    "
                                                    @click="
                                                        abrirModalExhibir(
                                                            item,
                                                            'sin_variante',
                                                        )
                                                    "
                                                >
                                                    <Eye class="h-4 w-4" />
                                                    {{
                                                        item.exhibido
                                                            ? "Exhibir producto completo"
                                                            : "Exhibir sin variante específica"
                                                    }}
                                                </button>
                                            </div>

                                            <div class="space-y-3">
                                                <div
                                                    v-for="grupo in item.variantes_grupos"
                                                    :key="grupo.label"
                                                    class="rounded-xl border border-slate-200 bg-white p-4"
                                                >
                                                    <div
                                                        class="mb-3 flex items-center justify-between gap-3"
                                                    >
                                                        <p
                                                            class="text-sm font-semibold text-slate-900"
                                                        >
                                                            {{ grupo.label }}
                                                        </p>
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <span
                                                                class="text-xs text-slate-500"
                                                            >
                                                                {{
                                                                    grupo
                                                                        .variantes
                                                                        .length
                                                                }}
                                                                variante{{
                                                                    grupo
                                                                        .variantes
                                                                        .length !==
                                                                    1
                                                                        ? "s"
                                                                        : ""
                                                                }}
                                                            </span>
                                                            <button
                                                                v-if="
                                                                    grupo.atributo_id
                                                                "
                                                                type="button"
                                                                class="rounded-lg px-2 py-1 text-xs font-semibold shadow-sm transition"
                                                                :class="
                                                                    grupo.exhibido_color ||
                                                                    exhibicionGeneral(
                                                                        item,
                                                                    )
                                                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                                                        : 'bg-amber-600 text-white hover:bg-amber-700'
                                                                "
                                                                :disabled="
                                                                    grupo.exhibido_color ||
                                                                    exhibicionGeneral(
                                                                        item,
                                                                    )
                                                                "
                                                                @click="
                                                                    abrirModalExhibir(
                                                                        item,
                                                                        {
                                                                            tipo_cobertura:
                                                                                'color',
                                                                            atributo_id:
                                                                                grupo.atributo_id,
                                                                            label: grupo.label,
                                                                        },
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    exhibicionGeneral(
                                                                        item,
                                                                    )
                                                                        ? "Cubierto completo"
                                                                        : grupo.exhibido_color
                                                                          ? "Color exhibido"
                                                                          : "Exhibir color"
                                                                }}
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <button
                                                            v-for="v in grupo.variantes"
                                                            :key="v.variante_id"
                                                            type="button"
                                                            class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-left text-xs shadow-sm transition"
                                                            :class="
                                                                v.exhibido ||
                                                                exhibicionGeneral(
                                                                    item,
                                                                )
                                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                                    : v.stock >
                                                                        0
                                                                      ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                                                      : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400'
                                                            "
                                                            :disabled="
                                                                v.stock <= 0 ||
                                                                v.exhibido ||
                                                                exhibicionGeneral(
                                                                    item,
                                                                )
                                                            "
                                                            @click="
                                                                abrirModalExhibir(
                                                                    item,
                                                                    v,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="font-semibold"
                                                                >{{
                                                                    v.detalle_label
                                                                }}</span
                                                            >
                                                            <span
                                                                class="rounded-lg bg-slate-100 px-1.5 py-0.5 text-slate-500"
                                                            >
                                                                {{
                                                                    exhibicionGeneral(
                                                                        item,
                                                                    )
                                                                        ? "Cubierto"
                                                                        : v.exhibido
                                                                          ? "Exhibido"
                                                                          : v.stock
                                                                }}
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        !item.variantes_grupos
                                                            ?.length
                                                    "
                                                    class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-sm text-slate-500"
                                                >
                                                    Este producto no tiene
                                                    variantes con stock para
                                                    detallar.
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINACIÓN -->
            <div
                v-if="paginacion && paginacion.last_page > 1"
                class="mt-5 flex flex-wrap items-center justify-center gap-2"
            >
                <button
                    v-for="link in paginacion.links"
                    :key="link.label"
                    type="button"
                    class="inline-flex min-w-[40px] items-center justify-center rounded-xl border px-3 py-2 text-sm shadow-sm transition focus:outline-none focus:ring-4"
                    :class="[
                        link.active
                            ? 'border-amber-300 bg-amber-50 text-amber-800 focus:ring-amber-100'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-100',
                        !link.url ? 'opacity-50 cursor-not-allowed' : '',
                    ]"
                    :disabled="!link.url"
                    @click="irAPagina(link.url)"
                >
                    <ChevronLeft
                        v-if="String(link.label).includes('Previous')"
                        class="h-4 w-4"
                    />
                    <ChevronRight
                        v-else-if="String(link.label).includes('Next')"
                        class="h-4 w-4"
                    />
                    <span v-else v-html="link.label" />
                </button>
            </div>

            <!-- LEYENDA -->
            <div
                class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm"
            >
                <div class="flex items-start gap-2">
                    <Info class="mt-0.5 h-4 w-4 text-slate-400" />
                    <p>
                        <strong class="font-semibold text-slate-800"
                            >Lógica:</strong
                        >
                        La unidad en exhibición es vendible como cualquier otra.
                        Si se vende esa unidad, el producto queda sin exhibición
                        hasta marcarlo manualmente.
                    </p>
                </div>
            </div>
        </main>

        <!-- ════════════════════════════════════════════════════
         MODAL EXHIBIR — DOS PASOS
         ════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div
                v-if="modal && !modal.accion"
                class="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur"
                @click.self="modal = null"
            >
                <div
                    class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                >
                    <!-- Header modal -->
                    <div class="border-b border-slate-200 bg-white px-6 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl"
                                    :class="
                                        modal.paso === 1
                                            ? 'bg-violet-100 text-violet-700'
                                            : 'bg-amber-100 text-amber-700'
                                    "
                                >
                                    <component
                                        :is="
                                            modal.paso === 1
                                                ? Boxes
                                                : CheckCircle2
                                        "
                                        class="h-5 w-5"
                                    />
                                </div>

                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            modal.paso === 1
                                                ? "Seleccionar variante"
                                                : "Condición de exhibición"
                                        }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ nombreItem(modal.inventario) }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100"
                                @click="modal = null"
                                aria-label="Cerrar"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Steps -->
                        <div
                            v-if="modal.inventario?.producto?.tiene_variantes"
                            class="mt-3 flex items-center gap-2 text-xs"
                        >
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold"
                                :class="
                                    modal.paso >= 1
                                        ? 'bg-violet-50 text-violet-700 ring-1 ring-violet-200'
                                        : 'bg-slate-50 text-slate-500 ring-1 ring-slate-200'
                                "
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="
                                        modal.paso >= 1
                                            ? 'bg-violet-500'
                                            : 'bg-slate-300'
                                    "
                                />
                                Paso 1
                            </span>
                            <ArrowRight class="h-4 w-4 text-slate-400" />
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold"
                                :class="
                                    modal.paso >= 2
                                        ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200'
                                        : 'bg-slate-50 text-slate-500 ring-1 ring-slate-200'
                                "
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="
                                        modal.paso >= 2
                                            ? 'bg-amber-500'
                                            : 'bg-slate-300'
                                    "
                                />
                                Paso 2
                            </span>
                        </div>
                    </div>

                    <!-- Body modal -->
                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <!-- Paso 1 -->
                        <div v-if="modal.paso === 1">
                            <div
                                v-if="modal.cargandoVariantes"
                                class="flex items-center justify-center gap-2 py-10 text-sm text-slate-500"
                            >
                                <Loader2 class="h-4 w-4 animate-spin" />
                                <span>Cargando variantes…</span>
                            </div>

                            <div v-else class="space-y-2">
                                <!-- Sin variante -->
                                <label
                                    v-if="false"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:bg-slate-50"
                                >
                                    <input
                                        type="radio"
                                        name="variante"
                                        class="h-4 w-4 accent-amber-600"
                                        :checked="
                                            modal.varianteSeleccionada ===
                                            'sin_variante'
                                        "
                                        @change="
                                            modal.varianteSeleccionada =
                                                'sin_variante'
                                        "
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            Sin variante específica
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            Exhibir el producto en general
                                        </p>
                                    </div>
                                </label>

                                <!-- Variantes -->
                                <label
                                    v-for="v in modal.variantes"
                                    :key="v.variante_id"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:bg-slate-50"
                                >
                                    <input
                                        type="radio"
                                        name="variante"
                                        class="h-4 w-4 accent-amber-600"
                                        :checked="
                                            modal.varianteSeleccionada
                                                ?.variante_id === v.variante_id
                                        "
                                        @change="modal.varianteSeleccionada = v"
                                    />
                                    <div
                                        class="flex min-w-0 flex-1 items-center justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-900"
                                            >
                                                {{ nombreVarianteModal(v) }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-slate-500"
                                            >
                                                {{ metaVarianteModal(v) }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200"
                                        >
                                            <Boxes class="h-3.5 w-3.5" />
                                            {{ v.stock }}
                                        </span>
                                    </div>
                                </label>

                                <p
                                    v-if="modal.variantes.length === 0"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                                >
                                    No hay variantes con stock disponible.
                                </p>
                            </div>
                        </div>

                        <!-- Paso 2 -->
                        <div v-if="modal.paso === 2" class="space-y-2">
                            <label
                                v-for="(label, key) in estadosExhibicion"
                                :key="key"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                                :class="
                                    modal.estadoSeleccionado === key
                                        ? estadoBadgeKey(key).badge +
                                          ' shadow-sm'
                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                "
                            >
                                <input
                                    type="radio"
                                    name="estado"
                                    class="h-4 w-4 accent-amber-600"
                                    :checked="modal.estadoSeleccionado === key"
                                    @change="modal.estadoSeleccionado = key"
                                />
                                <span
                                    class="h-2.5 w-2.5 rounded-full"
                                    :class="estadoBadgeKey(key).dot"
                                />
                                <span class="text-sm font-semibold">{{
                                    label
                                }}</span>
                                <Check
                                    class="ml-auto h-4 w-4"
                                    :class="
                                        modal.estadoSeleccionado === key
                                            ? 'opacity-100'
                                            : 'opacity-0'
                                    "
                                />
                            </label>
                        </div>
                    </div>

                    <!-- Footer modal -->
                    <div
                        class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                            @click="modal = null"
                        >
                            Cancelar
                        </button>

                        <button
                            v-if="modal.paso === 1"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                modal.varianteSeleccionada === null ||
                                modal.cargandoVariantes
                            "
                            @click="siguientePaso"
                        >
                            Siguiente
                            <ArrowRight class="h-4 w-4" />
                        </button>

                        <template v-if="modal.paso === 2">
                            <button
                                v-if="
                                    modal.inventario?.producto
                                        ?.tiene_variantes &&
                                    !modal.seleccionDirecta
                                "
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                                @click="pasoAnterior"
                            >
                                <ArrowLeft class="h-4 w-4" />
                                Atrás
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!modal.estadoSeleccionado"
                                @click="ejecutarExhibir"
                            >
                                Confirmar
                                <CheckCircle2 class="h-4 w-4" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ════════════════════════════════════════════════════
         MODAL QUITAR
         ════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div
                v-if="modal?.accion === 'quitar'"
                class="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur"
                @click.self="modal = null"
            >
                <div
                    class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-100 text-rose-700"
                            >
                                <EyeOff class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    Quitar exhibición
                                </p>
                                <p class="text-xs text-slate-500">
                                    Esto marcará el producto como “En bodega”.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100"
                            @click="modal = null"
                            aria-label="Cerrar"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div
                        class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200"
                    >
                        ¿Quitar
                        <strong>{{
                            modal.inventario.exhibicion_label ||
                            nombreItem(modal.inventario)
                        }}</strong>
                        de exhibición?
                        <div
                            v-if="modal.inventario.variante_exhibida"
                            class="mt-2 text-xs text-slate-600"
                        >
                            Variante en piso:
                            <strong>{{
                                varianteExhibida(modal.inventario)
                            }}</strong>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                            @click="modal = null"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-100"
                            @click="ejecutarQuitar"
                        >
                            Quitar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import {
    ref,
    reactive,
    computed,
    onMounted,
    onBeforeUnmount,
    watch,
} from "vue";
import axios from "axios";
import BaseInput from "@/components/ui/BaseInput.vue";

// Lucide
import {
    Store,
    Search,
    Tag,
    Package,
    Boxes,
    Eye,
    EyeOff,
    CheckCircle2,
    Check,
    X,
    XCircle,
    Warehouse,
    Loader2,
    Info,
    ArrowRight,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ListFilter,
    RefreshCw,
    PackageSearch,
} from "lucide-vue-next";

// ── Estado global ─────────────────────────────────────────────────────────────
const items = ref([]);
const stats = ref({ total: 0, exhibidos: 0, sinExhibicion: 0, sinStock: 0 });
const estadosExhibicion = ref({}); // { perfecto: 'Perfecto', ... }
const catalogos = reactive({ categorias: [], marcas: [], modelos: [] });
const filtros = reactive({
    categoria_id: "",
    marca_id: "",
    modelo_id: "",
    orden: "prioridad",
});
const paginacion = ref(null);
const busqueda = ref("");
const filtroAct = ref("todos");
const cargando = ref(false);
const procesando = ref(null);
const toast = ref(null);
const detalleAbierto = ref(null);
const mostrarFiltros = ref(false);

// ── Modal de dos pasos ────────────────────────────────────────────────────────
const modal = ref(null);
/*
  modal = {
    inventario,
    paso: 1 | 2,
    // paso 1 (solo si tiene variantes):
    variantes: [],
    cargandoVariantes: false,
    varianteSeleccionada: null | { inventario_id, variante_id, sku, stock } | 'sin_variante'
    // paso 2:
    estadoSeleccionado: null,
    accion?: 'quitar' (modal simple)
  }
*/

// ── Cargar datos ──────────────────────────────────────────────────────────────
async function cargarDatos(url = "/api/exhibicion") {
    cargando.value = true;
    try {
        const params = {};
        if (filtroAct.value !== "todos") params.filtro = filtroAct.value;
        if (busqueda.value) params.busqueda = busqueda.value;
        if (filtros.categoria_id) params.categoria_id = filtros.categoria_id;
        if (filtros.marca_id) params.marca_id = filtros.marca_id;
        if (filtros.modelo_id) params.modelo_id = filtros.modelo_id;
        if (filtros.orden !== "prioridad") params.orden = filtros.orden;

        const { data } = await axios.get(url, { params });
        items.value = data.items.data;
        paginacion.value = data.items;
        stats.value = data.stats;
        estadosExhibicion.value = data.estadosExhibicion;
        catalogos.categorias = data.catalogos?.categorias ?? [];
        catalogos.marcas = data.catalogos?.marcas ?? [];
        catalogos.modelos = data.catalogos?.modelos ?? [];
    } catch {
        mostrarToast("Error al cargar los datos.", "error");
    } finally {
        cargando.value = false;
    }
}

function cerrarConEscape(event) {
    if (event.key === "Escape" && modal.value) modal.value = null;
}

onMounted(() => {
    cargarDatos();
    window.addEventListener("keydown", cerrarConEscape);
});

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
    window.removeEventListener("keydown", cerrarConEscape);
    document.body.style.overflow = "";
});

watch(modal, (abierto) => {
    document.body.style.overflow = abierto ? "hidden" : "";
});

// ── Búsqueda con debounce ─────────────────────────────────────────────────────
let debounceTimer = null;
function onBusqueda() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => cargarDatos(), 350);
}

function aplicarFiltro(filtro) {
    filtroAct.value = filtro;
    cargarDatos();
}

function actualizarFiltros() {
    cargarDatos();
}

function cambiarMarca() {
    filtros.modelo_id = "";
    cargarDatos();
}

function limpiarFiltros() {
    busqueda.value = "";
    filtros.categoria_id = "";
    filtros.marca_id = "";
    filtros.modelo_id = "";
    filtros.orden = "prioridad";
    filtroAct.value = "todos";
    cargarDatos();
}

function toggleDetalle(item) {
    detalleAbierto.value =
        detalleAbierto.value === item.producto_id ? null : item.producto_id;
}

function irAPagina(url) {
    if (!url) return;
    const urlObj = new URL(url);
    if (filtroAct.value !== "todos")
        urlObj.searchParams.set("filtro", filtroAct.value);
    if (busqueda.value) urlObj.searchParams.set("busqueda", busqueda.value);
    if (filtros.categoria_id)
        urlObj.searchParams.set("categoria_id", filtros.categoria_id);
    if (filtros.marca_id) urlObj.searchParams.set("marca_id", filtros.marca_id);
    if (filtros.modelo_id)
        urlObj.searchParams.set("modelo_id", filtros.modelo_id);
    if (filtros.orden !== "prioridad")
        urlObj.searchParams.set("orden", filtros.orden);
    cargarDatos(urlObj.toString());
}

// ── Abrir modal exhibir ───────────────────────────────────────────────────────
async function abrirModalExhibir(inventario, seleccion = null) {
    if (seleccion) {
        modal.value = {
            inventario,
            paso: 2,
            variantes: [],
            cargandoVariantes: false,
            varianteSeleccionada: seleccion,
            estadoSeleccionado: null,
            seleccionDirecta: true,
        };
        return;
    }

    const tieneVariantes = !!inventario.producto?.tiene_variantes;

    modal.value = {
        inventario,
        paso: tieneVariantes ? 1 : 2,
        variantes: [],
        cargandoVariantes: false,
        varianteSeleccionada: tieneVariantes ? null : "sin_variante",
        estadoSeleccionado: null,
    };

    if (tieneVariantes) {
        modal.value.cargandoVariantes = true;
        try {
            const { data } = await axios.get(
                `/api/exhibicion/${inventario.id}/variantes`,
            );
            modal.value.variantes = data.variantes ?? [];
        } catch {
            mostrarToast("Error al cargar variantes.", "error");
            modal.value = null;
        } finally {
            modal.value && (modal.value.cargandoVariantes = false);
        }
    }
}

function abrirModalQuitar(inventario) {
    modal.value = { inventario, accion: "quitar" };
}

function abrirModalQuitarExhibicion(item, exhibicion) {
    modal.value = {
        inventario: {
            ...item,
            exhibicion_id: exhibicion.id,
            exhibicion_label: exhibicion.label,
            variante_exhibida: null,
        },
        accion: "quitar",
    };
}

// ── Navegación del modal ──────────────────────────────────────────────────────
function siguientePaso() {
    if (modal.value?.paso === 1 && modal.value.varianteSeleccionada !== null) {
        modal.value.paso = 2;
    }
}

function pasoAnterior() {
    if (!modal.value) return;
    modal.value.paso = 1;
    modal.value.estadoSeleccionado = null;
}

// ── Ejecutar acciones ─────────────────────────────────────────────────────────
async function ejecutarExhibir() {
    const { inventario, varianteSeleccionada, estadoSeleccionado } =
        modal.value;
    const inventarioId = inventario.inventario_id ?? inventario.id;
    procesando.value = inventarioId;
    modal.value = null;

    const body = {
        estado_exhibicion: estadoSeleccionado,
        tipo_cobertura:
            varianteSeleccionada?.tipo_cobertura ??
            (varianteSeleccionada === "sin_variante" ? "producto" : "variante"),
        atributo_id: varianteSeleccionada?.atributo_id ?? null,
        variante_exhibida_id:
            varianteSeleccionada === "sin_variante"
                ? null
                : varianteSeleccionada?.tipo_cobertura === "color"
                  ? null
                  : (varianteSeleccionada?.variante_id ?? null),
    };

    try {
        const { data } = await axios.patch(
            `/api/exhibicion/${inventarioId}/exhibir`,
            body,
        );
        mostrarToast(data.message, "ok");
        await cargarDatos();
    } catch (e) {
        mostrarToast(e.response?.data?.error ?? "Ocurrió un error.", "error");
    } finally {
        procesando.value = null;
    }
}

async function ejecutarQuitar() {
    const { inventario } = modal.value;
    const inventarioId = inventario.inventario_id ?? inventario.id;
    const exhibicionId = inventario.exhibicion_id ?? null;
    procesando.value = exhibicionId ?? inventarioId;
    modal.value = null;

    try {
        const { data } = await axios.patch(
            `/api/exhibicion/${inventarioId}/quitar`,
            null,
            { params: { exhibicion_id: exhibicionId } },
        );
        mostrarToast(data.message, "ok");
        await cargarDatos();
    } catch (e) {
        mostrarToast(e.response?.data?.error ?? "Ocurrió un error.", "error");
    } finally {
        procesando.value = null;
    }
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function mostrarToast(mensaje, tipo = "ok") {
    toast.value = { mensaje, tipo };
    setTimeout(() => {
        toast.value = null;
    }, 3500);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function stockBodega(item) {
    if (item.stock_bodega !== undefined) return parseFloat(item.stock_bodega);

    return item.exhibido
        ? Math.max(0, parseFloat(item.stock) - 1)
        : parseFloat(item.stock);
}
function resumenProducto(item) {
    const codigo = item.producto?.codigo || "Sin código";
    const partes = [codigo];

    if (item.tiene_variantes) {
        partes.push(`${item.colores_count || 0} colores`);
        partes.push(`${item.variantes_count || 0} variantes`);
    }

    return partes.join(" · ");
}
function nombreItem(item) {
    const producto = item.producto?.nombre ?? "—";
    const variante = varianteItem(item);
    return variante ? `${producto} - ${variante}` : producto;
}
function varianteItem(item) {
    return item.variante?.nombre_variante || item.variante?.sku || null;
}
function varianteEspecifica(item) {
    return item.variantes_grupos?.[0]?.variantes?.[0] ?? null;
}
function codigoProductoSku(item) {
    const codigo = item.producto?.codigo || "—";
    const sku = skuVariante(item);
    return sku ? `${codigo} · SKU: ${sku}` : codigo;
}
function categoriaItem(item) {
    return item.producto?.categoria?.nombre || "Sin categoría";
}
function marcaItem(item) {
    return item.producto?.marca?.nombre || "Sin marca";
}
function modeloItem(item) {
    return item.producto?.modelo?.nombre || "Sin modelo";
}
function exhibicionGeneral(item) {
    return item.exhibiciones?.some((exh) => exh.tipo === "producto");
}
function coberturaRedundante(item) {
    return exhibicionGeneral(item) && (item.exhibiciones_count ?? 0) > 1;
}
function exhibicionesVisibles(item) {
    return (item.exhibiciones ?? []).slice(0, 2);
}
function exhibicionesRestantes(item) {
    return Math.max(
        0,
        (item.exhibiciones?.length ?? 0) - exhibicionesVisibles(item).length,
    );
}
function coberturaBadge(tipo) {
    if (tipo === "producto") {
        return "bg-emerald-50 text-emerald-700 ring-emerald-200";
    }

    if (tipo === "color") {
        return "bg-amber-50 text-amber-800 ring-amber-200";
    }

    return "bg-sky-50 text-sky-700 ring-sky-200";
}
function descripcionCobertura(exh) {
    if (exh.tipo === "producto") {
        return "Todo el producto";
    }

    if (exh.tipo === "color") {
        return "Color completo";
    }

    return "Variante específica";
}
function coberturaPrincipal(item) {
    if ((item.exhibiciones_count ?? 0) > 1) {
        return `${item.exhibiciones_count} exhibidos`;
    }

    return item.exhibiciones?.[0]?.label || "Exhibido";
}
function skuVariante(item) {
    return item.variante?.sku ?? null;
}
function varianteExhibida(item) {
    return (
        item.variante_exhibida?.nombre_variante ||
        item.variante_exhibida?.sku ||
        null
    );
}
function skuExhibida(item) {
    return item.variante_exhibida?.sku ?? null;
}
function nombreVarianteModal(variante) {
    return variante.nombre_variante || variante.sku || "Variante";
}
function coberturaExhibicion(variante) {
    if (variante.grupo_exhibicion === "color" && variante.grupo_label) {
        return `Cubre color: ${variante.grupo_label}`;
    }

    return "Cubre todas las tallas";
}
function metaVarianteModal(variante) {
    return [
        variante.sku ? `SKU: ${variante.sku}` : null,
        coberturaExhibicion(variante),
    ]
        .filter(Boolean)
        .join(" · ");
}

const modelosFiltrados = computed(() => {
    if (!filtros.marca_id) return catalogos.modelos;

    return catalogos.modelos.filter(
        (modelo) => Number(modelo.marca_id) === Number(filtros.marca_id),
    );
});

const hayFiltros = computed(
    () =>
        !!busqueda.value ||
        filtroAct.value !== "todos" ||
        !!filtros.categoria_id ||
        !!filtros.marca_id ||
        !!filtros.modelo_id ||
        filtros.orden !== "prioridad",
);

const cantidadFiltros = computed(
    () =>
        [
            busqueda.value,
            filtroAct.value !== "todos",
            filtros.categoria_id,
            filtros.marca_id,
            filtros.modelo_id,
            filtros.orden !== "prioridad",
        ].filter(Boolean).length,
);

const tarjetas = computed(() => [
    {
        key: "exhibidos",
        label: "Exhibidos",
        valor: stats.value.exhibidos,
        icon: Eye,
        active: "border-emerald-300 bg-emerald-50/50 ring-4 ring-emerald-100",
        num: "text-emerald-700",
        iconBox: "bg-emerald-100 text-emerald-700",
    },
    {
        key: "sinExhibicion",
        label: "Sin exhibición",
        valor: stats.value.sinExhibicion,
        icon: Warehouse,
        active: "border-amber-300 bg-amber-50/50 ring-4 ring-amber-100",
        num: "text-orange-700",
        iconBox: "bg-orange-100 text-orange-700",
    },
]);

const estadoUI = {
    perfecto: {
        badge: "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200",
        dot: "bg-emerald-500",
    },
    caja_abierta: {
        badge: "bg-amber-50 text-amber-800 ring-1 ring-amber-200",
        dot: "bg-amber-500",
    },
    con_detalles: {
        badge: "bg-rose-50 text-rose-700 ring-1 ring-rose-200",
        dot: "bg-rose-500",
    },
    mixto: {
        badge: "bg-slate-100 text-slate-700 ring-1 ring-slate-300",
        dot: "bg-slate-500",
    },
};

function estadoBadgeKey(key) {
    return (
        estadoUI[key] ?? {
            badge: "bg-slate-50 text-slate-700 ring-1 ring-slate-200",
            dot: "bg-slate-400",
        }
    );
}

function estadoFila(item) {
    if (item.exhibido)
        return {
            text: "Exhibido",
            cls: "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200",
            dot: "bg-emerald-500",
            icon: CheckCircle2,
        };
    return {
        text: "En bodega",
        cls: "bg-slate-50 text-slate-700 ring-1 ring-slate-200",
        dot: "bg-slate-400",
        icon: Warehouse,
    };
}
</script>
