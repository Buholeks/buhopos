<template>
  <main>
    <header>
      <h1 class="text-xl font-semibold text-slate-900">{{ titulo }}</h1>
      <p class="mt-1 text-sm text-slate-500">Movimiento de mercancia entre sucursales.</p>
    </header>

    <section v-if="vista === 'nuevo'" class="space-y-4">
      <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_180px] md:items-end">
            <label class="block">
              <span class="mb-1 block text-sm font-medium text-slate-700">Sucursal destino</span>
              <select
                v-model.number="form.destino_sucursal_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-emerald-500 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              >
                <option value="">Selecciona destino</option>
                <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                  {{ sucursal.nombre }}
                </option>
              </select>
            </label>

            <BaseInput v-model="form.notas" label="Notas" placeholder="Motivo o referencia">
              <template #icon><FileText class="h-4 w-4" /></template>
            </BaseInput>

            <button
              type="button"
              class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
              :disabled="guardando || !puedeGuardar"
              @click="guardarTraspaso"
            >
              <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
              <Send v-else class="h-4 w-4" />
              {{ guardando ? "Guardando..." : "Enviar" }}
            </button>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div ref="buscadorRoot" class="relative">
            <BaseInput
              v-model="buscar"
              label="Buscar mercancia en sucursal actual"
              placeholder="Producto, codigo, SKU o codigo de barras"
              @focus="busquedaAbierta = inventario.length > 0"
              @keydown.down.prevent="moverCursor(1)"
              @keydown.up.prevent="moverCursor(-1)"
              @keydown.enter.prevent="seleccionarCursor"
              @keydown.esc.prevent="cerrarBusqueda"
            >
              <template #icon><Search class="h-4 w-4" /></template>
              <template #suffix>
                <button
                  type="button"
                  class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                  @click="buscarInventario"
                >
                  Buscar
                </button>
              </template>
            </BaseInput>

            <div
              v-if="busquedaAbierta"
              class="absolute z-30 mt-2 max-h-[420px] w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-xl"
            >
              <button
                v-for="item in inventario"
                :key="itemKey(item)"
                type="button"
                class="grid w-full gap-3 border-b border-slate-100 p-3 text-left transition last:border-b-0 sm:grid-cols-[48px_minmax(0,1fr)_150px]"
                :class="inventario[cursorBusqueda] === item ? 'bg-emerald-50' : 'hover:bg-emerald-50'"
                @mouseenter="cursorBusqueda = inventario.indexOf(item)"
                @click="seleccionarItemBusqueda(item)"
              >
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                  <img v-if="item.imagen_url" :src="item.imagen_url" alt="" class="h-full w-full object-cover" />
                  <Package v-else class="h-5 w-5 text-slate-400" />
                </div>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.nombre }}</p>
                    <span
                      v-if="item.tiene_series"
                      class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-100"
                    >
                      Serie/IMEI
                    </span>
                  </div>
                  <p v-if="item.variante_nombre || item.sku" class="mt-0.5 truncate text-xs text-slate-600">
                    {{ item.variante_nombre || item.sku }}
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ item.codigo || "Sin codigo" }}
                    <span v-if="item.codigo_barras"> - {{ item.codigo_barras }}</span>
                  </p>
                </div>
                <div class="text-xs text-slate-600 sm:text-right">
                  <p class="font-semibold text-slate-900">Stock: {{ fmt(item.stock) }}</p>
                  <p>Costo: {{ money(item.precio_costo) }}</p>
                  <p>Venta: {{ money(item.precio_venta) }}</p>
                </div>
              </button>

              <div v-if="buscando" class="p-4 text-center text-sm text-slate-500">Buscando...</div>
              <div v-if="!buscando && !inventario.length" class="p-4 text-center text-sm text-slate-500">
                Sin resultados.
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-900">Mercancia agregada</h2>
            <p class="mt-1 text-xs text-slate-500">{{ carrito.length }} partida(s) listas para enviar</p>
          </div>

          <div class="overflow-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th class="px-3 py-2 text-left">Cant.</th>
                  <th class="px-3 py-2 text-left">Descripcion</th>
                  <th class="px-3 py-2 text-left">Variante</th>
                  <th class="px-3 py-2 text-right">Compra</th>
                  <th class="px-3 py-2 text-right">Venta</th>
                  <th class="px-3 py-2 text-right">Total compra</th>
                  <th class="px-3 py-2 text-right">Total venta</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(item, idx) in carrito" :key="idx">
                  <td class="px-3 py-2">
                    <div class="flex w-32 items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                      <button
                        type="button"
                        class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                        :disabled="item.serie_id || Number(item.cantidad) <= 0.001"
                        @click="ajustarCantidadCarrito(item, -1)"
                      >
                        -
                      </button>
                      <input
                        v-model.number="item.cantidad"
                        type="number"
                        min="0.001"
                        step="0.001"
                        class="h-9 min-w-0 flex-1 border-x border-slate-200 text-center text-sm font-semibold text-slate-900 outline-none"
                        :readonly="!!item.serie_id"
                        @blur="normalizarCantidadCarrito(item)"
                      />
                      <button
                        type="button"
                        class="h-9 w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                        :disabled="!!item.serie_id"
                        @click="ajustarCantidadCarrito(item, 1)"
                      >
                        +
                      </button>
                    </div>
                  </td>
                  <td class="px-3 py-2 text-slate-700">
                    {{ item.nombre }}
                    <span v-if="item.serie_identificador" class="block text-xs text-slate-500">
                      {{ item.serie_identificador }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-slate-600">{{ item.variante_nombre || item.sku || "-" }}</td>
                  <td class="px-3 py-2 text-right text-slate-700">{{ money(item.precio_costo) }}</td>
                  <td class="px-3 py-2 text-right text-slate-700">{{ money(item.precio_venta) }}</td>
                  <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(item.cantidad * item.precio_costo) }}</td>
                  <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(item.cantidad * item.precio_venta) }}</td>
                  <td class="px-3 py-2 text-right">
                    <button type="button" class="rounded-lg p-2 text-rose-600 hover:bg-rose-50" @click="carrito.splice(idx, 1)">
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
                <tr v-if="!carrito.length">
                  <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">Sin partidas agregadas.</td>
                </tr>
              </tbody>
              <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                <tr>
                  <td class="px-3 py-3 font-bold text-slate-900">{{ fmt(totalPiezas) }}</td>
                  <td colspan="4" class="px-3 py-3 text-right font-semibold text-slate-700">Totales</td>
                  <td class="px-3 py-3 text-right font-bold text-slate-900">{{ money(totalCompra) }}</td>
                  <td class="px-3 py-3 text-right font-bold text-slate-900">{{ money(totalVenta) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </section>

    <section v-else class="rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div
        v-if="pendientesPorRecibir > 0"
        class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
      >
        Tienes {{ pendientesPorRecibir }} traspaso(s) pendiente(s) por recibir en esta sucursal.
      </div>

      <div class="space-y-3 border-b border-slate-200 p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_160px_160px_150px] xl:items-end">
        <BaseInput v-model="filtros.buscar" label="Buscar" placeholder="Folio, producto, SKU o IMEI" @keyup.enter="cargarTraspasos">
          <template #icon><Search class="h-4 w-4" /></template>
        </BaseInput>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Estado</span>
          <select v-model="filtros.estado" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
            <option value="">Todos</option>
            <option value="con_pendientes">
              {{ tipoConsulta === "entrada" ? "Por recibir" : "Pendientes de recepcion" }}
            </option>
            <option value="parcial">Parcialmente recibidos</option>
            <option value="recibido">
              {{ tipoConsulta === "entrada" ? "Ya recibidos" : "Recibidos por destino" }}
            </option>
            <option value="rechazado">Rechazados</option>
            <option value="cancelado">Cancelados</option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">
            {{ tipoConsulta === "entrada" ? "Sucursal origen" : "Sucursal destino" }}
          </span>
          <select v-model="filtros.sucursal_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
            <option value="">Todas</option>
            <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
              {{ sucursal.nombre }}
            </option>
          </select>
        </label>

        <BaseInput v-model="filtros.desde" label="Desde" type="date" />
        <BaseInput v-model="filtros.hasta" label="Hasta" type="date" />

        <button
          type="button"
          class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100"
          @click="cargarTraspasos"
        >
          <Search class="h-4 w-4" />
          Consultar
        </button>
      </div>

        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
            @click="limpiarFiltros"
          >
            Limpiar filtros
          </button>
        </div>

      </div>

      <div class="divide-y divide-slate-100">
        <article v-for="t in traspasos" :key="t.id" class="p-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold text-slate-900">{{ t.folio }}</p>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="estadoClass(t.estado)">
                  {{ estadoLabel(t.estado) }}
                </span>
              </div>
              <p class="mt-1 text-xs text-slate-500">
                {{ t.origen?.nombre }} -> {{ t.destino?.nombre }} - {{ fmt(t.total_items) }} pieza(s)
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Envio: <span class="font-medium text-slate-700">{{ t.user?.name || "Sin usuario" }}</span>
                <span v-if="t.receptor"> - Recibio: <span class="font-medium text-slate-700">{{ t.receptor.name }}</span></span>
                <span v-if="t.rechazador"> - Rechazo: <span class="font-medium text-slate-700">{{ t.rechazador.name }}</span></span>
                <span v-if="t.cancelador"> - Cancelo: <span class="font-medium text-slate-700">{{ t.cancelador.name }}</span></span>
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="verDetalle(t.id)">
                Ver detalle
              </button>
              <button v-if="puedeRecibir(t)" type="button" class="rounded-xl border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" @click="recibirTraspaso(t)">
                Recibir todo
              </button>
              <button v-if="puedeRecibir(t)" type="button" class="rounded-xl border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50" @click="rechazarTraspaso(t)">
                Rechazar
              </button>
              <button v-if="puedeCancelar(t)" type="button" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="cancelarTraspaso(t)">
                Cancelar
              </button>
            </div>
          </div>

        </article>

        <div v-if="!traspasos.length" class="p-8 text-center text-sm text-slate-500">
          No hay traspasos con los filtros seleccionados.
        </div>
      </div>
    </section>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="modalItem"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
          @mousedown.self="cerrarModalItem"
        >
          <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
              <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                <img v-if="modalItem.imagen_url" :src="modalItem.imagen_url" alt="" class="h-full w-full object-cover" />
                <Package v-else class="h-5 w-5 text-slate-400" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ modalItem.nombre }}</p>
                <p class="truncate text-xs text-slate-500">{{ modalItem.variante_nombre || modalItem.sku || "Sin variante" }}</p>
              </div>
              <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="cerrarModalItem">
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="space-y-4 px-5 py-5">
              <div class="grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-3 text-xs">
                <div>
                  <p class="text-slate-500">Disponible</p>
                  <p class="font-bold text-slate-900">{{ fmt(stockDisponible(modalItem)) }}</p>
                </div>
                <div>
                  <p class="text-slate-500">Compra</p>
                  <p class="font-bold text-slate-900">{{ money(modalItem.precio_costo) }}</p>
                </div>
                <div>
                  <p class="text-slate-500">Venta</p>
                  <p class="font-bold text-slate-900">{{ money(modalItem.precio_venta) }}</p>
                </div>
              </div>

              <div v-if="modalItem.tiene_series">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Serie/IMEI
                </label>
                <select
                  ref="modalSerieRef"
                  v-model.number="modalSerieId"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  @keydown.enter.prevent="confirmarModalItem"
                >
                  <option value="">Selecciona serie</option>
                  <option v-for="serie in seriesPorItem[itemKey(modalItem)] ?? []" :key="serie.id" :value="serie.id">
                    {{ serie.identificador }}
                  </option>
                </select>
              </div>

              <div v-else>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Cantidad
                </label>
                <input
                  ref="modalCantidadRef"
                  v-model.number="modalCantidad"
                  type="number"
                  min="0.001"
                  step="0.001"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-2xl font-extrabold text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  @keydown.enter.prevent="confirmarModalItem"
                />
              </div>
            </div>

            <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
              <button type="button" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="cerrarModalItem">
                Cancelar
              </button>
              <button
                type="button"
                class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                :disabled="!puedeConfirmarModal"
                @click="confirmarModalItem"
              >
                Agregar
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="detalleAbierto"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-3 sm:p-5"
          @mousedown.self="cerrarDetalle"
        >
          <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-base font-semibold text-slate-900">{{ detalleAbierto.folio }}</h2>
                  <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="estadoClass(detalleAbierto.estado)">
                    {{ estadoLabel(detalleAbierto.estado) }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                  {{ detalleAbierto.origen?.nombre }} -> {{ detalleAbierto.destino?.nombre }} - {{ fmt(detalleAbierto.total_items) }} pieza(s)
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Envio: <span class="font-medium text-slate-700">{{ detalleAbierto.user?.name || "Sin usuario" }}</span>
                  <span v-if="detalleAbierto.receptor"> - Recibio: <span class="font-medium text-slate-700">{{ detalleAbierto.receptor.name }}</span></span>
                  <span v-if="detalleAbierto.rechazador"> - Rechazo: <span class="font-medium text-slate-700">{{ detalleAbierto.rechazador.name }}</span></span>
                  <span v-if="detalleAbierto.cancelador"> - Cancelo: <span class="font-medium text-slate-700">{{ detalleAbierto.cancelador.name }}</span></span>
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <button v-if="puedeRecibir(detalleAbierto)" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="seleccionarPendientes(true)">
                  Seleccionar pendientes
                </button>
                <button v-if="puedeRecibir(detalleAbierto)" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="seleccionarPendientes(false)">
                  Limpiar
                </button>
                <button
                  v-if="puedeRecibir(detalleAbierto)"
                  type="button"
                  class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                  :disabled="!detalleIdsSeleccionados.length || operando === detalleAbierto.id"
                  @click="recibirSeleccionados(detalleAbierto)"
                >
                  Recibir seleccionados
                </button>
                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="cerrarDetalle">
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div class="grid gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-4">
              <div>
                <p class="text-xs text-slate-500">Partidas</p>
                <p class="font-bold text-slate-900">{{ fmt(detalleAbierto.detalles?.length || 0) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Pendientes</p>
                <p class="font-bold text-slate-900">{{ fmt(detallePendientes) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Valor compra</p>
                <p class="font-bold text-slate-900">{{ money(totalDetalleCompra) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Valor venta</p>
                <p class="font-bold text-slate-900">{{ money(totalDetalleVenta) }}</p>
              </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto">
              <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="sticky top-0 z-10 bg-white text-xs uppercase text-slate-500 shadow-sm">
                  <tr>
                    <th v-if="puedeRecibir(detalleAbierto)" class="w-10 px-3 py-2 text-left"></th>
                    <th class="px-3 py-2 text-left">Estado</th>
                    <th class="px-3 py-2 text-left">Cant.</th>
                    <th class="px-3 py-2 text-left">Descripcion</th>
                    <th class="px-3 py-2 text-left">Variante</th>
                    <th class="px-3 py-2 text-right">Compra</th>
                    <th class="px-3 py-2 text-right">Venta</th>
                    <th class="px-3 py-2 text-right">Total compra</th>
                    <th class="px-3 py-2 text-right">Total venta</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                  <tr v-for="d in detalleAbierto.detalles" :key="d.id" :class="d.estado === 'recibido' ? 'bg-emerald-50/60' : ''">
                    <td v-if="puedeRecibir(detalleAbierto)" class="px-3 py-2">
                      <input
                        v-model="seleccionRecepcion[d.id]"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        :disabled="d.estado !== 'pendiente'"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="detalleEstadoClass(d.estado)">
                        {{ detalleEstadoLabel(d.estado) }}
                      </span>
                    </td>
                    <td class="px-3 py-2 font-semibold text-slate-900">{{ fmt(d.cantidad) }}</td>
                    <td class="px-3 py-2 text-slate-700">
                      {{ d.producto_nombre }}
                      <span v-if="d.serie_identificador" class="block text-xs text-slate-500">{{ d.serie_identificador }}</span>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ d.variante_nombre || "-" }}</td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ money(d.precio_costo) }}</td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ money(d.precio_venta) }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(d.cantidad * d.precio_costo) }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ money(d.cantidad * d.precio_venta) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import { FileText, Loader2, Package, Search, Send, Trash2, X } from "lucide-vue-next";

const props = defineProps({
  modo: { type: String, default: "nuevo" },
  tipo: { type: String, default: "" },
});

const router = useRouter();
const auth = useAuthStore();
const vista = ref(props.modo === "consulta" ? "consulta" : "nuevo");
const sucursales = ref([]);
const inventario = ref([]);
const buscar = ref("");
const buscadorRoot = ref(null);
const buscando = ref(false);
const busquedaAbierta = ref(false);
const cursorBusqueda = ref(0);
const modalItem = ref(null);
const modalCantidad = ref(1);
const modalSerieId = ref("");
const modalCantidadRef = ref(null);
const modalSerieRef = ref(null);
const seriesPorItem = reactive({});
const carrito = ref([]);
const guardando = ref(false);
const traspasos = ref([]);
const detalleAbierto = ref(null);
const seleccionRecepcion = reactive({});
const pendientesPorRecibir = ref(0);
const operando = ref(null);
let timerBusqueda = null;

const form = reactive({ destino_sucursal_id: "", notas: "" });
const filtros = reactive({
  buscar: "",
  estado: "",
  sucursal_id: "",
  desde: "",
  hasta: "",
});

const puedeGuardar = computed(() => !!form.destino_sucursal_id && carrito.value.length > 0);
const tipoConsulta = computed(() => (props.tipo === "salida" ? "salida" : "entrada"));
const titulo = computed(() => {
  if (vista.value === "nuevo") return "Nuevo traspaso";
  return tipoConsulta.value === "salida" ? "Traspasos de salida" : "Traspasos de entrada";
});
const totalPiezas = computed(() => carrito.value.reduce((sum, item) => sum + Number(item.cantidad || 0), 0));
const totalCompra = computed(() => carrito.value.reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_costo || 0), 0));
const totalVenta = computed(() => carrito.value.reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_venta || 0), 0));
const puedeConfirmarModal = computed(() => {
  if (!modalItem.value) return false;
  if (modalItem.value.tiene_series) return !!modalSerieId.value;
  return Number(modalCantidad.value) > 0 && Number(modalCantidad.value) <= stockDisponible(modalItem.value);
});
const detalleIdsSeleccionados = computed(() =>
  Object.entries(seleccionRecepcion)
    .filter(([, selected]) => selected)
    .map(([id]) => Number(id))
);
const totalDetalleCompra = computed(() =>
  (detalleAbierto.value?.detalles ?? []).reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_costo || 0), 0)
);
const totalDetalleVenta = computed(() =>
  (detalleAbierto.value?.detalles ?? []).reduce((sum, item) => sum + Number(item.cantidad || 0) * Number(item.precio_venta || 0), 0)
);
const detallePendientes = computed(() =>
  (detalleAbierto.value?.detalles ?? []).reduce((sum, item) => sum + (item.estado === "pendiente" ? Number(item.cantidad || 0) : 0), 0)
);

