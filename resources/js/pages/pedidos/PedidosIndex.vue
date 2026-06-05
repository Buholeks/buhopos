<template>
    <main class="space-y-4">
        <header class="mx-auto flex w-full flex-col gap-3 sm:flex-row sm:items-end sm:justify-between" :class="mostrarFormulario && !mostrarConsulta ? 'max-w-6xl' : ''">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                    {{ etiquetaModulo }}
                </p>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                    {{ tituloVista }}
                </h1>
            </div>

            <div v-if="mostrarConsulta && !tipoBloqueado" class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-medium"
                    :class="filtroTipo === '' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'"
                    @click="filtroTipo = ''; cargarPedidos()"
                >
                    Todos
                </button>
                <button
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-medium"
                    :class="filtroTipo === 'pedido' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'"
                    @click="filtroTipo = 'pedido'; cargarPedidos()"
                >
                    Pedidos
                </button>
                <button
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-medium"
                    :class="filtroTipo === 'apartado' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'"
                    @click="filtroTipo = 'apartado'; cargarPedidos()"
                >
                    Apartados
                </button>
            </div>
        </header>

        <section
            class="mx-auto grid w-full gap-4"
            :class="contenedorClase"
        >
            <!-- Formulario nuevo pedido -->
            <form
    v-if="mostrarFormulario"
    class="rounded-2xl border border-slate-200 bg-white shadow-sm"
    @submit.prevent="guardarPedido"
>
    <!-- Header compacto -->
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
        <div>
            <h2 class="text-sm font-bold text-slate-900">
                Nuevo {{ form.tipo }}
            </h2>
            <p class="text-xs text-slate-500">
                Cliente, artículos y anticipo
            </p>
        </div>

        <div v-if="!tipoBloqueado" class="inline-flex rounded-xl bg-slate-100 p-1">
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="form.tipo === 'pedido'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'"
                @click="cambiarTipo('pedido')"
            >
                Pedido
            </button>

            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="form.tipo === 'apartado'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'"
                @click="cambiarTipo('apartado')"
            >
                Apartado
            </button>
        </div>

        <span
            v-else
            class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold capitalize text-emerald-700"
        >
            {{ form.tipo }}
        </span>
    </div>

    <div class="space-y-4 p-4">
        <!-- Cliente -->
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_150px]">
            <BaseSearchSelect
                :model-value="cliente?.id ?? null"
                label="Cliente"
                placeholder="Buscar cliente"
                :fetcher="buscarClientes"
                :min-chars="1"
                :label-key="(it) => it.nombre || 'Sin nombre'"
                :sub-label-key="(it) => it.telefono || it.email || 'Sin referencia'"
                value-key="id"
                required
                @selected="seleccionarCliente"
            />

            <div
                v-if="cliente"
                class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                    Saldo favor
                </p>
                <p class="text-base font-black text-emerald-900">
                    {{ money(resumenCliente.saldo_favor) }}
                </p>
            </div>
        </div>

        <!-- Fecha / anticipo / pago -->
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">
                    Fecha promesa
                </span>
                <input
                    v-model="form.fecha_promesa"
                    type="date"
                    class="field"
                />
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">
                    Anticipo
                </span>
                <input
                    v-model.number="form.anticipo"
                    type="number"
                    min="0"
                    step="0.01"
                    :max="subtotal"
                    class="field"
                    :class="Number(form.anticipo) > subtotal ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : ''"
                />
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-slate-600">
                    Pago anticipo
                </span>
                <select
                    v-model="form.forma_pago"
                    class="field"
                    :disabled="Number(form.anticipo) <= 0"
                    :class="Number(form.anticipo) <= 0 ? 'bg-slate-50 text-slate-400' : ''"
                >
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta">Tarjeta</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </label>
        </div>

        <p v-if="Number(form.anticipo) > subtotal" class="-mt-2 text-xs font-medium text-red-600">
            El anticipo no puede superar el total.
        </p>

        <!-- Artículos -->
        <div class="rounded-xl border border-slate-200">
            <div class="grid gap-3 border-b border-slate-100 p-3 lg:grid-cols-[minmax(0,1fr)_44px_170px] lg:items-end">
                <BaseSearchSelect
                    :key="`busqueda-${form.tipo}-${busquedaProductoKey}`"
                    :model-value="null"
                    :label="form.tipo === 'apartado' ? 'Producto a apartar' : 'Producto a pedir'"
                    :placeholder="form.tipo === 'apartado' ? 'Buscar producto con stock' : 'Buscar producto o variante'"
                    :fetcher="form.tipo === 'apartado' ? buscarProductosConStock : buscarProductosCatalogo"
                    :min-chars="1"
                    :label-key="labelProducto"
                    :sub-label-key="subLabelProducto"
                    value-key="selector_id"
                    @selected="agregarProductoDesdeBusqueda"
                />

                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45"
                    title="Crear producto con variante"
                    :disabled="form.tipo !== 'pedido'"
                    @click="abrirModalProductoRapido"
                >
                    <Plus class="h-4 w-4" />
                </button>

                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Articulos</p>
                    <p class="text-lg font-black text-slate-900">{{ form.detalles.length }}</p>
                </div>
            </div>

            <div v-if="form.detalles.length" class="overflow-x-auto">
                <table class="min-w-full table-fixed divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="min-w-[260px] px-3 py-2">Producto</th>
                            <th class="w-[100px] px-3 py-2 text-right">Cant.</th>
                            <th class="w-[130px] px-3 py-2 text-right">Precio</th>
                            <th class="w-[130px] px-3 py-2 text-right">Importe</th>
                            <th class="w-[48px] px-3 py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(detalle, index) in form.detalles"
                            :key="detalle.uid"
                            class="align-middle"
                        >
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-800">
                                    {{ detalle.descripcion }}
                                </p>

                                <p
                                    v-if="detalle.marca_texto || detalle.modelo_texto || detalle.color_texto || detalle.talla_texto"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ [detalle.marca_texto, detalle.modelo_texto, detalle.color_texto, detalle.talla_texto].filter(Boolean).join(" / ") }}
                                </p>

                                <p class="mt-1 text-[11px] text-slate-500">
                                    {{ detalle.variante_id ? "Variante vinculada" : detalle.producto_id ? "Producto base vinculado" : "Sin vinculo" }}
                                </p>
                            </td>

                            <td class="px-3 py-2">
                                <input
                                    v-model.number="detalle.cantidad"
                                    type="number"
                                    min="1"
                                    class="field field-sm text-right"
                                />
                            </td>

                            <td class="px-3 py-2">
                                <input
                                    v-model.number="detalle.precio_acordado"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="field field-sm text-right"
                                />
                            </td>

                            <td class="px-3 py-2 text-right font-bold text-slate-900">
                                {{ money(detalleImporte(detalle)) }}
                            </td>

                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    title="Quitar producto"
                                    @click="quitarDetalle(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-3 py-5 text-center text-sm text-slate-500">
                Busca y selecciona productos para agregarlos.
            </div>
        </div>

        <!-- Notas -->
        <textarea
            v-model="form.notas"
            rows="2"
            class="field resize-none"
            placeholder="Notas internas del pedido..."
        />

        <!-- Resumen -->
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Subtotal</span>
                <span class="font-bold text-slate-900">{{ money(subtotal) }}</span>
            </div>

            <div class="mt-1 flex justify-between text-sm">
                <span class="text-slate-500">Saldo pendiente</span>
                <span class="font-black text-emerald-700">{{ money(saldoPendiente) }}</span>
            </div>
        </div>

        <!-- Acción -->
        <button
            type="submit"
            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"
            :disabled="guardando || Number(form.anticipo) > subtotal"
        >
            <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
            Registrar {{ form.tipo }}
        </button>
    </div>
