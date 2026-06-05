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
                                @click="emit('ir-nueva')"
                                class="border-b-2 px-3 py-2 text-sm font-medium"
                                :class="
                                    varTab === 'nueva'
                                        ? 'border-emerald-600 text-emerald-700'
                                        : 'border-transparent text-slate-500 hover:text-slate-700'
                                "
                            >
                                Nueva
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
                                </div>
                            </div>

                            <div
                                v-if="(variantesFiltradas?.length ?? 0) > 0"
                                class="space-y-2"
                            >
                                <div
                                    v-for="v in variantesFiltradas"
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
                                                v-if="v.imagen_url"
                                                :src="v.imagen_url"
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
                                                    emit('toggle-editar', v)
                                                "
                                                title="Editar variante (también doble click)"
                                                class="rounded-md p-2 text-amber-600 hover:bg-amber-50"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>

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
                                                    <label
                                                        class="text-sm font-medium text-slate-700"
                                                        >SKU</label
                                                    >
                                                    <input
                                                        v-model.trim="
                                                            formEditProxy.sku
                                                        "
                                                        placeholder="Auto-generado"
                                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
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
                                                    <div
                                                        class="mt-2 flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white"
                                                        >
                                                            <img
                                                                v-if="
                                                                    formEditProxy.imagenPreview ||
                                                                    formEditProxy.imagenActualUrl
                                                                "
                                                                :src="
                                                                    formEditProxy.imagenPreview ||
                                                                    formEditProxy.imagenActualUrl
                                                                "
                                                                class="h-full w-full object-contain"
                                                                alt="preview"
                                                            />
                                                            <Image
                                                                v-else
                                                                class="h-6 w-6 text-slate-300"
                                                            />
                                                        </div>

                                                        <div
                                                            class="flex flex-col gap-2"
                                                        >
                                                            <input
                                                                type="file"
                                                                accept="image/*"
                                                                class="hidden"
                                                                @change="
                                                                    onEditImgChange
                                                                "
                                                            />

                                                            <button
                                                                type="button"
                                                                @click="
                                                                    abrirInputImgEdit(
                                                                        $event,
                                                                    )
                                                                "
                                                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                            >
                                                                {{
                                                                    formEditProxy.imagenActualUrl ||
                                                                    formEditProxy.imagenPreview
                                                                        ? "Cambiar"
                                                                        : "Subir imagen"
                                                                }}
                                                            </button>

                                                            <button
                                                                v-if="
                                                                    formEditProxy.imagenActualUrl ||
                                                                    formEditProxy.imagenPreview
                                                                "
                                                                type="button"
                                                                @click="
                                                                    emit(
                                                                        'quitar-imagen-edit',
                                                                    )
                                                                "
                                                                class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                                                            >
                                                                Quitar
                                                            </button>

                                                            <p
                                                                class="text-xs text-slate-400"
                                                            >
                                                                JPG, PNG, WebP ·
                                                                2MB
                                                            </p>
                                                        </div>
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

                            <div
                                v-else
                                class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500"
                            >
                                Sin variantes todavía.
                                <button
                                    class="mt-3 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                    @click="emit('ir-nueva')"
                                >
                                    <Plus class="h-4 w-4" />
                                    Crear primera variante
                                </button>
                            </div>
                        </div>

                        <!-- TAB: NUEVA -->
                        <div v-show="varTab === 'nueva'">
                            <div
                                v-if="
                                    (catalogos?.tiposAtributo?.length ?? 0) ===
                                    0
                                "
                                class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                            >
                                La empresa no tiene tipos de atributo
                                configurados. Ve a <strong>Atributos</strong> y
                                crea tipos como Color, Talla, Material.
                            </div>

                            <div
                                v-else
                                class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2"
                            >
                                <div
                                    v-for="tipo in catalogos?.tiposAtributo ??
                                    []"
                                    :key="`nv-${tipo.id}`"
                                >
                                    <BaseSearchSelect
                                        v-model.number="
                                            formVarProxy.atributos[tipo.id]
                                        "
                                        :items="tipo.atributos ?? []"
                                        :label="tipo.nombre"
                                        :placeholder="`Buscar ${tipo.nombre}…`"
                                        :label-key="(a) => a.valor"
                                        value-key="id"
                                        :disabled="!tipo.atributos?.length"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        SKU
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(opcional)</span
                                        >
                                    </label>
                                    <input
                                        v-model.trim="formVarProxy.sku"
                                        placeholder="Auto-generado"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        Código de barras
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(opcional)</span
                                        >
                                    </label>
                                    <input
                                        v-model.trim="
                                            formVarProxy.codigo_barras
                                        "
                                        placeholder="EAN/UPC"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        Imagen de la variante
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(opcional)</span
                                        >
                                    </label>

                                    <div class="mt-2 flex items-center gap-3">
                                        <div
                                            class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                                        >
                                            <img
                                                v-if="
                                                    formVarProxy.imagenPreview
                                                "
                                                :src="
                                                    formVarProxy.imagenPreview
                                                "
                                                class="h-full w-full object-contain"
                                                alt="preview"
                                            />
                                            <Image
                                                v-else
                                                class="h-6 w-6 text-slate-300"
                                            />
                                        </div>

                                        <div class="flex flex-col gap-2">
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="hidden"
                                                @change="onNewImgChange"
                                            />

                                            <button
                                                type="button"
                                                @click="
                                                    abrirInputImgNew($event)
                                                "
                                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                            >
                                                {{
                                                    formVarProxy.imagenPreview
                                                        ? "Cambiar"
                                                        : "Subir imagen"
                                                }}
                                            </button>

                                            <button
                                                v-if="
                                                    formVarProxy.imagenPreview
                                                "
                                                type="button"
                                                @click="
                                                    emit('quitar-imagen-nueva')
                                                "
                                                class="inline-flex items-center justify-center rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                                            >
                                                Quitar
                                            </button>

                                            <p class="text-xs text-slate-400">
                                                JPG, PNG, WebP · 2MB
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        Precio venta
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(vacío = hereda)</span
                                        >
                                    </label>
                                    <input
                                        v-model.number="
                                            formVarProxy.precio_venta
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
                                    >
                                        Stock mínimo
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(vacío = hereda)</span
                                        >
                                    </label>
                                    <input
                                        v-model.number="
                                            formVarProxy.stock_minimo
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="—"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    />
                                </div>

                                <div
                                    class="md:col-span-2 mt-2 border-t border-slate-200 pt-3 text-xs font-semibold uppercase tracking-wider text-slate-600"
                                >
                                    Oferta de esta variante
                                </div>

                                <div>
                                    <label
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        Precio oferta
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(opcional)</span
                                        >
                                    </label>
                                    <input
                                        v-model.number="
                                            formVarProxy.precio_oferta
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
                                    >
                                        Válida hasta
                                        <span
                                            class="text-xs font-normal text-slate-500"
                                            >(opcional)</span
                                        >
                                    </label>
                                    <input
                                        v-model="formVarProxy.oferta_hasta"
                                        type="date"
                                        :disabled="!formVarProxy.precio_oferta"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-100"
                                    />
                                </div>

                                <div
                                    class="md:col-span-2 flex items-center gap-3"
                                >
                                    <button
                                        type="button"
                                        :disabled="!formVarProxy.precio_oferta"
                                        @click="
                                            formVarProxy.oferta_activa =
                                                !formVarProxy.oferta_activa
                                        "
                                        class="relative h-6 w-11 rounded-full transition disabled:opacity-50"
                                        :class="
                                            formVarProxy.oferta_activa
                                                ? 'bg-emerald-600'
                                                : 'bg-slate-300'
                                        "
                                    >
                                        <span
                                            class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                            :class="
                                                formVarProxy.oferta_activa
                                                    ? 'left-5'
                                                    : 'left-0.5'
                                            "
                                        />
                                    </button>

                                    <span class="text-sm text-slate-700">
                                        {{
                                            formVarProxy.oferta_activa
                                                ? "Oferta activa para esta variante"
                                                : "Sin oferta activa"
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="mt-4 flex items-center justify-end gap-3"
                            >
                                <div
                                    v-if="combinacionDuplicada"
                                    class="mr-auto inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"
                                >
                                    <AlertTriangle class="h-4 w-4" />
                                    Esta combinación ya existe
                                </div>

                                <button
                                    @click="emit('crear')"
                                    :disabled="
                                        cargandoVar ||
                                        (catalogos?.tiposAtributo?.length ??
                                            0) === 0 ||
                                        combinacionDuplicada ||
                                        !algunAtributoSeleccionado
                                    "
                                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                                >
                                    <Loader2
                                        v-if="cargandoVar"
                                        class="h-4 w-4 animate-spin"
                                    />
                                    <Plus v-else class="h-4 w-4" />
                                    Agregar variante
                                </button>
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
                                        @click="emit('ir-nueva')"
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
import { computed, ref } from "vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import {
    LayoutGrid,
    X,
    Image,
    Pencil,
    Trash2,
    Plus,
    Loader2,
    AlertTriangle,
    Search,
} from "lucide-vue-next";

const props = defineProps({
    mostrar: { type: Boolean, default: false },
    productoNombre: { type: String, default: "" },

    catalogos: { type: Object, default: () => ({ tiposAtributo: [] }) },
    variantes: { type: Array, default: () => [] },

    varTab: { type: String, default: "lista" },
    varEditandoId: { type: [Number, String, null], default: null },
    cargandoVar: { type: Boolean, default: false },

    formVar: { type: Object, required: true },
    formEditVar: { type: Object, required: true },

    combinacionDuplicada: { type: Boolean, default: false },
    algunAtributoSeleccionado: { type: Boolean, default: false },

    formatPrecio: { type: Function, required: true },
    resumenPorTipo: { type: Function, default: null },
});

const emit = defineEmits([
    "cerrar",
    "update:varTab",
    "ir-nueva",
    "crear",
    "imagen-nueva-change",
    "quitar-imagen-nueva",
    "toggle-editar",
    "cerrar-edicion",
    "guardar-edicion",
    "imagen-edit-change",
    "quitar-imagen-edit",
    "eliminar",
    "update:formVar",
    "update:formEditVar",
]);

const formVarProxy = computed({
    get: () => props.formVar,
    set: (v) => emit("update:formVar", v),
});

const formEditProxy = computed({
    get: () => props.formEditVar,
    set: (v) => emit("update:formEditVar", v),
});

const busquedaVariante = ref("");

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

const variantesActivas = computed(
    () => (props.variantes ?? []).filter((v) => v.activo).length,
);

const variantesInactivas = computed(
    () => (props.variantes ?? []).filter((v) => !v.activo).length,
);

function setVarTab(t) {
    emit("update:varTab", t);
}

function abrirInputImgNew(event) {
    const contenedor = event.currentTarget.parentElement;
    const input = contenedor?.querySelector('input[type="file"]');
    input?.click();
}

function abrirInputImgEdit(event) {
    const contenedor = event.currentTarget.parentElement;
    const input = contenedor?.querySelector('input[type="file"]');
    input?.click();
}

function onNewImgChange(e) {
    const f = e.target.files?.[0];
    if (!f) return;

    emit("imagen-nueva-change", f);
    e.target.value = "";
}

function onEditImgChange(e) {
    const f = e.target.files?.[0];
    if (!f) return;

    emit("imagen-edit-change", f);
    e.target.value = "";
}
</script>