watch(
  () => props.modo,
  async (modo) => {
    vista.value = modo === "consulta" ? "consulta" : "nuevo";
    detalleAbierto.value = null;
    await cargarSucursales();
    if (vista.value === "consulta") await cargarTraspasos();
  }
);

watch(
  () => props.tipo,
  async () => {
    detalleAbierto.value = null;
    if (vista.value === "consulta") await cargarTraspasos();
  }
);

watch(buscar, (value) => {
  clearTimeout(timerBusqueda);
  if (!String(value || "").trim()) {
    inventario.value = [];
    busquedaAbierta.value = false;
    return;
  }
  timerBusqueda = setTimeout(() => buscarInventario(), 260);
});

onMounted(async () => {
  document.addEventListener("click", cerrarBusquedaExterna);
  aplicarMesActual();
  await cargarSucursales();
  await cargarPendientes();
  if (vista.value === "consulta") await cargarTraspasos();
});

onBeforeUnmount(() => {
  document.removeEventListener("click", cerrarBusquedaExterna);
});

function itemKey(item) {
  return `${item.producto_id}:${item.variante_id ?? "null"}`;
}

function fmt(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { maximumFractionDigits: 3 });
}

function money(value) {
  return Number(value ?? 0).toLocaleString("es-MX", { style: "currency", currency: "MXN" });
}