</form>

            <!-- Lista de pedidos -->
            <section v-if="mostrarConsulta" class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Seguimiento</h2>
                            <p class="text-sm text-slate-500">{{ subtituloConsulta }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Filtro de estado -->
                            <select
                                v-model="filtroEstado"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500"
                                @change="cargarPedidos"
                            >
                                <option value="">Todos los estados</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">En proceso</option>
                                <option value="disponible">Disponible</option>
                                <option value="parcial">Parcial</option>
                                <option value="entregado">Entregado</option>
                                <option value="cancelado">Cancelado</option>
                                <option value="vencido">Vencido</option>
                            </select>

                            <!-- Filtros de fecha -->
                            <input
                                v-model="filtroFechaDesde"
                                type="date"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500"
                                title="Desde"
                                @change="cargarPedidos"
                            />
                            <input
                                v-model="filtroFechaHasta"
                                type="date"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500"
                                title="Hasta"
                                @change="cargarPedidos"
                            />

                            <!-- Búsqueda -->
                            <div class="relative w-full sm:w-64">
                                <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <input
                                    v-model="buscar"
                                    class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    placeholder="Folio, cliente o artículo"
                                    @keyup.enter="cargarPedidos"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div v-if="cargando" class="flex items-center justify-center gap-2 p-8 text-sm text-slate-500">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Cargando pedidos
                    </div>

                    <div v-else-if="pedidos.length === 0" class="p-8 text-center text-sm text-slate-500">
                        No hay pedidos registrados.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Folio</th>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Articulos</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-right">Anticipo</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="pedido in pedidos" :key="pedido.id" class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ pedido.folio }}</p>
                                        <p class="text-xs capitalize text-slate-500">{{ pedido.tipo }}</p>
                                        <!-- Badge fecha vencida -->
                                        <span
                                            v-if="pedidoVencido(pedido)"
                                            class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700"
                                        >
                                            Vencido {{ diasVencido(pedido) }}d
                                        </span>
                                        <span
                                            v-else-if="pedidoProximoVencer(pedido)"
                                            class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700"
                                        >
                                            Vence en {{ diasParaVencer(pedido) }}d
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-900">{{ pedido.cliente?.nombre || "Sin cliente" }}</p>
                                        <p class="text-xs text-slate-500">{{ pedido.cliente?.telefono || "" }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="max-w-md space-y-1">
                                            <p
                                                v-for="detalle in pedido.detalles"
                                                :key="detalle.id"
                                                class="text-xs text-slate-600"
                                            >
                                                {{ detalle.cantidad }} x {{ detalle.descripcion }}
                                                <span
                                                    v-if="detalle.producto_id"
                                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500"
                                                >
                                                    catalogo
                                                </span>
                                                <span
                                                    v-if="detalle.compra_detalle_id"
                                                    class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700"
                                                >
                                                    compra vinculada
                                                </span>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                        {{ money(pedido.subtotal) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <p class="font-semibold text-emerald-700">{{ money(pedido.anticipo) }}</p>
                                        <p
                                            v-if="pedidoCerrado(pedido)"
                                            class="text-xs font-semibold text-emerald-700"
                                        >
                                            Liquidado
                                        </p>
                                        <p v-else class="text-xs text-slate-500">
                                            Debe {{ money(pedido.saldo_pendiente) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="estadoClass(pedido.estado)">
                                            {{ estadoLabel(pedido.estado) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col items-end gap-2">
                                            <!-- Botones de acción -->
                                            <div class="flex gap-1.5">
                                                <!-- Ver detalle -->
                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50 hover:text-slate-700"
                                                    title="Ver detalle e historial"
                                                    @click="abrirDetalle(pedido)"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                </button>
                                                <!-- Cancelar -->
                                                <button
                                                    v-if="!pedidoCerrado(pedido)"
                                                    type="button"
                                                    class="rounded-lg border border-red-200 p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700"
                                                    title="Cancelar pedido"
                                                    @click="confirmarCancelacion(pedido)"
                                                >
                                                    <XCircle class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <!-- Abono inline -->
                                            <div
                                                v-if="!pedidoCerrado(pedido)"
                                                class="flex w-full items-center gap-1.5"
                                            >
                                                <input
                                                    v-model.number="abonos[pedido.id].monto"
                                                    type="number"
                                                    min="0"
                                                    :max="pedido.saldo_pendiente"
                                                    step="0.01"
                                                    class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm outline-none focus:border-emerald-500"
                                                    placeholder="0.00"
                                                />
                                                <select
                                                    v-model="abonos[pedido.id].forma_pago"
                                                    class="rounded-lg border border-slate-200 px-1.5 py-1.5 text-xs outline-none focus:border-emerald-500"
                                                >
                                                    <option value="efectivo">Efec.</option>
                                                    <option value="tarjeta">Tarj.</option>
                                                    <option value="transferencia">Trans.</option>
                                                </select>
                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-emerald-200 px-2 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                                    @click="registrarAbono(pedido)"
                                                >
                                                    Abonar
                                                </button>
                                            </div>
                                            <p
                                                v-else
                                                class="text-xs font-semibold text-slate-400"
                                            >
                                                Cerrado
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </section>

        <!-- Modal: Detalle e historial de abonos -->
        <div
            v-if="modalDetalle.visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            @click.self="cerrarDetalle"
        >
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            {{ modalDetalle.pedido?.folio }}
                            <span class="ml-2 text-sm font-normal capitalize text-slate-500">{{ modalDetalle.pedido?.tipo }}</span>
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ modalDetalle.pedido?.cliente?.nombre }}
                            <span v-if="modalDetalle.pedido?.cliente?.telefono"> · {{ modalDetalle.pedido.cliente.telefono }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        @click="cerrarDetalle"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="modalDetalle.cargando" class="flex items-center justify-center gap-2 p-8 text-sm text-slate-500">
                    <Loader2 class="h-4 w-4 animate-spin" />
                    Cargando detalle
                </div>

                <div v-else-if="modalDetalle.data" class="max-h-[70vh] overflow-y-auto p-5 space-y-5">
                    <!-- Info general -->
                    <div class="grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-500">Estado</p>
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold" :class="estadoClass(modalDetalle.data.estado)">
                                {{ estadoLabel(modalDetalle.data.estado) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Fecha promesa</p>
                            <p class="font-medium text-slate-900">
                                {{ modalDetalle.data.fecha_promesa ? formatFecha(modalDetalle.data.fecha_promesa) : '—' }}
                            </p>
                            <span
                                v-if="pedidoVencido(modalDetalle.data)"
                                class="text-xs font-semibold text-red-600"
                            >Vencido</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Registrado</p>
                            <p class="font-medium text-slate-900">{{ formatFecha(modalDetalle.data.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Artículos -->
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-900">Artículos</h3>
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-slate-500">
                                <tr>
                                    <th class="pb-1">Descripción</th>
                                    <th class="pb-1 text-center">Cant.</th>
                                    <th class="pb-1 text-right">Precio</th>
                                    <th class="pb-1 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="d in modalDetalle.data.detalles" :key="d.id">
                                    <td class="py-1.5">
                                        <p class="font-medium text-slate-900">{{ d.descripcion }}</p>
                                        <p v-if="d.producto?.nombre" class="text-xs text-slate-400">{{ d.producto.nombre }}</p>
                                    </td>
                                    <td class="py-1.5 text-center text-slate-700">{{ d.cantidad }}</td>
                                    <td class="py-1.5 text-right text-slate-700">{{ money(d.precio_acordado) }}</td>
                                    <td class="py-1.5 text-right font-semibold text-slate-900">{{ money(d.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumen financiero -->
                    <div class="rounded-xl bg-slate-50 p-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Total</span>
                            <span class="font-bold text-slate-900">{{ money(modalDetalle.data.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Anticipo pagado</span>
                            <span class="font-semibold text-emerald-700">{{ money(modalDetalle.data.anticipo) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-1 text-sm">
                            <span class="font-medium text-slate-700">Saldo pendiente</span>
                            <span class="font-bold text-slate-900">{{ money(modalDetalle.data.saldo_pendiente) }}</span>
                        </div>
                    </div>

                    <!-- Historial de abonos -->
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-900">Historial de pagos</h3>
                        <div v-if="!modalDetalle.data.saldos?.length" class="rounded-lg border border-slate-100 p-4 text-center text-sm text-slate-400">
                            Sin pagos registrados
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="mov in modalDetalle.data.saldos"
                                :key="mov.id"
                                class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm"
                            >
                                <div>
                                    <p class="font-medium text-slate-900">{{ mov.concepto }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ formatFechaHora(mov.created_at) }}
                                        <span v-if="mov.user"> · {{ mov.user.name }}</span>
                                        <span v-if="mov.forma_pago"> · {{ mov.forma_pago }}</span>
                                    </p>
                                </div>
                                <span
                                    class="font-semibold"
                                    :class="['abono','devolucion','ajuste'].includes(mov.tipo) ? 'text-emerald-700' : 'text-red-600'"
                                >
                                    {{ ['abono','devolucion','ajuste'].includes(mov.tipo) ? '+' : '-' }}{{ money(mov.monto) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Notas -->
                    <div v-if="modalDetalle.data.notas">
                        <h3 class="mb-1 text-sm font-semibold text-slate-900">Notas</h3>
                        <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ modalDetalle.data.notas }}</p>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="cerrarDetalle"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Confirmar cancelación -->
        <div
            v-if="modalCancelar.visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        >
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Cancelar pedido</h2>
                    <p class="text-sm text-slate-500">{{ modalCancelar.pedido?.folio }} · {{ modalCancelar.pedido?.cliente?.nombre }}</p>
                </div>
                <div class="p-5 space-y-3">
                    <p class="text-sm text-slate-700">
                        ¿Confirmas la cancelación de este pedido?
                    </p>
                    <div v-if="Number(modalCancelar.pedido?.anticipo) > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        El cliente tiene un anticipo de <strong>{{ money(modalCancelar.pedido?.anticipo) }}</strong>.
                        Este monto se acreditará como <strong>saldo a favor</strong> del cliente.
                    </div>
                    <div v-if="modalCancelar.pedido?.tipo === 'apartado'" class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
                        Se liberará el inventario reservado para este apartado.
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        :disabled="modalCancelar.procesando"
                        @click="modalCancelar.visible = false"
                    >
                        Volver
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                        :disabled="modalCancelar.procesando"
                        @click="ejecutarCancelacion"
                    >
                        <Loader2 v-if="modalCancelar.procesando" class="h-4 w-4 animate-spin" />
                        Confirmar cancelación
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Producto rapido -->
        <div
            v-if="modalProductoRapido.visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        >
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Crear producto para pedido</h2>
                    <p class="text-sm text-slate-500">Alta minima con variante para agregarlo al pedido.</p>
                </div>

                <div class="space-y-4 p-5">
                    <div
                        v-if="catalogos.tiposAtributo.length === 0"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                    >
                        Primero registra tipos de atributo y valores para poder crear variantes.
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Nombre</span>
                        <input v-model.trim="modalProductoRapido.nombre" class="field" placeholder="Nombre del producto" />
                    </label>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <BaseSearchSelect
                            v-model.number="modalProductoRapido.marca_id"
                            :items="catalogos.marcas"
                            label="Marca"
                            placeholder="Buscar marca"
                            :label-key="(m) => m.nombre"
                            value-key="id"
                            :disabled="!catalogos.marcas.length"
                            @change="modalProductoRapido.modelo_id = ''"
                        />

                        <BaseSearchSelect
                            v-model.number="modalProductoRapido.modelo_id"
                            :items="modelosProductoRapido"
                            label="Modelo"
                            placeholder="Buscar modelo"
                            :label-key="(m) => m.nombre"
                            value-key="id"
                            :disabled="!modalProductoRapido.marca_id || !modelosProductoRapido.length"
                            hint="Primero selecciona una marca."
                        />
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Precio venta</span>
                        <input v-model.number="modalProductoRapido.precio_venta" type="number" min="0" step="0.01" class="field" />
                    </label>

                    <div
                        v-for="tipo in catalogos.tiposAtributo"
                        :key="`rapido-${tipo.id}`"
                    >
                        <BaseSearchSelect
                            v-model.number="modalProductoRapido.atributos[tipo.id]"
                            :items="tipo.atributos ?? []"
                            :label="tipo.nombre"
                            :placeholder="`Seleccionar ${tipo.nombre}`"
                            :label-key="(a) => a.valor"
                            value-key="id"
                            :disabled="!tipo.atributos?.length"
                            required
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="cerrarModalProductoRapido"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="modalProductoRapido.guardando || !modalProductoRapido.nombre || catalogos.tiposAtributo.length === 0"
                        @click="crearProductoRapido"
                    >
                        <Loader2 v-if="modalProductoRapido.guardando" class="h-4 w-4 animate-spin" />
                        Crear y agregar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Crear variante -->
        <div
            v-if="modalVariante.visible"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        >
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Crear variante para pedido</h2>
                    <p class="text-sm text-slate-500">{{ modalVariante.producto?.nombre }}</p>
                </div>

                <div class="space-y-4 p-5">
                    <div
                        v-if="catalogos.tiposAtributo.length === 0"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                    >
                        Primero registra tipos de atributo y valores para poder crear variantes.
                    </div>

                    <div
                        v-for="tipo in catalogos.tiposAtributo"
                        :key="tipo.id"
                    >
                        <BaseSearchSelect
                            v-model.number="modalVariante.atributos[tipo.id]"
                            :items="tipo.atributos ?? []"
                            :label="tipo.nombre"
                            :placeholder="`Seleccionar ${tipo.nombre}`"
                            :label-key="(a) => a.valor"
                            value-key="id"
                            :disabled="!tipo.atributos?.length"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Precio venta</span>
                            <input v-model.number="modalVariante.precio_venta" type="number" min="0" step="0.01" class="field" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">SKU</span>
                            <input v-model.trim="modalVariante.sku" class="field" placeholder="Automatico" />
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="cerrarModalVariante"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="modalVariante.guardando || catalogos.tiposAtributo.length === 0"
                        @click="crearVarianteDesdePedido"
                    >
                        <Loader2 v-if="modalVariante.guardando" class="h-4 w-4 animate-spin" />
                        Crear variante
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { Eye, Loader2, Plus, Search, Trash2, X, XCircle } from "lucide-vue-next";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import http from "@/lib/http";
import { toastError, toastSuccess, toastWarning } from "@/lib/alert";

const props = defineProps({
    modo: { type: String, default: "mixto" },
    tipo: { type: String, default: "" },
});

const pedidos = ref([]);
const cargando = ref(false);
const guardando = ref(false);
const buscar = ref("");
const filtroTipo = ref("");
const filtroEstado = ref("");
const filtroFechaDesde = ref("");
const filtroFechaHasta = ref("");
const cliente = ref(null);
const busquedaProductoKey = ref(0);
const resumenCliente = reactive({ saldo_favor: 0, pedidos_disponibles: [] });
const abonos = reactive({});
let buscarTimer = null;
const borradoresFormulario = reactive({
    pedido: null,
    apartado: null,
});

const catalogos = reactive({ tiposAtributo: [], marcas: [] });

const modalProductoRapido = reactive({
    visible: false,
    guardando: false,
    nombre: "",
    marca_id: "",
    modelo_id: "",
    precio_venta: 0,
    atributos: {},
});

const modalVariante = reactive({
    visible: false,
    guardando: false,
    detalleIndex: null,
    producto: null,
    atributos: {},
    precio_venta: 0,
    sku: "",
});

const modalDetalle = reactive({
    visible: false,
    cargando: false,
    pedido: null,
    data: null,
});

const modalCancelar = reactive({
    visible: false,
    procesando: false,
    pedido: null,
});

const form = reactive({
    tipo: props.tipo || "pedido",
    fecha_promesa: "",
    anticipo: 0,
    forma_pago: "efectivo",
    notas: "",
    detalles: [],
});

const subtotal = computed(() =>
    form.detalles.reduce(
        (total, d) => total + Number(d.cantidad || 0) * Number(d.precio_acordado || 0),
        0
    )
);

const saldoPendiente = computed(() =>
    Math.max(0, subtotal.value - Number(form.anticipo || 0))
);

const mostrarFormulario = computed(() => props.modo !== "consulta");
const mostrarConsulta = computed(() => props.modo !== "nuevo");
const tipoBloqueado = computed(() => ["pedido", "apartado"].includes(props.tipo));
const contenedorClase = computed(() => {
    if (mostrarFormulario.value && mostrarConsulta.value) {
        return 'lg:grid-cols-[420px_minmax(0,1fr)]';
    }

    if (mostrarFormulario.value) {
        return 'max-w-6xl';
    }

    return '';
});
const etiquetaModulo = computed(() => props.tipo === "apartado" ? "Apartados" : "Pedidos");
const tituloVista = computed(() => {
    if (props.modo === "nuevo") return props.tipo === "apartado" ? "Nuevo apartado" : "Nuevo pedido";
    if (props.modo === "consulta") return props.tipo === "apartado" ? "Consulta de apartados" : "Consulta de pedidos";
    return "Control de encargos y reservas";
});
const subtituloConsulta = computed(() =>
    props.tipo === "apartado"
        ? "Reservas hechas sobre inventario existente."
        : "Encargos y pedidos vinculados al catalogo."
);
const modelosProductoRapido = computed(() => {
    const marcaId = Number(modalProductoRapido.marca_id || 0);
    if (!marcaId) return [];
    return catalogos.marcas.find((m) => Number(m.id) === marcaId)?.modelos ?? [];
});

watch(buscar, () => {
    window.clearTimeout(buscarTimer);
    buscarTimer = window.setTimeout(() => cargarPedidos(), 350);
});

onMounted(() => {
    aplicarTipoInicial();
    cargarPedidos();
    cargarCatalogos();
});

watch(
    () => [props.modo, props.tipo],
    ([modoNuevo, tipoNuevo], [modoAnterior, tipoAnterior] = []) => {
        if (modoAnterior === "nuevo" && ["pedido", "apartado"].includes(tipoAnterior)) {
            guardarBorradorFormulario(tipoAnterior);
        }

        aplicarTipoInicial();
        cambiarEstadoModulo(modoNuevo, tipoNuevo);
        cargarPedidos();
    }
);

function cambiarEstadoModulo(modo, tipo) {
    limpiarEstadoVolatilModulo({ limpiarFiltros: modo === "consulta" });

    if (modo === "nuevo" && ["pedido", "apartado"].includes(tipo)) {
        restaurarBorradorFormulario(tipo);
        return;
    }

    if (modo !== "consulta") {
        limpiarFormularioBase(tipo || "pedido");
    }
}

function limpiarEstadoVolatilModulo({ limpiarFiltros = false } = {}) {
    cerrarModalProductoRapido();
    cerrarModalVariante();
    cerrarDetalle();
    modalCancelar.visible = false;
    modalCancelar.pedido = null;
    modalCancelar.procesando = false;

    Object.keys(abonos).forEach((key) => delete abonos[key]);

    if (limpiarFiltros) {
        filtroEstado.value = "";
        filtroFechaDesde.value = "";
        filtroFechaHasta.value = "";
        buscar.value = "";
    }
}

function guardarBorradorFormulario(tipo) {
    if (!["pedido", "apartado"].includes(tipo)) return;

    borradoresFormulario[tipo] = {
        cliente: clonarValor(cliente.value),
        resumen: clonarValor(resumenCliente),
        form: {
            tipo: form.tipo,
            fecha_promesa: form.fecha_promesa,
            anticipo: form.anticipo,
            forma_pago: form.forma_pago,
            notas: form.notas,
            detalles: clonarValor(form.detalles),
        },
    };
}

function clonarValor(valor) {
    return valor == null ? null : JSON.parse(JSON.stringify(valor));
}

function restaurarBorradorFormulario(tipo) {
    const borrador = borradoresFormulario[tipo];
    if (!borrador) {
        limpiarFormularioBase(tipo);
        return;
    }

    cliente.value = borrador.cliente;
    resumenCliente.saldo_favor = Number(borrador.resumen?.saldo_favor ?? 0);
    resumenCliente.pedidos_disponibles = borrador.resumen?.pedidos_disponibles ?? [];

    form.tipo = tipo;
    form.fecha_promesa = borrador.form?.fecha_promesa ?? "";
    form.anticipo = Number(borrador.form?.anticipo ?? 0);
    form.forma_pago = borrador.form?.forma_pago ?? "efectivo";
    form.notas = borrador.form?.notas ?? "";
    form.detalles = Array.isArray(borrador.form?.detalles) && borrador.form.detalles.length
        ? clonarValor(borrador.form.detalles)
        : [];
}

function limpiarFormularioBase(tipo = props.tipo || "pedido") {
    cliente.value = null;
    resumenCliente.saldo_favor = 0;
    resumenCliente.pedidos_disponibles = [];
    form.tipo = tipo || "pedido";
    form.fecha_promesa = "";
    form.anticipo = 0;
    form.forma_pago = "efectivo";
    form.notas = "";
    form.detalles = [];
    busquedaProductoKey.value += 1;
}

function aplicarTipoInicial() {
    if (!tipoBloqueado.value) return;
    form.tipo = props.tipo;
    filtroTipo.value = props.tipo;
}

function nuevoDetalle() {
    return {
        uid: `${Date.now()}-${Math.random()}`,
        selector_id: null,
        producto_id: null,
        variante_id: null,
        descripcion: "",
        marca_texto: "",
        modelo_texto: "",
        color_texto: "",
        talla_texto: "",
        cantidad: 1,
        precio_acordado: 0,
        notas: "",
    };
}

function inicializarAbono(pedidoId) {
    if (!abonos[pedidoId]) {
        abonos[pedidoId] = { monto: "", forma_pago: "efectivo" };
    }
}

function cambiarTipo(tipo) {
    if (tipoBloqueado.value) return;
    form.tipo = tipo;
    form.detalles = [];
    busquedaProductoKey.value += 1;
}

function quitarDetalle(index) {
    form.detalles.splice(index, 1);
}

async function buscarClientes(q) {
    const { data } = await http.get("/api/clientes/buscar", { params: { q } });
    return Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
}

async function cargarCatalogos() {
    try {
        const [atributosResp, marcasResp] = await Promise.all([
            http.get("/api/productos/atributos-empresa"),
            http.get("/api/marcas"),
        ]);

        catalogos.tiposAtributo = Array.isArray(atributosResp.data)
            ? atributosResp.data
            : atributosResp.data?.data ?? [];
        catalogos.marcas = Array.isArray(marcasResp.data)
            ? marcasResp.data
            : marcasResp.data?.data ?? [];
    } catch {
        catalogos.tiposAtributo = [];
        catalogos.marcas = [];
    }
}

async function seleccionarCliente(item) {
    cliente.value = item;
    resumenCliente.saldo_favor = 0;
    resumenCliente.pedidos_disponibles = [];

    if (!item?.id) return;

    try {
        const { data } = await http.get(`/api/clientes/${item.id}/pedidos-resumen`);
        resumenCliente.saldo_favor = Number(data?.saldo_favor ?? 0);
        resumenCliente.pedidos_disponibles = data?.pedidos_disponibles ?? [];
    } catch {
        toastError("No se pudo cargar el saldo del cliente");
    }
}

async function buscarProductosConStock(q) {
    const { data } = await http.get("/api/ventas/buscar-variantes", { params: { q } });
    const items = Array.isArray(data) ? data.filter((it) => !it.sin_stock) : [];
    return items.map((it) => ({
        ...it,
        selector_id: `${it.producto_id}:${it.id ?? "sin-variante"}:${it.serie_id ?? "sin-serie"}`,
    }));
}

async function buscarProductosCatalogo(q) {
    const { data } = await http.get("/api/pedidos/buscar-catalogo", { params: { q } });
    const items = Array.isArray(data) ? data : [];
    return items.map((it) => ({
        ...it,
        selector_id: `${it.tipo_resultado}:${it.producto_id}:${it.id ?? "sin-variante"}`,
        precio_venta: Number(it.precio_venta ?? 0),
    }));
}

function labelProducto(it) {
    const variante = it.nombre_variante ? ` - ${it.nombre_variante}` : "";
    const tipo = it.tipo_resultado === "producto" && it.tiene_variantes ? "Producto base: " : "";
    return `${tipo}${it.nombre || "Producto"}${variante}`;
}

function subLabelProducto(it) {
    const partes = [];
    if (it.codigo) partes.push(it.codigo);
    if (it.sku) partes.push(`SKU ${it.sku}`);
    if (it.tipo_resultado === "producto" && it.tiene_variantes) partes.push("nueva variante");
    if (it.stock != null) partes.push(`Stock ${Number(it.stock ?? 0)}`);
    partes.push(money(it.precio_venta));
    return partes.join(" | ");
}

function detalleImporte(detalle) {
    return Number(detalle?.cantidad || 0) * Number(detalle?.precio_acordado || 0);
}

function agregarProductoDesdeBusqueda(item) {
    if (!item) return;

    const detalle = nuevoDetalle();
    form.detalles.push(detalle);
    seleccionarProducto(form.detalles.length - 1, item);
    busquedaProductoKey.value += 1;
}

function seleccionarProducto(index, item) {
    const detalle = form.detalles[index];
    if (!detalle) return;

    if (!item) {
        desvincularProducto(index);
        return;
    }

    if (form.tipo === "pedido" && item.tipo_resultado === "producto" && item.tiene_variantes) {
        abrirModalVariante(index, item);
        return;
    }

    detalle.selector_id = item.selector_id;
    detalle.producto_id = item.producto_id;
    detalle.variante_id = item.id || null;
    detalle.descripcion = labelProducto(item);
    detalle.precio_acordado = Number(item.precio_venta ?? 0);

    if (item.nombre_variante) {
        const partes = String(item.nombre_variante).split("/").map((p) => p.trim()).filter(Boolean);
        detalle.color_texto = partes[0] ?? detalle.color_texto;
        detalle.talla_texto = partes[1] ?? detalle.talla_texto;
    }
}

function abrirModalProductoRapido() {
    if (form.tipo !== "pedido") {
        toastWarning("Los apartados requieren productos con inventario existente");
        return;
    }

    modalProductoRapido.visible = true;
    modalProductoRapido.guardando = false;
    modalProductoRapido.nombre = "";
    modalProductoRapido.marca_id = "";
    modalProductoRapido.modelo_id = "";
    modalProductoRapido.precio_venta = 0;
    modalProductoRapido.atributos = {};
}

function cerrarModalProductoRapido() {
    modalProductoRapido.visible = false;
    modalProductoRapido.guardando = false;
    modalProductoRapido.nombre = "";
    modalProductoRapido.marca_id = "";
    modalProductoRapido.modelo_id = "";
    modalProductoRapido.precio_venta = 0;
    modalProductoRapido.atributos = {};
}

async function crearProductoRapido() {
    if (!modalProductoRapido.nombre) {
        toastError("Captura el nombre del producto");
        return;
    }

    if (catalogos.tiposAtributo.length === 0) {
        toastError("No hay atributos configurados para crear la variante");
        return;
    }

    const atributos = {};
    for (const tipo of catalogos.tiposAtributo) {
        const valor = modalProductoRapido.atributos[tipo.id];
        if (valor) atributos[tipo.id] = valor;
    }

    if (Object.keys(atributos).length === 0) {
        toastError("Selecciona al menos un atributo de la variante");
        return;
    }

    modalProductoRapido.guardando = true;

    try {
        const marcaSeleccionada = catalogos.marcas.find(
            (m) => Number(m.id) === Number(modalProductoRapido.marca_id)
        );
        const modeloSeleccionado = modelosProductoRapido.value.find(
            (m) => Number(m.id) === Number(modalProductoRapido.modelo_id)
        );

        const { data: productoResp } = await http.post("/api/productos", {
            nombre: modalProductoRapido.nombre,
            marca_id: modalProductoRapido.marca_id || null,
            modelo_id: modalProductoRapido.modelo_id || null,
            descripcion: null,
            precio_costo: 0,
            precio_venta: Number(modalProductoRapido.precio_venta || 0),
            activo: true,
        });

        const producto = productoResp?.data ?? productoResp;

        const { data: varianteResp } = await http.post(`/api/productos/${producto.id}/variantes`, {
            atributos,
            precio_venta: Number(modalProductoRapido.precio_venta || 0),
            activo: true,
        });

        const variante = varianteResp?.data ?? varianteResp;
        const detalle = nuevoDetalle();
        form.detalles.push(detalle);
        const index = form.detalles.length - 1;

        seleccionarProducto(index, {
            tipo_resultado: "variante",
            selector_id: `variante:${producto.id}:${variante.id}`,
            id: variante.id,
            producto_id: producto.id,
            nombre: producto.nombre,
            codigo: producto.codigo,
            sku: variante.sku,
            nombre_variante: variante.nombre_variante,
            precio_venta: variante.precio_venta ?? variante.precio_vigente ?? modalProductoRapido.precio_venta,
        });

        form.detalles[index].marca_texto = marcaSeleccionada?.nombre ?? "";
        form.detalles[index].modelo_texto = modeloSeleccionado?.nombre ?? "";

        toastSuccess("Producto creado y agregado al pedido");
        busquedaProductoKey.value += 1;
        cerrarModalProductoRapido();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo crear el producto");
    } finally {
        modalProductoRapido.guardando = false;
    }
}

function abrirModalVariante(index, producto) {
    modalVariante.visible = true;
    modalVariante.guardando = false;
    modalVariante.detalleIndex = index;
    modalVariante.producto = producto;
    modalVariante.atributos = {};
    modalVariante.precio_venta = Number(producto.precio_venta ?? 0);
    modalVariante.sku = "";
}

function cerrarModalVariante() {
    const index = modalVariante.detalleIndex;
    if (index != null && form.detalles[index] && !form.detalles[index].producto_id) {
        form.detalles.splice(index, 1);
    }

    modalVariante.visible = false;
    modalVariante.guardando = false;
    modalVariante.detalleIndex = null;
    modalVariante.producto = null;
    modalVariante.atributos = {};
    modalVariante.precio_venta = 0;
    modalVariante.sku = "";
}

async function crearVarianteDesdePedido() {
    const producto = modalVariante.producto;
    const index = modalVariante.detalleIndex;

    if (!producto || index == null) return;

    const atributos = {};
    for (const tipo of catalogos.tiposAtributo) {
        const valor = modalVariante.atributos[tipo.id];
        if (valor) atributos[tipo.id] = valor;
    }

    if (Object.keys(atributos).length === 0) {
        toastError("Selecciona los atributos de la variante");
        return;
    }

    modalVariante.guardando = true;
    try {
        const { data } = await http.post(`/api/productos/${producto.producto_id}/variantes`, {
            atributos,
            sku: modalVariante.sku || null,
            precio_venta: Number(modalVariante.precio_venta || 0),
            activo: true,
        });

        const variante = data?.data ?? data;
        seleccionarProducto(index, {
            tipo_resultado: "variante",
            selector_id: `variante:${producto.producto_id}:${variante.id}`,
            id: variante.id,
            producto_id: producto.producto_id,
            nombre: producto.nombre,
            codigo: producto.codigo,
            sku: variante.sku,
            nombre_variante: variante.nombre_variante,
            precio_venta: variante.precio_venta ?? variante.precio_vigente ?? modalVariante.precio_venta,
        });

        toastSuccess("Variante creada y cargada al pedido");
        cerrarModalVariante();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo crear la variante");
    } finally {
        modalVariante.guardando = false;
    }
}

function desvincularProducto(index) {
    const detalle = form.detalles[index];
    if (!detalle) return;
    detalle.selector_id = null;
    detalle.producto_id = null;
    detalle.variante_id = null;
}

async function cargarPedidos() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/pedidos", {
            params: {
                buscar: buscar.value || undefined,
                tipo: props.tipo || filtroTipo.value || undefined,
                estado: filtroEstado.value || undefined,
                fecha_desde: filtroFechaDesde.value || undefined,
                fecha_hasta: filtroFechaHasta.value || undefined,
                por_pagina: 25,
            },
        });
        pedidos.value = data?.data ?? [];
        // Inicializar estado de abono para cada pedido
        pedidos.value.forEach((p) => inicializarAbono(p.id));
    } catch {
        toastError("No se pudieron cargar los pedidos");
    } finally {
        cargando.value = false;
    }
}

async function guardarPedido() {
    if (!cliente.value?.id) {
        toastError("Selecciona un cliente");
        return;
    }

    if (Number(form.anticipo) > subtotal.value) {
        toastError("El anticipo no puede superar el total del pedido");
        return;
    }

    const detalles = form.detalles.map((d) => ({
        producto_id: d.producto_id,
        variante_id: d.variante_id,
        descripcion: d.descripcion,
        marca_texto: d.marca_texto,
        modelo_texto: d.modelo_texto,
        color_texto: d.color_texto,
        talla_texto: d.talla_texto,
        cantidad: Number(d.cantidad || 1),
        precio_acordado: Number(d.precio_acordado || 0),
        notas: d.notas,
    }));

    if (!detalles.length || detalles.some((d) => !d.descripcion || d.cantidad < 1)) {
        toastError("Completa los articulos del pedido");
        return;
    }

    guardando.value = true;
    try {
        await http.post("/api/pedidos", {
            tipo: form.tipo,
            cliente_id: cliente.value.id,
            fecha_promesa: form.fecha_promesa || null,
            anticipo: Number(form.anticipo || 0),
            forma_pago: Number(form.anticipo || 0) > 0 ? form.forma_pago : null,
            notas: form.notas,
            detalles,
        });

        toastSuccess("Pedido registrado");
        limpiarFormulario();
        await cargarPedidos();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo registrar");
    } finally {
        guardando.value = false;
    }
}

async function registrarAbono(pedido) {
    if (pedidoCerrado(pedido)) {
        toastWarning("Este pedido ya esta cerrado");
        return;
    }

    const estado = abonos[pedido.id] || {};
    const monto = Number(estado.monto || 0);
    if (monto <= 0) {
        toastError("Captura un monto de abono");
        return;
    }

    if (monto > Number(pedido.saldo_pendiente)) {
        toastError("El abono supera el saldo pendiente");
        return;
    }

    try {
        await http.post(`/api/pedidos/${pedido.id}/abonos`, {
            monto,
            forma_pago: estado.forma_pago || "efectivo",
        });
        abonos[pedido.id] = { monto: "", forma_pago: "efectivo" };
        toastSuccess("Abono registrado");
        await cargarPedidos();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo registrar el abono");
    }
}

async function abrirDetalle(pedido) {
    modalDetalle.visible = true;
    modalDetalle.cargando = true;
    modalDetalle.pedido = pedido;
    modalDetalle.data = null;

    try {
        const { data } = await http.get(`/api/pedidos/${pedido.id}`);
        modalDetalle.data = data;
    } catch {
        toastError("No se pudo cargar el detalle");
        modalDetalle.visible = false;
    } finally {
        modalDetalle.cargando = false;
    }
}

function cerrarDetalle() {
    modalDetalle.visible = false;
    modalDetalle.pedido = null;
    modalDetalle.data = null;
}

function confirmarCancelacion(pedido) {
    modalCancelar.pedido = pedido;
    modalCancelar.visible = true;
    modalCancelar.procesando = false;
}

async function ejecutarCancelacion() {
    if (!modalCancelar.pedido) return;

    modalCancelar.procesando = true;
    try {
        const { data } = await http.post(`/api/pedidos/${modalCancelar.pedido.id}/cancelar`);
        toastSuccess(data?.message || "Pedido cancelado");
        modalCancelar.visible = false;
        modalCancelar.pedido = null;
        await cargarPedidos();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo cancelar el pedido");
    } finally {
        modalCancelar.procesando = false;
    }
}

function limpiarFormulario() {
    const tipoActual = tipoBloqueado.value ? props.tipo : form.tipo;
    if (["pedido", "apartado"].includes(tipoActual)) {
        borradoresFormulario[tipoActual] = null;
    }

    limpiarFormularioBase(tipoActual || "pedido");
}

// Helpers de fecha/vencimiento

function pedidoVencido(pedido) {
    if (!pedido?.fecha_promesa) return false;
    if (pedidoCerrado(pedido)) return false;
    return new Date(pedido.fecha_promesa) < new Date(new Date().toDateString());
}

function pedidoProximoVencer(pedido) {
    if (!pedido?.fecha_promesa) return false;
    if (pedidoCerrado(pedido)) return false;
    const hoy = new Date(new Date().toDateString());
    const promesa = new Date(pedido.fecha_promesa);
    const diff = (promesa - hoy) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= 3;
}

function diasParaVencer(pedido) {
    if (!pedido?.fecha_promesa) return 0;
    const hoy = new Date(new Date().toDateString());
    const promesa = new Date(pedido.fecha_promesa);
    return Math.ceil((promesa - hoy) / (1000 * 60 * 60 * 24));
}

function diasVencido(pedido) {
    if (!pedido?.fecha_promesa) return 0;
    const hoy = new Date(new Date().toDateString());
    const promesa = new Date(pedido.fecha_promesa);
    return Math.ceil((hoy - promesa) / (1000 * 60 * 60 * 24));
}

function formatFecha(fecha) {
    if (!fecha) return "—";
    return new Date(fecha).toLocaleDateString("es-MX", {
        day: "2-digit", month: "short", year: "numeric",
    });
}

function formatFechaHora(fecha) {
    if (!fecha) return "—";
    return new Date(fecha).toLocaleString("es-MX", {
        day: "2-digit", month: "short", year: "numeric",
        hour: "2-digit", minute: "2-digit",
    });
}

function estadoLabel(estado) {
    return {
        pendiente: "Pendiente",
        en_proceso: "En proceso",
        disponible: "Disponible",
        parcial: "Parcial",
        entregado: "Entregado",
        cancelado: "Cancelado",
        vencido: "Vencido",
    }[estado] || estado;
}

function pedidoCerrado(pedido) {
    return ["entregado", "cancelado"].includes(pedido?.estado);
}

function estadoClass(estado) {
    if (["disponible", "entregado"].includes(estado)) return "bg-emerald-50 text-emerald-700";
    if (["cancelado", "vencido"].includes(estado)) return "bg-red-50 text-red-700";
    if (estado === "parcial") return "bg-amber-50 text-amber-700";
    return "bg-slate-100 text-slate-700";
}

function money(value) {
    return Number(value || 0).toLocaleString("es-MX", {
        style: "currency",
        currency: "MXN",
    });
}
</script>

<style scoped>
.field {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    outline: none;
    background: white;
}

.field-sm {
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
    font-size: 0.8125rem;
}

.field:focus {
    border-color: rgb(16 185 129);
    box-shadow: 0 0 0 4px rgb(209 250 229);
}

.field-sm {
    padding: 0.42rem 0.6rem;
}

.field-xs {
    padding: 0.32rem 0.5rem;
    font-size: 0.78rem;
}
</style>
