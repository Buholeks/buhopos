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
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-5"
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
                            Control de productos en piso de ventas vs bodega
                        </p>
                    </div>
                </div>

                <div
                    class="hidden items-center gap-2 text-xs text-slate-500 md:flex"
                >
                    <Info class="h-4 w-4" />
                    <span
                        >El exhibido se vende como unidad normal; si se vende,
                        queda sin exhibición.</span
                    >
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-6">
            <!-- STATS -->
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="t in tarjetas"
                    :key="t.key"
                    type="button"
                    @click="aplicarFiltro(t.key)"
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4"
                    :class="
                        filtroAct === t.key
                            ? 'ring-4 ' + t.ring
                            : 'focus:ring-slate-100'
                    "
                >
                    <div
                        class="absolute -right-10 -top-10 h-28 w-28 rounded-full opacity-50 blur-2xl"
                        :class="t.bgGlow"
                    />
                    <div
                        class="relative flex items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wider text-slate-500"
                            >
                                {{ t.label }}
                            </p>
                            <p
                                class="mt-1 text-3xl font-semibold leading-none"
                                :class="t.num"
                            >
                                {{ t.valor }}
                            </p>
                        </div>
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl ring-1 ring-slate-200"
                            :class="t.iconBox"
                        >
                            <component :is="t.icon" class="h-5 w-5" />
                        </div>
                    </div>

                    <div
                        class="relative mt-3 flex items-center gap-2 text-xs text-slate-500"
                    >
                        <span
                            class="inline-flex h-2 w-2 rounded-full"
                            :class="
                                filtroAct === t.key
                                    ? 'bg-emerald-500'
                                    : 'bg-slate-300'
                            "
                        />
                        <span>{{
                            filtroAct === t.key
                                ? "Filtro activo"
                                : "Clic para filtrar"
                        }}</span>
                    </div>
                </button>
            </div>

            <!-- TOOLBAR -->
            <div
                class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="w-full sm:max-w-xl">
                    <!-- BaseInput -->
                    <div class="relative">
                        <div
                            class="pointer-events-none absolute left-3 top-[42px] -translate-y-1/2 text-slate-400"
                        >
                            <Search class="h-4 w-4" />
                        </div>

                        <BaseInput
                            v-model="busqueda"
                            label="Buscar"
                            placeholder="Buscar por nombre, código, SKU…"
                            @input="onBusqueda"
                            :class="'pl-10'"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-3 sm:justify-end"
                >
                    <span class="text-sm text-slate-500">
                        <strong class="font-semibold text-slate-700">{{
                            paginacion?.total ?? 0
                        }}</strong>
                        resultado{{ (paginacion?.total ?? 0) !== 1 ? "s" : "" }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="cargarDatos()"
                        :disabled="cargando"
                    >
                        <Loader2 v-if="cargando" class="h-4 w-4 animate-spin" />
                        <span>Actualizar</span>
                    </button>
                </div>
            </div>

            <!-- TABLA -->
            <div
                class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    v-if="cargando"
                    class="flex items-center justify-center gap-2 p-10 text-sm text-slate-500"
                >
                    <Loader2 class="h-4 w-4 animate-spin" />
                    <span>Cargando…</span>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr
                                class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                            >
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3">Variante / SKU</th>
                                <th class="px-4 py-3 text-center">Stock</th>
                                <th class="px-4 py-3 text-center">Bodega</th>
                                <th class="px-4 py-3 text-center">
                                    Variante exhibida
                                </th>
                                <th class="px-4 py-3 text-center">Condición</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3 text-center">Acción</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="items.length === 0">
                                <td
                                    colspan="9"
                                    class="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    No hay registros para este filtro.
                                </td>
                            </tr>

                            <tr
                                v-for="item in items"
                                :key="item.id"
                                class="transition hover:bg-slate-50/60"
                                :class="item.exhibido ? 'bg-emerald-50/40' : ''"
                            >
                                <!-- ID -->
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200"
                                    >
                                        <Tag class="h-3.5 w-3.5" />
                                        {{ item.producto_id }}
                                    </span>
                                </td>

                                <!-- Producto -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 overflow-hidden rounded-xl ring-1 ring-slate-200 bg-slate-100"
                                        >
                                            <img
                                                v-if="
                                                    item.variante?.imagen ||
                                                    item.producto?.imagen
                                                "
                                                :src="`/storage/${item.variante?.imagen ?? item.producto?.imagen}`"
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

                                        <div class="min-w-0">
                                            <p
                                                class="truncate font-semibold text-slate-900"
                                            >
                                                {{ nombreItem(item) }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-slate-500"
                                            >
                                                {{
                                                    item.producto?.codigo || "—"
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Variante -->
                                <td class="px-4 py-3">
                                    <span
                                        v-if="skuVariante(item)"
                                        class="inline-flex items-center gap-1 rounded-lg bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700 ring-1 ring-violet-200"
                                    >
                                        <Tag class="h-3.5 w-3.5" />
                                        {{ skuVariante(item) }}
                                    </span>
                                    <span v-else class="text-sm text-slate-400"
                                        >Sin variante</span
                                    >
                                </td>

                                <!-- Stock -->
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold text-amber-700">
                                        {{ parseFloat(item.stock) }}
                                    </span>
                                </td>

                                <!-- Bodega -->
                                <td class="px-4 py-3 text-center">
                                    <span class="font-medium text-slate-600">
                                        {{ stockBodega(item) }}
                                    </span>
                                </td>

                                <!-- Variante exhibida -->
                                <td class="px-4 py-3 text-center">
                                    <span
                                        v-if="
                                            item.exhibido && skuExhibida(item)
                                        "
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200"
                                    >
                                        <Tag class="h-3.5 w-3.5" />
                                        {{ skuExhibida(item) }}
                                    </span>
                                    <span
                                        v-else-if="item.exhibido"
                                        class="text-sm text-slate-500"
                                        >General</span
                                    >
                                    <span v-else class="text-sm text-slate-400"
                                        >—</span
                                    >
                                </td>

                                <!-- Condición -->
                                <td class="px-4 py-3 text-center">
                                    <span
                                        v-if="
                                            item.exhibido &&
                                            item.estado_exhibicion
                                        "
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="
                                            estadoBadgeKey(
                                                item.estado_exhibicion,
                                            ).badge
                                        "
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="
                                                estadoBadgeKey(
                                                    item.estado_exhibicion,
                                                ).dot
                                            "
                                        />
                                        {{
                                            estadosExhibicion[
                                                item.estado_exhibicion
                                            ] ?? item.estado_exhibicion
                                        }}
                                    </span>
                                    <span v-else class="text-sm text-slate-400"
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
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="procesando === item.id"
                                        @click="abrirModalQuitar(item)"
                                    >
                                        <Loader2
                                            v-if="procesando === item.id"
                                            class="h-4 w-4 animate-spin"
                                        />
                                        <EyeOff v-else class="h-4 w-4" />
                                        Quitar
                                    </button>

                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            parseFloat(item.stock) <= 0 ||
                                            procesando === item.id
                                        "
                                        @click="abrirModalExhibir(item)"
                                    >
                                        <Loader2
                                            v-if="procesando === item.id"
                                            class="h-4 w-4 animate-spin"
                                        />
                                        <Eye v-else class="h-4 w-4" />
                                        Exhibir
                                    </button>
                                </td>
                            </tr>
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
                        El exhibido es vendible como cualquier otra unidad. Si
                        se vende el <em class="font-medium">exhibido</em>, queda
                        sin exhibición hasta marcarlo manualmente.
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
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
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
                                <XCircle class="h-5 w-5" />
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
                    <div class="px-6 py-5">
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
                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:bg-slate-50"
                                >
                                    <input
                                        type="radio"
                                        name="variante"
                                        class="mt-1 h-4 w-4 accent-amber-600"
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
                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:bg-slate-50"
                                >
                                    <input
                                        type="radio"
                                        name="variante"
                                        class="mt-1 h-4 w-4 accent-amber-600"
                                        :checked="
                                            modal.varianteSeleccionada
                                                ?.variante_id === v.variante_id
                                        "
                                        @change="modal.varianteSeleccionada = v"
                                    />
                                    <div
                                        class="flex min-w-0 flex-1 items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-900"
                                            >
                                                {{ v.sku }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                Stock: {{ v.stock }}
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
                                class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:bg-slate-50"
                                :class="
                                    modal.estadoSeleccionado === key
                                        ? 'ring-4 ring-amber-100'
                                        : ''
                                "
                            >
                                <div class="flex items-center gap-3">
                                    <input
                                        type="radio"
                                        name="estado"
                                        class="h-4 w-4 accent-amber-600"
                                        :checked="
                                            modal.estadoSeleccionado === key
                                        "
                                        @change="modal.estadoSeleccionado = key"
                                    />
                                    <span
                                        class="text-sm font-semibold text-slate-900"
                                        >{{ label }}</span
                                    >
                                </div>

                                <span
                                    class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="estadoBadgeKey(key).badge"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="estadoBadgeKey(key).dot"
                                    />
                                    {{ label }}
                                </span>
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
                                    modal.inventario?.producto?.tiene_variantes
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
                            <XCircle class="h-5 w-5" />
                        </button>
                    </div>

                    <div
                        class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200"
                    >
                        ¿Quitar
                        <strong>{{ nombreItem(modal.inventario) }}</strong> de
                        exhibición?
                        <div
                            v-if="modal.inventario.variante_exhibida"
                            class="mt-2 text-xs text-slate-600"
                        >
                            Variante en piso:
                            <strong>{{
                                modal.inventario.variante_exhibida.sku
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
import { ref, computed, onMounted } from "vue";
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
    XCircle,
    Warehouse,
    Loader2,
    Info,
    ArrowRight,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
} from "lucide-vue-next";

// ── Estado global ─────────────────────────────────────────────────────────────
const items = ref([]);
const stats = ref({ total: 0, exhibidos: 0, sinExhibicion: 0, sinStock: 0 });
const estadosExhibicion = ref({}); // { perfecto: 'Perfecto', ... }
const paginacion = ref(null);
const busqueda = ref("");
const filtroAct = ref("todos");
const cargando = ref(false);
const procesando = ref(null);
const toast = ref(null);

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

        const { data } = await axios.get(url, { params });
        items.value = data.items.data;
        paginacion.value = data.items;
        stats.value = data.stats;
        estadosExhibicion.value = data.estadosExhibicion;
    } catch {
        mostrarToast("Error al cargar los datos.", "error");
    } finally {
        cargando.value = false;
    }
}

onMounted(() => cargarDatos());

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

function irAPagina(url) {
    if (!url) return;
    const urlObj = new URL(url);
    if (filtroAct.value !== "todos")
        urlObj.searchParams.set("filtro", filtroAct.value);
    if (busqueda.value) urlObj.searchParams.set("busqueda", busqueda.value);
    cargarDatos(urlObj.toString());
}

// ── Abrir modal exhibir ───────────────────────────────────────────────────────
async function abrirModalExhibir(inventario) {
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
    procesando.value = inventario.id;
    modal.value = null;

    const body = {
        estado_exhibicion: estadoSeleccionado,
        variante_exhibida_id:
            varianteSeleccionada === "sin_variante"
                ? null
                : (varianteSeleccionada?.variante_id ?? null),
    };

    try {
        const { data } = await axios.patch(
            `/api/exhibicion/${inventario.id}/exhibir`,
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
    procesando.value = inventario.id;
    modal.value = null;

    try {
        const { data } = await axios.patch(
            `/api/exhibicion/${inventario.id}/quitar`,
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
    return item.exhibido
        ? Math.max(0, parseFloat(item.stock) - 1)
        : parseFloat(item.stock);
}
function nombreItem(item) {
    return item.producto?.nombre ?? "—";
}
function skuVariante(item) {
    return item.variante?.sku ?? null;
}
function skuExhibida(item) {
    return item.variante_exhibida?.sku ?? null;
}

const tarjetas = computed(() => [
    {
        key: "exhibidos",
        label: "Exhibidos",
        valor: stats.value.exhibidos,
        icon: Eye,
        ring: "ring-emerald-200",
        bgGlow: "bg-emerald-100",
        num: "text-emerald-700",
        iconBox: "bg-emerald-100 text-emerald-700",
    },
    {
        key: "sinExhibicion",
        label: "Sin exhibición",
        valor: stats.value.sinExhibicion,
        icon: Warehouse,
        ring: "ring-orange-200",
        bgGlow: "bg-orange-100",
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