function estadoLabel(estado) {
  return { pendiente: "Pendiente", recibido: "Recibido", rechazado: "Rechazado", cancelado: "Cancelado" }[estado] ?? estado;
}

function estadoClass(estado) {
  return {
    pendiente: "bg-amber-50 text-amber-700 ring-amber-100",
    recibido: "bg-emerald-50 text-emerald-700 ring-emerald-100",
    rechazado: "bg-slate-100 text-slate-700 ring-slate-200",
    cancelado: "bg-rose-50 text-rose-700 ring-rose-100",
  }[estado] ?? "bg-slate-100 text-slate-700 ring-slate-200";
}

function detalleEstadoLabel(estado) {
  return { pendiente: "Pendiente", recibido: "Recibido", rechazado: "Rechazado" }[estado] ?? estado;
}

function detalleEstadoClass(estado) {
  return {
    pendiente: "bg-amber-50 text-amber-700 ring-amber-100",
    recibido: "bg-emerald-50 text-emerald-700 ring-emerald-100",
    rechazado: "bg-slate-100 text-slate-700 ring-slate-200",
  }[estado] ?? "bg-slate-100 text-slate-700 ring-slate-200";
}

async function cargarSucursales() {
  const { data } = await http.get("/api/traspasos/sucursales", {
    params: { solo_destino: vista.value === "nuevo" ? 1 : undefined },
  });
  sucursales.value = data;
}

