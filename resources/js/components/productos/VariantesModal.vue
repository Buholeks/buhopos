<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mostrar"
                class="fixed inset-0 z-110 flex items-center justify-center bg-slate-900/40 p-4"
                @mousedown.self="emit('cerrar')"
            >
                <div
                    class="w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                >
                    <!-- Header -->
                    <div class="relative flex items-start gap-3 px-6 pt-6">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600"
                        >
                            <LayoutGrid class="h-5 w-5" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                Variantes · {{ productoNombre }}
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Un solo lugar para ver, crear y editar. (Doble
                                click en una variante = editar)
                            </p>
                        </div>

                        <button
                            @click="emit('cerrar')"
                            class="absolute right-4 top-4 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Cerrar"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="mt-4 border-b border-slate-200 px-6">
                        <div class="flex flex-wrap gap-2">
                            <button
                                @click="setVarTab('lista')"
                                class="border-b-2 px-3 py-2 text-sm font-medium"
                                :class="
                                    varTab === 'lista'
                                        ? 'border-blue-600 text-blue-700'
                                        : 'border-transparent text-slate-500 hover:text-slate-700'
                                "
                            >
                                Lista
                                <span
                                    class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[10px] text-slate-600"
                                >
                                    {{ variantes?.length ?? 0 }}
                                </span>
                            </button>

                            <button
                                @click="setVarTab('generar')"
                                class="border-b-2 px-3 py-2 text-sm font-medium"
                                :class="
                                    varTab === 'generar'
                                        ? 'border-emerald-600 text-emerald-700'
                                        : 'border-transparent text-slate-500 hover:text-slate-700'
                                "
                            >
                                Generar
                            </button>

                        </div>
                    </div>

                    <div class="max-h-[75vh] overflow-y-auto px-6 py-5">
                        <!-- TAB: LISTA -->
                        <div v-show="varTab === 'lista'">
                            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">
                                        Buscar variante
                                    </label>
                                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3">
                                        <Search class="h-4 w-4 text-slate-400" />
                                        <input
                                            v-model="busquedaVariante"
                                            placeholder="SKU, codigo, atributo..."
                                            class="w-full py-2.5 text-sm outline-none"
                                        />
                                    </div>
                                </div>
                                <div class="flex items-end gap-2">
                                    <span class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">
                                        {{ variantesActivas }} activas
                                    </span>
                                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                                        {{ variantesInactivas }} inactivas
                                    </span>
                                    <button
                                        type="button"
                                        :disabled="cargandoVar || (variantes ?? []).length === 0"
                                        title="Borra el precio propio de todas las variantes para que vuelvan a usar el precio del producto padre"
                                        class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="emit('restablecer-precios')"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" />
                                        Ajustar a precio padre
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="variantesAgrupadas.length > 0"
                                class="space-y-3"
                            >
                                <div
                                    v-for="grupo in variantesAgrupadas"
                                    :key="grupo.key"
                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 text-left hover:bg-slate-100"
                                        @click="toggleGrupo(grupo.key)"
                                    >
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                                            <img
                                                v-if="grupo.imagen_url"
                                                :src="grupo.imagen_url"
                                                :alt="grupo.label"
                                                class="h-full w-full object-contain"
                                            />
                                            <Image v-else class="h-4 w-4 text-slate-300" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ grupo.label }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ grupo.tipo }} · {{ grupo.variantes.length }} variantes
                                            </p>
                                        </div>
                                        <ChevronDown
                                            class="h-4 w-4 text-slate-400 transition"
                                            :class="grupoColapsado(grupo.key) ? '-rotate-90' : ''"
                                        />
                                    </button>

                                    <div v-show="!grupoColapsado(grupo.key)" class="space-y-2 p-2">
                                <div
                                    v-for="v in grupo.variantes"
                                    :key="v.id"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                                    @dblclick="emit('toggle-editar', v)"
                                    title="Doble click para editar esta variante"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <!-- Imagen -->
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white"
                                        >
                                            <img
                                                v-if="imagenVariante(v)"
                                                :src="imagenVariante(v)"
                                                :alt="v.nombre_variante"
                                                class="h-full w-full object-contain"
                                            />
                                            <Image
                                                v-else
                                                class="h-5 w-5 text-slate-300"
                                            />
                                        </div>

                                        <!-- Chips nombre_variante / atributos -->
                                        <div
                                            class="flex flex-1 flex-wrap gap-1.5"
                                        >
                                            <template v-if="v.nombre_variante">
                                                <span
                                                    v-for="parte in String(
                                                        v.nombre_variante,
                                                    ).split(' / ')"
                                                    :key="parte"
                                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                                                >
                                                    <span
                                                        class="font-medium text-slate-900"
                                                        >{{ parte }}</span
                                                    >
                                                </span>
                                            </template>

                                            <template v-else>
                                                <span
                                                    v-for="va in v.atributos ??
                                                    []"
                                                    :key="va.id"
                                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                                                >
                                                    <span
                                                        class="text-slate-500"
                                                    >
                                                        {{
                                                            va.tipo_atributo
                                                                ?.nombre ??
                                                            va.tipo?.nombre ??
                                                            ""
                                                        }}
                                                    </span>
                                                    <span
                                                        class="font-medium text-slate-900"
                                                    >
                                                        {{
                                                            va.atributo
                                                                ?.valor ?? "?"
                                                        }}
                                                    </span>
                                                </span>
                                            </template>
                                        </div>

                                        <span
                                            class="font-mono text-xs text-slate-500"
                                            >{{ v.sku }}</span
                                        >

                                        <!-- Precio -->
                                        <span
                                            class="font-mono text-sm text-slate-700"
                                        >
                                            <template
                                                v-if="
                                                    v.oferta_activa &&
                                                    v.precio_oferta
                                                "
                                            >
                                                <span
                                                    class="block font-semibold text-red-600"
                                                    >{{
                                                        formatPrecio(
                                                            v.precio_oferta,
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="block text-xs text-slate-400 line-through"
                                                >
                                                    {{
                                                        v.precio_venta != null
                                                            ? formatPrecio(
                                                                  v.precio_venta,
                                                              )
                                                            : "↑ padre"
                                                    }}
                                                </span>
                                            </template>
                                            <template v-else>
                                                {{
                                                    v.precio_venta != null
                                                        ? formatPrecio(
                                                              v.precio_venta,
                                                          )
                                                        : "↑ Hereda padre"
                                                }}
                                            </template>
                                        </span>

                                        <!-- Estado -->
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                            :class="
                                                v.activo
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-500'
                                            "
                                        >
                                            {{
                                                v.activo ? "Activa" : "Inactiva"
                                            }}
                                        </span>

                                        <!-- Acciones -->
                                        <div
                                            class="ml-auto flex items-center gap-1"
                                        >
                                            <button
                                                @click.stop="
                                                    emit('eliminar', v)
                                                "
                                                title="Eliminar variante"
                                                class="rounded-md p-2 text-red-600 hover:bg-red-50"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Panel edición inline -->
                                    <Transition
                                        enter-active-class="transition duration-150 ease-out"
                                        enter-from-class="opacity-0 -translate-y-1"
                                        enter-to-class="opacity-100 translate-y-0"
                                        leave-active-class="transition duration-150 ease-in"
                                        leave-from-class="opacity-100 translate-y-0"
                                        leave-to-class="opacity-0 -translate-y-1"
                                    >
                                        <div
                                            v-if="varEditandoId === v.id"
                                            class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
                                        >
                                            <div
                                                class="mb-3 flex items-center justify-between"
                                            >
                                                <span
                                                    class="text-sm text-slate-700"
                                                >
                                                    Editando:
                                                    <strong
                                                        class="text-emerald-700"
                                                        >{{
                                                            v.nombre_variante ||
                                                            v.sku
                                                        }}</strong
                                                    >
                                                </span>

                                                <button
                                                    @click="
                                                        emit('cerrar-edicion')
                                                    "
                                                    class="rounded-md p-2 text-slate-400 hover:bg-emerald-100 hover:text-emerald-800"
                                                    title="Cerrar edición"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div
                                                class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                            >
                                                <div>
                                                    <BaseInput
                                                        v-model.trim="formEditProxy.sku"
                                                        label="SKU"
                                                        placeholder="Auto-generado"
                                                        input-class="font-mono"
                                                    />
                                                </div>

                                                <div>
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Código de barras</label
                                                    >
                                                    <input
                                                        v-model.trim="
                                                            formEditProxy.codigo_barras
                                                        "
                                                        placeholder="EAN/UPC"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                                    />
                                                </div>

                                                <div
                                                    class="md:col-span-2 mt-2 border-t border-emerald-200 pt-3 text-xs font-semibold uppercase tracking-wider text-slate-600"
                                                >
                                                    Atributos de la variante
                                                </div>

                                                <div
                                                    v-for="tipo in catalogos?.tiposAtributo ?? []"
                                                    :key="`edit-attr-${tipo.id}`"
                                                >
                                                    <BaseSearchSelect
                                                        v-model.number="
                                                            formEditProxy.atributos[
                                                                tipo.id
                                                            ]
                                                        "
                                                        :items="tipo.atributos ?? []"
                                                        :label="tipo.nombre"
                                                        :placeholder="`Buscar ${tipo.nombre}...`"
                                                        :label-key="(a) => a.valor"
                                                        value-key="id"
                                                        :disabled="!tipo.atributos?.length"
                                                    />
                                                </div>

                                                <!-- Imagen variante -->
                                                <div class="md:col-span-2">
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Imagen de la
                                                        variante</label
                                                    >
                                                    <div class="mt-2">
                                                        <MediaPicker
                                                            :model-value="formEditProxy.imagenMedia"
                                                            carpeta-tipo="variante"
                                                            @update:model-value="emit('imagen-media-edit-change', $event)"
                                                            @clear="emit('quitar-imagen-edit')"
                                                        />
                                                    </div>
                                                </div>

                                                <div
                                                    class="md:col-span-2 mt-2 flex items-center justify-between border-t border-emerald-200 pt-3"
                                                >
                                                    <span
                                                        class="text-xs font-semibold uppercase tracking-wider text-slate-600"
                                                        >Precios</span
                                                    >
                                                    <span
                                                        class="text-xs text-slate-500"
                                                        >vacío = hereda del
                                                        producto padre</span
                                                    >
                                                </div>

                                                <div>
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Precio costo</label
                                                    >
                                                    <input
                                                        v-model.number="
                                                            formEditProxy.precio_costo
                                                        "
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="—"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                                    />
                                                </div>

                                                <div>
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Precio venta</label
                                                    >
                                                    <input
                                                        v-model.number="
                                                            formEditProxy.precio_venta
                                                        "
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="—"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                                    />
                                                </div>

                                                <div
                                                    v-for="n in 5"
                                                    :key="`ev-${n}`"
                                                >
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Precio {{ n }}</label
                                                    >
                                                    <input
                                                        v-model.number="
                                                            formEditProxy[
                                                                `precio${n}`
                                                            ]
                                                        "
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="—"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                                    />
                                                </div>

                                                <div
                                                    class="md:col-span-2 mt-2 border-t border-emerald-200 pt-3 text-xs font-semibold uppercase tracking-wider text-slate-600"
                                                >
                                                    Oferta de esta variante
                                                </div>

                                                <div>
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Precio oferta</label
                                                    >
                                                    <input
                                                        v-model.number="
                                                            formEditProxy.precio_oferta
                                                        "
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="—"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                                    />
                                                </div>

                                                <div>
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Válida hasta</label
                                                    >
                                                    <input
                                                        v-model="
                                                            formEditProxy.oferta_hasta
                                                        "
                                                        type="date"
                                                        :disabled="
                                                            !formEditProxy.precio_oferta
                                                        "
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-100"
                                                    />
                                                </div>

                                                <div
                                                    class="md:col-span-2 flex items-center gap-3"
                                                >
                                                    <button
                                                        type="button"
                                                        :disabled="
                                                            !formEditProxy.precio_oferta
                                                        "
                                                        @click="
                                                            formEditProxy.oferta_activa =
                                                                !formEditProxy.oferta_activa
                                                        "
                                                        class="relative h-6 w-11 rounded-full transition disabled:opacity-50"
                                                        :class="
                                                            formEditProxy.oferta_activa
                                                                ? 'bg-emerald-600'
                                                                : 'bg-slate-300'
                                                        "
                                                    >
                                                        <span
                                                            class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                                            :class="
                                                                formEditProxy.oferta_activa
                                                                    ? 'left-5'
                                                                    : 'left-0.5'
                                                            "
                                                        />
                                                    </button>

                                                    <span
                                                        class="text-sm text-slate-700"
                                                    >
                                                        {{
                                                            formEditProxy.oferta_activa
                                                                ? "Oferta activa"
                                                                : "Sin oferta activa"
                                                        }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="md:col-span-2 mt-2 border-t border-emerald-200 pt-3 text-xs font-semibold uppercase tracking-wider text-slate-600"
                                                >
                                                    Control
                                                </div>

                                                <div>
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                    >
                                                        Stock mínimo
                                                        <span
                                                            class="text-xs font-normal text-slate-400"
                                                            >(vacío =
                                                            hereda)</span
                                                        >
                                                    </label>
                                                    <input
                                                        v-model.number="
                                                            formEditProxy.stock_minimo
                                                        "
                                                        type="number"
                                                        min="0"
                                                        placeholder="—"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                                    />
                                                </div>

                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >Estado</label
                                                    >
                                                    <button
                                                        type="button"
                                                        @click="
                                                            formEditProxy.activo =
                                                                !formEditProxy.activo
                                                        "
                                                        class="relative h-6 w-11 rounded-full transition"
                                                        :class="
                                                            formEditProxy.activo
                                                                ? 'bg-emerald-600'
                                                                : 'bg-slate-300'
                                                        "
                                                    >
                                                        <span
                                                            class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                                            :class="
                                                                formEditProxy.activo
                                                                    ? 'left-5'
                                                                    : 'left-0.5'
                                                            "
                                                        />
                                                    </button>
                                                    <span
                                                        class="text-sm text-slate-700"
                                                    >
                                                        {{
                                                            formEditProxy.activo
                                                                ? "Activa"
                                                                : "Inactiva"
                                                        }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-4 flex items-center justify-end gap-2 border-t border-emerald-200 pt-3"
                                            >
                                                <button
                                                    @click="
                                                        emit('cerrar-edicion')
                                                    "
                                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                                >
                                                    Cancelar
                                                </button>

                                                <button
                                                    @click="
                                                        emit(
                                                            'guardar-edicion',
                                                            v,
                                                        )
                                                    "
                                                    :disabled="cargandoVar"
                                                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                                                >
                                                    <Loader2
                                                        v-if="cargandoVar"
                                                        class="h-4 w-4 animate-spin"
                                                    />
                                                    Guardar cambios
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500"
                            >
                                Sin variantes todavía.
                                <button
                                    class="mt-3 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                    @click="setVarTab('generar')"
                                >
                                    <Plus class="h-4 w-4" />
                                    Crear primera variante
                                </button>
                            </div>
                        </div>

                        <!-- TAB: GENERAR -->
                        <div v-show="varTab === 'generar'">
                            <div
                                v-if="
                                    (catalogos?.tiposAtributo?.length ?? 0) ===
                                    0
                                "
                                class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                            >
                                La empresa no tiene atributos configurados.
                            </div>

                            <div v-else class="space-y-4">
                                <div
                                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-semibold text-emerald-950">
                                                Generador de combinaciones
                                            </h3>
                                            <p class="mt-1 text-xs text-emerald-700">
                                                Selecciona varios valores por atributo y se crearan las variantes faltantes.
                                            </p>
                                        </div>
                                        <div class="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                            {{ variantesGenerables.length }} nuevas
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div
                                        v-for="tipo in catalogos?.tiposAtributo ?? []"
                                        :key="`gen-${tipo.id}`"
                                        class="rounded-xl border border-slate-200 bg-white p-4"
                                    >
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <div>
                                                <h4 class="text-sm font-semibold text-slate-900">
                                                    {{ tipo.nombre }}
                                                </h4>
                                                <p class="text-xs text-slate-500">
                                                    {{ seleccionMasiva[tipo.id]?.length ?? 0 }} seleccionados
                                                </p>
                                            </div>

                                            <button
                                                type="button"
                                                class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                                @click="limpiarTipoMasivo(tipo.id)"
                                            >
                                                Limpiar
                                            </button>
                                        </div>

                                        <BaseSearchSelect
                                            v-if="(tipo.atributos ?? []).length"
                                            :model-value="seleccionTemporal[tipo.id] ?? null"
                                            @update:model-value="(val) => agregarValorMasivoDirecto(tipo.id, val)"
                                            :items="valoresDisponibles(tipo)"
                                            :placeholder="`Seleccionar ${tipo.nombre}…`"
                                            :label-key="(a) => a.valor"
                                            value-key="id"
                                            :disabled="!valoresDisponibles(tipo).length"
                                        />

                                        <div v-if="(seleccionMasiva[tipo.id]?.length ?? 0) > 0" class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="atributo in valoresSeleccionados(tipo)"
                                                :key="atributo.id"
                                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200"
                                            >
                                                {{ atributo.valor }}
                                                <button
                                                    type="button"
                                                    class="rounded-full p-0.5 text-emerald-500 hover:bg-emerald-100 hover:text-emerald-800"
                                                    title="Quitar"
                                                    @click="quitarValorMasivo(tipo.id, atributo.id)"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </div>

                                        <div
                                            v-if="!(tipo.atributos ?? []).length"
                                            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-500"
                                        >
                                            Este atributo no tiene valores.
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-white">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                                        <div>
                                            <h4 class="text-sm font-semibold text-slate-900">
                                                Vista previa
                                            </h4>
                                            <p class="text-xs text-slate-500">
                                                Las combinaciones existentes se omiten automaticamente.
                                            </p>
                                        </div>
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                            {{ combinacionesMasivas.length }} combinaciones
                                        </span>
                                    </div>

                                    <div class="max-h-64 overflow-y-auto p-3">
                                        <div
                                            v-if="combinacionesMasivas.length === 0"
                                            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500"
                                        >
                                            Selecciona valores en al menos un atributo.
                                        </div>

                                        <div v-else class="overflow-x-auto">
                                            <table class="min-w-full border-separate border-spacing-0">
                                                <thead>
                                                    <tr>
                                                        <th class="border-b border-slate-200 px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                                            Variante
                                                        </th>
                                                        <th class="w-56 border-b border-slate-200 px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                                            SKU
                                                        </th>
                                                        <th class="w-28 border-b border-slate-200 px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                                            Estado
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                v-for="combo in combinacionesMasivas.slice(0, 80)"
                                                :key="combo.key"
                                                        class="bg-white"
                                            >
                                                        <td class="border-b border-slate-100 px-3 py-2">
                                                            <div class="flex flex-wrap gap-1.5">
                                                    <span
                                                        v-for="parte in combo.labels"
                                                        :key="parte"
                                                        class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"
                                                    >
                                                        {{ parte }}
                                                    </span>
                                                            </div>
                                                        </td>
                                                        <td class="border-b border-slate-100 px-3 py-2">
                                                            <BaseInput
                                                                v-model.trim="skuPorCombo[combo.key]"
                                                                :disabled="combo.existe"
                                                                placeholder="Opcional"
                                                                :error="skusConflicto[combo.key] ? 'Este SKU ya existe' : ''"
                                                                input-class="font-mono text-xs py-1.5"
                                                            />
                                                        </td>
                                                        <td class="border-b border-slate-100 px-3 py-2 text-center">
                                                <span
                                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                                    :class="
                                                        combo.existe
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-emerald-100 text-emerald-700'
                                                    "
                                                >
                                                    {{ combo.existe ? "Existe" : "Nueva" }}
                                                </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div
                                                v-if="combinacionesMasivas.length > 80"
                                                class="py-2 text-center text-xs text-slate-500"
                                            >
                                                Mostrando 80 de {{ combinacionesMasivas.length }} combinaciones.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-3">
                                    <div
                                        v-if="haySkusDuplicados"
                                        class="mr-auto inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"
                                    >
                                        <AlertTriangle class="h-4 w-4" />
                                        Hay SKUs que ya existen — corrígelos antes de continuar
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                        @click="limpiarGenerador"
                                    >
                                        Limpiar
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="cargandoVar || variantesGenerables.length === 0 || haySkusDuplicados"
                                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                                        @click="emit('crear-masivo', variantesGenerables)"
                                    >
                                        <Loader2
                                            v-if="cargandoVar"
                                            class="h-4 w-4 animate-spin"
                                        />
                                        <Plus v-else class="h-4 w-4" />
                                        Crear {{ variantesGenerables.length }} variantes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: POR TIPO -->
                        <div
                            v-for="tipo in catalogos?.tiposAtributo ?? []"
                            :key="`panel-${tipo.id}`"
                            v-if="false"
                        >
                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{ tipo.nombre }}
                                        </h3>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Resumen por valor (cuántas variantes
                                            usan ese valor).
                                        </p>
                                    </div>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700"
                                        @click="setVarTab('generar')"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Nueva variante
                                    </button>
                                </div>

                                <div
                                    class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                                >
                                    <div
                                        v-for="row in resumenPorTipo
                                            ? resumenPorTipo(tipo.id)
                                            : []"
                                        :key="`${tipo.id}-${row.valor}`"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2"
                                    >
                                        <span class="text-sm text-slate-800">{{
                                            row.valor
                                        }}</span>
                                        <span
                                            class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600"
                                        >
                                            {{ row.count }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            (resumenPorTipo
                                                ? resumenPorTipo(tipo.id).length
                                                : 0) === 0
                                        "
                                        class="rounded-lg border border-dashed border-slate-300 bg-white p-4 text-center text-sm text-slate-500"
                                    >
                                        No hay variantes usando este tipo
                                        todavía.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4"
                    >
                        <button
                            @click="emit('cerrar')"
                            class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import MediaPicker from "@/components/media/MediaPicker.vue";
import {
    LayoutGrid,
    X,
    Image,
    Trash2,
    Plus,
    Loader2,
    AlertTriangle,
    Search,
    ChevronDown,
    RotateCcw,
} from "lucide-vue-next";

const props = defineProps({
    mostrar: { type: Boolean, default: false },
    productoNombre: { type: String, default: "" },
    resetGeneradorKey: { type: Number, default: 0 },

    catalogos: { type: Object, default: () => ({ tiposAtributo: [] }) },
    variantes: { type: Array, default: () => [] },

    varTab: { type: String, default: "lista" },
    varEditandoId: { type: [Number, String, null], default: null },
    cargandoVar: { type: Boolean, default: false },

    formEditVar: { type: Object, required: true },

    formatPrecio: { type: Function, required: true },
    resumenPorTipo: { type: Function, default: null },
});

const emit = defineEmits([
    "cerrar",
    "update:varTab",
    "crear-masivo",
    "toggle-editar",
    "cerrar-edicion",
    "guardar-edicion",
    "imagen-media-edit-change",
    "quitar-imagen-edit",
    "eliminar",
    "update:formEditVar",
    "restablecer-precios",
]);

const formEditProxy = computed({
    get: () => props.formEditVar,
    set: (v) => emit("update:formEditVar", v),
});

const busquedaVariante = ref("");
const seleccionMasiva = ref({});
const seleccionTemporal = ref({});
const skuPorCombo = ref({});
const gruposColapsados = ref(new Set());

const existingSkus = computed(() =>
    new Set((props.variantes ?? []).map((v) => v.sku).filter(Boolean)),
);

const skusConflicto = computed(() => {
    const result = {};
    for (const [key, sku] of Object.entries(skuPorCombo.value)) {
        if (sku && existingSkus.value.has(sku)) {
            result[key] = true;
        }
    }
    return result;
});

const haySkusDuplicados = computed(() =>
    Object.keys(skusConflicto.value).length > 0,
);

const variantesFiltradas = computed(() => {
    const q = busquedaVariante.value.trim().toLowerCase();
    if (!q) return props.variantes ?? [];

    return (props.variantes ?? []).filter((v) => {
        const atributos = (v.atributos ?? [])
            .map((va) => `${va.tipo_atributo?.nombre ?? ""} ${va.atributo?.valor ?? ""}`)
            .join(" ");

        return [
            v.nombre_variante,
            v.sku,
            v.codigo_barras,
            atributos,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase()
            .includes(q);
    });
});

const tipoAgrupadorVisual = computed(() => {
    const tipos = props.catalogos?.tiposAtributo ?? [];
    const porNombre = tipos.find((tipo) =>
        ["color", "colores", "colors", "colour", "colours"].includes(normalizar(tipo.nombre ?? "")),
    );

    if (porNombre) return porNombre;
    if (tipos.length > 0) return tipos[0];

    const primerAtributo = (props.variantes ?? [])
        .flatMap((v) => v.atributos ?? [])
        .sort((a, b) => Number(a.tipo_atributo_id ?? 0) - Number(b.tipo_atributo_id ?? 0))[0];

    return primerAtributo
        ? {
              id: primerAtributo.tipo_atributo_id ?? primerAtributo.tipo_atributo?.id,
              nombre: primerAtributo.tipo_atributo?.nombre ?? "Atributo",
          }
        : null;
});

const variantesAgrupadas = computed(() => {
    const tipo = tipoAgrupadorVisual.value;
    const grupos = new Map();

    for (const variante of variantesFiltradas.value) {
        const atributo = atributoDeTipo(variante, tipo?.id);
        const key = atributo
            ? `tipo:${tipo.id}:atributo:${atributo.id}`
            : `sin-grupo:${variante.id}`;
        const label = atributo?.valor ?? variante.grupo_visual ?? "Sin grupo";

        if (!grupos.has(key)) {
            grupos.set(key, {
                key,
                label,
                tipo: tipo?.nombre ?? "Atributo",
                imagen_url: imagenVariante(variante),
                variantes: [],
            });
        }

        const grupo = grupos.get(key);
        if (!grupo.imagen_url) grupo.imagen_url = imagenVariante(variante);
        grupo.variantes.push(variante);
    }

    return Array.from(grupos.values()).map((grupo) => ({
        ...grupo,
        variantes: grupo.variantes.sort(ordenarVariantesDentroDeGrupo),
    }));
});

watch(
    variantesAgrupadas,
    (grupos) => {
        if (busquedaVariante.value.trim()) {
            gruposColapsados.value = new Set();
            return;
        }

        gruposColapsados.value = new Set(grupos.map((grupo) => grupo.key));
    },
    { immediate: true },
);

const variantesActivas = computed(
    () => (props.variantes ?? []).filter((v) => v.activo).length,
);

const variantesInactivas = computed(
    () => (props.variantes ?? []).filter((v) => !v.activo).length,
);

function imagenVariante(v) {
    return v?.imagen_url_resuelta ?? v?.imagen_url ?? null;
}

function atributoDeTipo(variante, tipoId) {
    if (!tipoId) return null;

    const atributo = (variante.atributos ?? []).find(
        (attr) => Number(attr.tipo_atributo_id ?? attr.tipo_atributo?.id) === Number(tipoId),
    );

    if (!atributo) return null;

    return {
        id: atributo.atributo_id ?? atributo.atributo?.id,
        valor: atributo.atributo?.valor ?? atributo.valor,
    };
}

function ordenarVariantesDentroDeGrupo(a, b) {
    return String(a.nombre_variante ?? a.sku ?? "").localeCompare(
        String(b.nombre_variante ?? b.sku ?? ""),
        "es",
        { numeric: true },
    );
}

function normalizar(value) {
    return String(value)
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/\s+/g, "");
}

const combinacionesMasivas = computed(() => {
    const grupos = (props.catalogos?.tiposAtributo ?? [])
        .map((tipo) => {
            const ids = seleccionMasiva.value[tipo.id] ?? [];
            const valores = ids
                .map((id) => (tipo.atributos ?? []).find((a) => Number(a.id) === Number(id)))
                .filter(Boolean);

            return valores.length
                ? {
                      tipoId: tipo.id,
                      tipoNombre: tipo.nombre,
                      valores,
                  }
                : null;
        })
        .filter(Boolean);

    if (!grupos.length) return [];

    return productoCartesiano(grupos).map((items, index) => {
        const atributos = Object.fromEntries(
            items.map((item) => [item.tipoId, item.atributo.id]),
        );
        const ids = Object.values(atributos).map(Number).sort((a, b) => a - b);
        const labels = items.map((item) => item.atributo.valor);
        return {
            key: ids.join("-"),
            atributos,
            labels,
            sku: skuPorCombo.value[ids.join("-")] ?? "",
            existe: existeCombinacion(ids),
        };
    });
});

const variantesGenerables = computed(() =>
    combinacionesMasivas.value.filter((combo) => !combo.existe),
);

function setVarTab(t) {
    emit("update:varTab", t);
}

function grupoColapsado(key) {
    return gruposColapsados.value.has(key);
}

function toggleGrupo(key) {
    const next = new Set(gruposColapsados.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    gruposColapsados.value = next;
}

watch(
    () => props.resetGeneradorKey,
    () => limpiarGenerador(),
);

function valoresSeleccionados(tipo) {
    const ids = seleccionMasiva.value[tipo.id] ?? [];
    return ids
        .map((id) => (tipo.atributos ?? []).find((a) => Number(a.id) === Number(id)))
        .filter(Boolean);
}

function valoresDisponibles(tipo) {
    const ids = new Set((seleccionMasiva.value[tipo.id] ?? []).map(Number));
    return (tipo.atributos ?? []).filter((atributo) => !ids.has(Number(atributo.id)));
}

function agregarValorMasivo(tipoId) {
    const atributoId = seleccionTemporal.value[tipoId];
    if (!atributoId) return;

    const actual = [...(seleccionMasiva.value[tipoId] ?? [])];
    if (!actual.some((id) => Number(id) === Number(atributoId))) {
        actual.push(Number(atributoId));
    }

    seleccionMasiva.value = {
        ...seleccionMasiva.value,
        [tipoId]: actual,
    };
    seleccionTemporal.value = {
        ...seleccionTemporal.value,
        [tipoId]: "",
    };
}

function agregarValorMasivoDirecto(tipoId, atributoId) {
    if (!atributoId) return;

    const actual = [...(seleccionMasiva.value[tipoId] ?? [])];
    if (!actual.some((id) => Number(id) === Number(atributoId))) {
        actual.push(Number(atributoId));
    }

    seleccionMasiva.value = { ...seleccionMasiva.value, [tipoId]: actual };
    seleccionTemporal.value = { ...seleccionTemporal.value, [tipoId]: null };
}

function quitarValorMasivo(tipoId, atributoId) {
    const actual = [...(seleccionMasiva.value[tipoId] ?? [])].filter(
        (id) => Number(id) !== Number(atributoId),
    );

    seleccionMasiva.value = {
        ...seleccionMasiva.value,
        [tipoId]: actual,
    };
}

function limpiarTipoMasivo(tipoId) {
    seleccionMasiva.value = {
        ...seleccionMasiva.value,
        [tipoId]: [],
    };
    seleccionTemporal.value = {
        ...seleccionTemporal.value,
        [tipoId]: "",
    };
}

function limpiarGenerador() {
    seleccionMasiva.value = {};
    seleccionTemporal.value = {};
    skuPorCombo.value = {};
}

function productoCartesiano(grupos) {
    return grupos.reduce(
        (acc, grupo) =>
            acc.flatMap((combo) =>
                grupo.valores.map((atributo) => [
                    ...combo,
                    {
                        tipoId: grupo.tipoId,
                        tipoNombre: grupo.tipoNombre,
                        atributo,
                    },
                ]),
            ),
        [[]],
    );
}

function existeCombinacion(ids) {
    return (props.variantes ?? []).some((variante) => {
        const existentes = (variante.atributos ?? [])
            .map((va) => va.atributo_id ?? va.atributo?.id)
            .filter(Boolean)
            .map(Number)
            .sort((a, b) => a - b);

        return JSON.stringify(existentes) === JSON.stringify(ids);
    });
}

</script>