async function cargarPendientes() {
  const { data } = await http.get("/api/traspasos/resumen-pendientes");
  pendientesPorRecibir.value = Number(data.por_recibir ?? 0);
}

async function buscarInventario() {
  buscando.value = true;
  try {
    const { data } = await http.get("/api/traspasos/inventario", { params: { buscar: buscar.value } });
    inventario.value = data;
    cursorBusqueda.value = 0;
    busquedaAbierta.value = true;
  } finally {
    buscando.value = false;
  }
}

function moverCursor(delta) {
  if (!busquedaAbierta.value || !inventario.value.length) return;
  const total = inventario.value.length;
  cursorBusqueda.value = (cursorBusqueda.value + delta + total) % total;
}

function seleccionarCursor() {
  if (!busquedaAbierta.value || !inventario.value.length) {
    buscarInventario();
    return;
  }

  seleccionarItemBusqueda(inventario.value[cursorBusqueda.value]);
}

async function seleccionarItemBusqueda(item) {
  if (!item) return;
  busquedaAbierta.value = false;
  buscar.value = "";
  await abrirModalItem(item);
}

function cerrarBusqueda() {
  busquedaAbierta.value = false;
}

function cerrarBusquedaExterna(e) {
  if (!busquedaAbierta.value || !buscadorRoot.value) return;
  if (!buscadorRoot.value.contains(e.target)) cerrarBusqueda();
}

async function abrirModalItem(item) {
  modalItem.value = item;
  modalCantidad.value = Math.min(1, stockDisponible(item));
  modalSerieId.value = "";
  if (item.tiene_series) await cargarSeries(item);

  await nextTick();
  if (item.tiene_series) {
    modalSerieRef.value?.focus();
  } else {
    modalCantidadRef.value?.select();
  }
}

function cerrarModalItem() {
  modalItem.value = null;
  modalCantidad.value = 1;
  modalSerieId.value = "";
}

function confirmarModalItem() {
  if (!puedeConfirmarModal.value || !modalItem.value) return;
  agregarItem(modalItem.value, {
    cantidad: Number(modalCantidad.value),
    serieId: modalSerieId.value ? Number(modalSerieId.value) : null,
  });
  cerrarModalItem();
}

async function cargarSeries(item) {
  const key = itemKey(item);
  const { data } = await http.get("/api/traspasos/series-disponibles", {
    params: { producto_id: item.producto_id, variante_id: item.variante_id },
  });
  seriesPorItem[key] = data.filter((serie) => !carrito.value.some((row) => Number(row.serie_id) === Number(serie.id)));
}

function stockDisponible(item) {
  const usado = carrito.value
    .filter((row) => row.producto_id === item.producto_id && row.variante_id === item.variante_id)
    .reduce((sum, row) => sum + Number(row.cantidad || 0), 0);
  return Math.max(0, Number(item.stock || 0) - usado);
}

function agregarItem(item, opciones = {}) {
  const key = itemKey(item);

  if (item.tiene_series) {
    const serieId = Number(opciones.serieId);
    const serie = (seriesPorItem[key] ?? []).find((s) => Number(s.id) === serieId);
    if (!serie || carrito.value.some((row) => Number(row.serie_id) === serieId)) return;

    carrito.value.push(rowDesdeItem(item, 1, serie.id, serie.identificador));
    cargarSeries(item);
    return;
  }

  const cantidad = Number(opciones.cantidad ?? 1);
  if (cantidad <= 0 || cantidad > stockDisponible(item)) return;

  const existente = carrito.value.find((row) => !row.serie_id && row.producto_id === item.producto_id && row.variante_id === item.variante_id);
  if (existente) {
    existente.cantidad = Number(existente.cantidad) + cantidad;
  } else {
    carrito.value.push(rowDesdeItem(item, cantidad));
  }
}

function ajustarCantidadCarrito(item, delta) {
  const siguiente = Number(item.cantidad || 0) + delta;
  item.cantidad = Math.max(0.001, Math.min(stockTotalItem(item), siguiente));
}

function normalizarCantidadCarrito(item) {
  const cantidad = Number(item.cantidad || 0);
  item.cantidad = Math.max(0.001, Math.min(stockTotalItem(item), cantidad || 0.001));
}

function stockTotalItem(item) {
  const base = inventario.value.find((row) => row.producto_id === item.producto_id && row.variante_id === item.variante_id);
  return Number(base?.stock ?? item.stock ?? item.cantidad ?? 0);
}

function rowDesdeItem(item, cantidad, serieId = null, serieIdentificador = null) {
  return {
    producto_id: item.producto_id,
    variante_id: item.variante_id,
    cantidad,
    serie_id: serieId,
    serie_identificador: serieIdentificador,
    nombre: item.nombre,
    variante_nombre: item.variante_nombre,
    sku: item.sku,
    stock: Number(item.stock || 0),
    precio_costo: Number(item.precio_costo || 0),
    precio_venta: Number(item.precio_venta || 0),
  };
}

async function guardarTraspaso() {
  guardando.value = true;
  try {
    await http.post("/api/traspasos", {
      destino_sucursal_id: form.destino_sucursal_id,
      notas: form.notas,
      detalles: carrito.value.map((item) => ({
        producto_id: item.producto_id,
        variante_id: item.variante_id,
        cantidad: item.cantidad,
        serie_id: item.serie_id,
      })),
    });

    toastSuccess("Traspaso enviado. Queda pendiente de recepcion.");
    carrito.value = [];
    form.notas = "";
    await buscarInventario();
    router.push({ name: "traspasos-salida" });
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo registrar el traspaso.");
  } finally {
    guardando.value = false;
  }
}

async function cargarTraspasos() {
  const { data } = await http.get("/api/traspasos", {
    params: {
      buscar: filtros.buscar || undefined,
      estado: filtros.estado || undefined,
      sucursal_id: filtros.sucursal_id || undefined,
      tipo: tipoConsulta.value,
      desde: filtros.desde || undefined,
      hasta: filtros.hasta || undefined,
      per_page: 30,
    },
  });
  traspasos.value = data.data ?? [];
  await cargarPendientes();
}

function limpiarFiltros() {
  Object.assign(filtros, {
    buscar: "",
    estado: "",
    sucursal_id: "",
  });
  aplicarMesActual();
  cargarTraspasos();
}

function aplicarMesActual() {
  const hoy = new Date();
  const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
  const fin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
  filtros.desde = fechaInput(inicio);
  filtros.hasta = fechaInput(fin);
}

function fechaInput(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

async function verDetalle(id) {
  const { data } = await http.get(`/api/traspasos/${id}`);
  detalleAbierto.value = data;
  resetSeleccionRecepcion();
}

function cerrarDetalle() {
  detalleAbierto.value = null;
  resetSeleccionRecepcion();
}

function resetSeleccionRecepcion() {
  Object.keys(seleccionRecepcion).forEach((key) => delete seleccionRecepcion[key]);
  (detalleAbierto.value?.detalles ?? []).forEach((detalle) => {
    seleccionRecepcion[detalle.id] = false;
  });
}

function seleccionarPendientes(valor) {
  (detalleAbierto.value?.detalles ?? []).forEach((detalle) => {
    if (detalle.estado === "pendiente") seleccionRecepcion[detalle.id] = valor;
  });
}

function puedeRecibir(traspaso) {
  return traspaso.estado === "pendiente" && tipoConsulta.value === "entrada" && Number(traspaso.destino_sucursal_id) === Number(auth.sucursalId);
}

function puedeCancelar(traspaso) {
  return traspaso.estado === "pendiente" && tipoConsulta.value === "salida" && Number(traspaso.origen_sucursal_id) === Number(auth.sucursalId);
}

async function recibirTraspaso(traspaso) {
  try {
    operando.value = traspaso.id;
    await http.post(`/api/traspasos/${traspaso.id}/recibir`);
    toastSuccess("Traspaso recibido. Stock aplicado.");
    cerrarDetalle();
    await cargarTraspasos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo recibir el traspaso.");
  } finally {
    operando.value = null;
  }
}

async function recibirSeleccionados(traspaso) {
  try {
    operando.value = traspaso.id;
    await http.post(`/api/traspasos/${traspaso.id}/recibir`, { detalle_ids: detalleIdsSeleccionados.value });
    toastSuccess("Partidas recibidas. Stock aplicado.");
    await cargarTraspasos();
    if (detalleAbierto.value?.id === traspaso.id) {
      await verDetalle(traspaso.id);
    }
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo recibir la seleccion.");
  } finally {
    operando.value = null;
  }
}

async function rechazarTraspaso(traspaso) {
  const motivo = window.prompt(`Motivo de rechazo para ${traspaso.folio}`);
  if (motivo === null) return;

  try {
    await http.post(`/api/traspasos/${traspaso.id}/rechazar`, { motivo_rechazo: motivo });
    toastSuccess("Traspaso rechazado. Stock devuelto a origen.");
    cerrarDetalle();
    await cargarTraspasos();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo rechazar el traspaso.");
  }
}

async function cancelarTraspaso(traspaso) {
  const motivo = window.prompt(`Motivo de cancelacion para ${traspaso.folio}`);
  if (motivo === null) return;

  try {
    await http.post(`/api/traspasos/${traspaso.id}/cancelar`, { motivo_cancelacion: motivo });
    toastSuccess("Traspaso cancelado. Stock devuelto a origen.");
    cerrarDetalle();
    await cargarTraspasos();
    if (vista.value === "nuevo") await buscarInventario();
  } catch (e) {
    toastError(e?.response?.data?.message || "No se pudo cancelar el traspaso.");
  }
}
</script>
