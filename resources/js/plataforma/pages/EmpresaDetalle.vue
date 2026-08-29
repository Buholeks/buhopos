<template>
    <div class="space-y-4">
        <!-- Estado de carga -->
        <div
            v-if="cargando"
            class="flex min-h-52 items-center justify-center rounded-xl border border-slate-200 bg-white"
        >
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" />
                Cargando empresa...
            </div>
        </div>

        <!-- Error general -->
        <div
            v-else-if="errorCarga"
            class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3"
        >
            <div class="flex items-start gap-3">
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-rose-800">
                        No se pudo cargar la empresa
                    </p>

                    <p class="mt-0.5 text-xs text-rose-600">
                        {{ errorCarga }}
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
                    @click="cargarTodo"
                >
                    Reintentar
                </button>
            </div>
        </div>

        <template v-else-if="empresa">
            <!-- Encabezado -->
            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-col gap-3 px-4 py-3.5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <RouterLink
                            :to="{ name: 'plataforma-empresas' }"
                            class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 hover:text-emerald-800"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            Empresas
                        </RouterLink>

                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h1 class="truncate text-lg font-semibold text-slate-900">
                                {{ empresa.nombre }}
                            </h1>

                            <EstadoBadge
                                :estado="empresa.suscripcion?.estado"
                            />
                        </div>

                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            Cliente #{{ empresa.id }}
                            <span class="mx-1 text-slate-300">·</span>
                            {{ empresa.correo || "Sin correo registrado" }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="hidden items-center gap-4 rounded-lg bg-slate-50 px-3 py-2 md:flex">
                            <div>
                                <p class="text-[10px] text-slate-400">Plan</p>
                                <p class="text-xs font-medium text-slate-700">
                                    {{ empresa.suscripcion?.plan?.nombre || "Sin plan" }}
                                </p>
                            </div>

                            <div class="h-7 w-px bg-slate-200" />

                            <div>
                                <p class="text-[10px] text-slate-400">Vencimiento</p>
                                <p class="text-xs font-medium text-slate-700">
                                    {{ formatearFecha(empresa.suscripcion?.fecha_vencimiento) }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-lg px-3 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                empresa.activo
                                    ? 'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50'
                                    : 'bg-emerald-600 text-white hover:bg-emerald-700'
                            "
                            :disabled="procesandoEmpresa"
                            @click="cambiarEmpresa"
                        >
                            <Loader2
                                v-if="procesandoEmpresa"
                                class="h-3.5 w-3.5 animate-spin"
                            />

                            <Power v-else class="h-3.5 w-3.5" />

                            {{
                                empresa.activo
                                    ? "Suspender empresa"
                                    : "Reactivar empresa"
                            }}
                        </button>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="grid border-t border-slate-200 sm:grid-cols-2 lg:grid-cols-5">
                    <div
                        v-for="resumen in resumenEmpresa"
                        :key="resumen.label"
                        class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:border-r sm:last:border-r-0 lg:border-b-0"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                            :class="resumen.iconClass"
                        >
                            <component
                                :is="resumen.icon"
                                class="h-4 w-4"
                            />
                        </div>

                        <div class="min-w-0">
                            <p class="text-[10px] text-slate-400">
                                {{ resumen.label }}
                            </p>

                            <p class="truncate text-sm font-medium text-slate-800">
                                {{ resumen.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tabs -->
            <nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1">
                <button
                    v-for="item in tabs"
                    :key="item.label"
                    type="button"
                    class="inline-flex h-9 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium transition"
                    :class="
                        tab === item.label
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'
                    "
                    @click="tab = item.label"
                >
                    <component
                        :is="item.icon"
                        class="h-4 w-4"
                    />

                    {{ item.label }}

                    <span
                        v-if="item.count !== null"
                        class="rounded px-1.5 py-0.5 text-[10px]"
                        :class="
                            tab === item.label
                                ? 'bg-white/10 text-white'
                                : 'bg-slate-100 text-slate-500'
                        "
                    >
                        {{ item.count }}
                    </span>
                </button>
            </nav>

            <!-- SUSCRIPCIÓN -->
            <section
                v-if="tab === 'Suscripción'"
                class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(380px,0.85fr)]"
            >
                <!-- Plan y vigencia -->
                <form
                    class="rounded-xl border border-slate-200 bg-white"
                    @submit.prevent="guardarSuscripcion"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Plan y vigencia
                            </h2>

                            <p class="mt-0.5 text-xs text-slate-500">
                                Configura el servicio contratado por la empresa.
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[10px] font-medium"
                            :class="
                                empresa.suscripcion?.stripe_subscription_id
                                    ? 'bg-violet-50 text-violet-700'
                                    : 'bg-slate-100 text-slate-500'
                            "
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    empresa.suscripcion?.stripe_subscription_id
                                        ? 'bg-violet-500'
                                        : 'bg-slate-400'
                                "
                            />

                            {{
                                empresa.suscripcion?.stripe_subscription_id
                                    ? "Stripe conectado"
                                    : "Sin Stripe"
                            }}
                        </span>
                    </div>

                    <div class="p-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Plan
                                </span>

                                <select
                                    v-model="suscripcion.plan_id"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                >
                                    <option :value="null">Sin plan</option>

                                    <option
                                        v-for="plan in planes"
                                        :key="plan.id"
                                        :value="plan.id"
                                    >
                                        {{ plan.nombre }} —
                                        {{ dinero.format(plan.precio_mensual) }}
                                    </option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">Periodicidad</span>
                                <select v-model="suscripcion.periodicidad" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                    <option value="mensual">Mensual</option>
                                    <option value="anual">Anual</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Estado
                                </span>

                                <select
                                    v-model="suscripcion.estado"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                >
                                    <option
                                        v-for="estado in estados"
                                        :key="estado"
                                        :value="estado"
                                    >
                                        {{ capitalizar(estado) }}
                                    </option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Días de gracia
                                </span>

                                <input
                                    v-model.number="suscripcion.dias_gracia"
                                    type="number"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Fecha de inicio
                                </span>

                                <input
                                    v-model="suscripcion.fecha_inicio"
                                    type="date"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Fecha de vencimiento
                                </span>

                                <input
                                    v-model="suscripcion.fecha_vencimiento"
                                    type="date"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </label>

                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Precio acordado
                                </span>

                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">
                                        $
                                    </span>

                                    <input
                                        v-model.number="suscripcion.precio_acordado"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </div>
                            </label>

                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-xs font-medium text-slate-600">
                                    Notas
                                </span>

                                <textarea
                                    v-model="suscripcion.notas"
                                    rows="3"
                                    placeholder="Información adicional sobre la suscripción"
                                    class="w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </label>
                        </div>

                        <div
                            v-if="mensajeSuscripcion"
                            class="mt-3 rounded-lg border px-3 py-2 text-xs"
                            :class="
                                mensajeSuscripcion.tipo === 'error'
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            "
                        >
                            {{ mensajeSuscripcion.texto }}
                        </div>

                        <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                            <button
                                type="submit"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="guardandoSuscripcion"
                            >
                                <Loader2
                                    v-if="guardandoSuscripcion"
                                    class="h-4 w-4 animate-spin"
                                />

                                <Save v-else class="h-4 w-4" />

                                {{
                                    guardandoSuscripcion
                                        ? "Guardando..."
                                        : "Guardar suscripción"
                                }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="space-y-4">
                    <!-- Stripe -->
                    <section class="rounded-xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <CreditCard class="h-4 w-4 text-violet-600" />

                                <h2 class="text-sm font-semibold text-slate-900">
                                    Cobros con Stripe
                                </h2>
                            </div>

                            <p class="mt-1 text-xs text-slate-500">
                                Crea enlaces de pago o abre el portal de facturación.
                            </p>
                        </div>

                        <div class="p-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-2 rounded-lg bg-violet-600 px-3 text-xs font-medium text-white transition hover:bg-violet-700 disabled:opacity-50"
                                    :disabled="procesandoStripe"
                                    @click="crearCheckout"
                                >
                                    <Loader2
                                        v-if="procesandoStripe === 'checkout'"
                                        class="h-3.5 w-3.5 animate-spin"
                                    />

                                    <Link2 v-else class="h-3.5 w-3.5" />

                                    Crear enlace de pago
                                </button>

                                <button
                                    v-if="empresa.stripe_customer_id"
                                    type="button"
                                    class="inline-flex h-9 items-center gap-2 rounded-lg border border-violet-200 bg-white px-3 text-xs font-medium text-violet-700 transition hover:bg-violet-50 disabled:opacity-50"
                                    :disabled="procesandoStripe"
                                    @click="abrirPortal"
                                >
                                    <Loader2
                                        v-if="procesandoStripe === 'portal'"
                                        class="h-3.5 w-3.5 animate-spin"
                                    />

                                    <ExternalLink v-else class="h-3.5 w-3.5" />

                                    Abrir portal
                                </button>
                            </div>

                            <div
                                v-if="stripeUrl"
                                class="mt-3 rounded-lg border border-violet-100 bg-violet-50/50 p-3"
                            >
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-violet-700">
                                        Enlace generado
                                    </span>

                                    <div class="flex gap-2">
                                        <input
                                            :value="stripeUrl"
                                            readonly
                                            class="h-9 min-w-0 flex-1 rounded-lg border border-violet-200 bg-white px-3 text-xs text-slate-600 outline-none"
                                        />

                                        <button
                                            type="button"
                                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-violet-200 bg-white px-3 text-xs font-medium text-violet-700 hover:bg-violet-50"
                                            @click="copiarStripe"
                                        >
                                            <Copy class="h-3.5 w-3.5" />
                                            Copiar
                                        </button>
                                    </div>
                                </label>
                            </div>

                            <p
                                v-if="mensajeStripe"
                                class="mt-3 text-xs"
                                :class="
                                    mensajeStripe.tipo === 'error'
                                        ? 'text-rose-600'
                                        : 'text-emerald-700'
                                "
                            >
                                {{ mensajeStripe.texto }}
                            </p>
                        </div>
                    </section>

                    <!-- Solicitudes por revisar -->
                    <section v-if="solicitudesRevision.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-semibold text-amber-900">Comprobantes por revisar</h2>
                        <div v-for="solicitud in solicitudesRevision" :key="solicitud.id" class="mt-3 rounded-lg border border-amber-200 bg-white p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div><p class="font-semibold text-slate-800">{{ solicitud.referencia_unica }} · {{ dinero.format(solicitud.importe) }}</p><p class="text-xs text-slate-500">{{ solicitud.metodo }} · {{ solicitud.numero_operacion || 'Sin número de operación' }}</p></div>
                                <div class="flex gap-2">
                                    <a :href="`/api/plataforma/solicitudes-pago/${solicitud.id}/comprobante`" target="_blank" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold">Ver comprobante</a>
                                    <button type="button" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" @click="revisarSolicitud(solicitud, 'rechazar')">Rechazar</button>
                                    <button type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white" @click="revisarSolicitud(solicitud, 'confirmar')">Confirmar</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Pago manual -->
                    <form
                        class="rounded-xl border border-slate-200 bg-white"
                        @submit.prevent="registrarPago"
                    >
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Registrar pago manual
                            </h2>

                            <p class="mt-0.5 text-xs text-slate-500">
                                El pago confirmado activará la suscripción.
                            </p>
                        </div>

                        <div class="p-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">
                                        Importe
                                    </span>

                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">
                                            $
                                        </span>

                                        <input
                                            v-model.number="pago.importe"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                        />
                                    </div>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">
                                        Fecha de pago
                                    </span>

                                    <input
                                        v-model="pago.fecha_pago"
                                        type="date"
                                        required
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">
                                        Periodo inicial
                                    </span>

                                    <input
                                        v-model="pago.periodo_inicio"
                                        type="date"
                                        required
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">
                                        Periodo final
                                    </span>

                                    <input
                                        v-model="pago.periodo_fin"
                                        type="date"
                                        required
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">
                                        Método
                                    </span>

                                    <select
                                        v-model="pago.metodo"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    >
                                        <option value="transferencia">Transferencia</option>
                                        <option value="efectivo">Efectivo</option>
                                        <option value="tarjeta">Tarjeta</option>
                                        <option value="deposito">Depósito</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">
                                        Referencia
                                    </span>

                                    <input
                                        v-model.trim="pago.referencia"
                                        type="text"
                                        placeholder="Opcional"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </label>
                            </div>

                            <p
                                v-if="mensajePago"
                                class="mt-3 text-xs"
                                :class="
                                    mensajePago.tipo === 'error'
                                        ? 'text-rose-600'
                                        : 'text-emerald-700'
                                "
                            >
                                {{ mensajePago.texto }}
                            </p>

                            <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                                <button
                                    type="submit"
                                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="guardandoPago"
                                >
                                    <Loader2
                                        v-if="guardandoPago"
                                        class="h-4 w-4 animate-spin"
                                    />

                                    <CircleCheck v-else class="h-4 w-4" />

                                    {{
                                        guardandoPago
                                            ? "Registrando..."
                                            : "Confirmar pago"
                                    }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Historial -->
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white xl:col-span-2">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Historial de pagos
                            </h2>

                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ empresa.suscripcion?.pagos?.length || 0 }}
                                pagos registrados
                            </p>
                        </div>

                        <ReceiptText class="h-4 w-4 text-slate-400" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Fecha
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Importe
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Método
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Referencia
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Periodo
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="registro in empresa.suscripcion?.pagos"
                                    :key="registro.id"
                                    class="hover:bg-slate-50/70"
                                >
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ formatearFecha(registro.fecha_pago) }}
                                    </td>

                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ dinero.format(registro.importe) }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                            {{ capitalizar(registro.metodo) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-slate-500">
                                        {{ registro.referencia || "Sin referencia" }}
                                    </td>

                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ formatearFecha(registro.periodo_inicio) }}
                                        –
                                        {{ formatearFecha(registro.periodo_fin) }}
                                    </td>
                                </tr>

                                <tr v-if="!empresa.suscripcion?.pagos?.length">
                                    <td
                                        colspan="5"
                                        class="px-4 py-10 text-center text-sm text-slate-400"
                                    >
                                        Sin pagos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <!-- SUCURSALES -->
            <section
                v-if="tab === 'Sucursales'"
                class="space-y-4"
            >
                <form
                    class="rounded-xl border border-slate-200 bg-white"
                    @submit.prevent="guardarSucursal"
                >
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Agregar sucursal
                        </h2>
                    </div>

                    <div class="grid gap-3 p-4 md:grid-cols-[1fr_1.5fr_auto] md:items-end">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600">
                                Nombre
                            </span>

                            <input
                                v-model.trim="nuevaSucursal.nombre"
                                required
                                placeholder="Ej. Sucursal Centro"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600">
                                Dirección
                            </span>

                            <input
                                v-model.trim="nuevaSucursal.direccion"
                                placeholder="Dirección de la sucursal"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>

                        <button
                            type="submit"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            :disabled="guardandoSucursal"
                        >
                            <Loader2
                                v-if="guardandoSucursal"
                                class="h-4 w-4 animate-spin"
                            />

                            <Plus v-else class="h-4 w-4" />

                            Agregar
                        </button>
                    </div>
                </form>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Sucursales
                            </h2>

                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ empresa.sucursales?.length || 0 }} registradas
                            </p>
                        </div>

                        <Store class="h-4 w-4 text-slate-400" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Sucursal
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Dirección
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Estado
                                    </th>

                                    <th class="w-28 px-4 py-2.5 text-right text-xs font-medium text-slate-500">
                                        Acción
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="sucursal in empresa.sucursales"
                                    :key="sucursal.id"
                                    class="hover:bg-slate-50/70"
                                >
                                    <td class="px-4 py-3 font-medium text-slate-800">
                                        {{ sucursal.nombre }}
                                    </td>

                                    <td class="px-4 py-3 text-slate-500">
                                        {{ sucursal.direccion || "Sin dirección" }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-[10px] font-medium"
                                            :class="
                                                sucursal.activo
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-500'
                                            "
                                        >
                                            {{ sucursal.activo ? "Activa" : "Inactiva" }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                            :class="
                                                sucursal.activo
                                                    ? 'text-rose-600 hover:bg-rose-50'
                                                    : 'text-emerald-700 hover:bg-emerald-50'
                                            "
                                            @click="toggleSucursal(sucursal)"
                                        >
                                            {{ sucursal.activo ? "Suspender" : "Activar" }}
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="!empresa.sucursales?.length">
                                    <td
                                        colspan="4"
                                        class="px-4 py-10 text-center text-sm text-slate-400"
                                    >
                                        No hay sucursales registradas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <!-- USUARIOS -->
            <section
                v-if="tab === 'Usuarios'"
                class="space-y-4"
            >
                <form
                    class="rounded-xl border border-slate-200 bg-white"
                    @submit.prevent="guardarUsuario"
                >
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Crear usuario
                        </h2>
                    </div>

                    <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[1fr_1.25fr_1fr_1fr_auto] xl:items-end">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600">
                                Nombre
                            </span>

                            <input
                                v-model.trim="nuevoUsuario.name"
                                required
                                placeholder="Nombre completo"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600">
                                Correo
                            </span>

                            <input
                                v-model.trim="nuevoUsuario.email"
                                required
                                type="email"
                                placeholder="correo@empresa.com"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600">
                                Contraseña
                            </span>

                            <input
                                v-model="nuevoUsuario.password"
                                required
                                type="password"
                                minlength="8"
                                placeholder="Mínimo 8 caracteres"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600">
                                Sucursal
                            </span>

                            <select
                                v-model="nuevoUsuario.sucursal_id"
                                required
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition hover:border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            >
                                <option value="">Selecciona</option>

                                <option
                                    v-for="sucursal in sucursalesActivas"
                                    :key="sucursal.id"
                                    :value="sucursal.id"
                                >
                                    {{ sucursal.nombre }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            :disabled="guardandoUsuario"
                        >
                            <Loader2
                                v-if="guardandoUsuario"
                                class="h-4 w-4 animate-spin"
                            />

                            <UserPlus v-else class="h-4 w-4" />

                            Crear
                        </button>
                    </div>
                </form>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Usuarios
                            </h2>

                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ empresa.usuarios?.length || 0 }} registrados
                            </p>
                        </div>

                        <UsersRound class="h-4 w-4 text-slate-400" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Usuario
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Sucursal
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Nivel
                                    </th>

                                    <th class="px-4 py-2.5 text-xs font-medium text-slate-500">
                                        Estado
                                    </th>

                                    <th class="w-28 px-4 py-2.5 text-right text-xs font-medium text-slate-500">
                                        Acción
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="usuario in empresa.usuarios"
                                    :key="usuario.id"
                                    class="hover:bg-slate-50/70"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800">
                                            {{ usuario.name }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ usuario.email }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-3 text-slate-600">
                                        {{ usuario.sucursal?.nombre || "Sin sucursal" }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                            {{
                                                usuario.es_super_admin
                                                    ? "Superadministrador"
                                                    : "Usuario"
                                            }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-[10px] font-medium"
                                            :class="
                                                usuario.activo
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-500'
                                            "
                                        >
                                            {{ usuario.activo ? "Activo" : "Inactivo" }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                            :class="
                                                usuario.activo
                                                    ? 'text-rose-600 hover:bg-rose-50'
                                                    : 'text-emerald-700 hover:bg-emerald-50'
                                            "
                                            @click="toggleUsuario(usuario)"
                                        >
                                            {{ usuario.activo ? "Desactivar" : "Activar" }}
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="!empresa.usuarios?.length">
                                    <td
                                        colspan="5"
                                        class="px-4 py-10 text-center text-sm text-slate-400"
                                    >
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import {
    ArrowLeft,
    Building2,
    CircleAlert,
    CircleCheck,
    Copy,
    CreditCard,
    ExternalLink,
    Link2,
    Loader2,
    PackageCheck,
    Plus,
    Power,
    ReceiptText,
    Save,
    Store,
    UserPlus,
    UsersRound,
} from "lucide-vue-next";

import http from "@/lib/http";
import { confirm, swal, toastSuccess, error as showError } from "@/lib/alert";
import { generarIdempotencyKey } from "@/lib/idempotency";
import EstadoBadge from "../components/EstadoBadge.vue";

const route = useRoute();

const empresa = ref(null);
const planes = ref([]);
const tab = ref("Suscripción");

const cargando = ref(false);
const errorCarga = ref("");

const guardandoSuscripcion = ref(false);
const guardandoPago = ref(false);
const guardandoSucursal = ref(false);
const guardandoUsuario = ref(false);
const procesandoEmpresa = ref(false);
const procesandoStripe = ref("");

const stripeUrl = ref("");

const mensajeSuscripcion = ref(null);
const mensajePago = ref(null);
const mensajeStripe = ref(null);

const estados = [
    "pendiente",
    "prueba",
    "activa",
    "vencida",
    "suspendida",
    "cancelada",
];

const dinero = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});

const hoy = new Date().toISOString().slice(0, 10);

const suscripcion = reactive({
    plan_id: null,
    periodicidad: "mensual",
    estado: "pendiente",
    fecha_inicio: "",
    fecha_vencimiento: "",
    dias_gracia: 3,
    precio_acordado: null,
    notas: "",
});

const pago = reactive({
    importe: "",
    fecha_pago: hoy,
    periodo_inicio: hoy,
    periodo_fin: "",
    metodo: "transferencia",
    referencia: "",
    estado: "confirmado",
    idempotency_key: generarIdempotencyKey(),
});

const solicitudesRevision = computed(() =>
    empresa.value?.suscripcion?.solicitudes_pago?.filter((item) => item.estado === "en_revision") || [],
);

const nuevaSucursal = reactive({
    nombre: "",
    direccion: "",
});

const nuevoUsuario = reactive({
    name: "",
    email: "",
    password: "",
    sucursal_id: "",
});

const tabs = computed(() => [
    {
        label: "Suscripción",
        icon: CreditCard,
        count: null,
    },
    {
        label: "Sucursales",
        icon: Store,
        count: empresa.value?.sucursales?.length || 0,
    },
    {
        label: "Usuarios",
        icon: UsersRound,
        count: empresa.value?.usuarios?.length || 0,
    },
]);

const sucursalesActivas = computed(() => {
    return empresa.value?.sucursales?.filter((sucursal) => sucursal.activo) || [];
});

const resumenEmpresa = computed(() => [
    {
        label: "Estado",
        value: empresa.value?.activo ? "Activa" : "Suspendida",
        icon: Power,
        iconClass: empresa.value?.activo
            ? "bg-emerald-50 text-emerald-700"
            : "bg-rose-50 text-rose-700",
    },
    {
        label: "Plan",
        value: empresa.value?.suscripcion?.plan?.nombre || "Sin plan",
        icon: PackageCheck,
        iconClass: "bg-violet-50 text-violet-700",
    },
    {
        label: "Sucursales",
        value: empresa.value?.sucursales?.length || 0,
        icon: Store,
        iconClass: "bg-slate-100 text-slate-600",
    },
    {
        label: "Usuarios",
        value: empresa.value?.usuarios?.length || 0,
        icon: UsersRound,
        iconClass: "bg-slate-100 text-slate-600",
    },
    {
        label: "Vencimiento",
        value: formatearFecha(empresa.value?.suscripcion?.fecha_vencimiento),
        icon: Building2,
        iconClass: "bg-amber-50 text-amber-700",
    },
]);

async function cargarTodo() {
    if (cargando.value) {
        return;
    }

    cargando.value = true;
    errorCarga.value = "";

    try {
        const [respuestaPlanes, respuestaEmpresa] = await Promise.all([
            http.get("/api/plataforma/planes"),
            http.get(`/api/plataforma/empresas/${route.params.id}`),
        ]);

        planes.value = respuestaPlanes.data;
        asignarEmpresa(respuestaEmpresa.data);
    } catch (error) {
        errorCarga.value =
            error?.response?.data?.message ||
            "Ocurrió un error al consultar la información.";
    } finally {
        cargando.value = false;
    }
}

async function revisarSolicitud(solicitud, accion) {
    let notas_revision = null;
    if (accion === "rechazar") {
        const resultado = await swal.fire({ title: "Rechazar comprobante", input: "textarea", inputLabel: "Motivo para el cliente", inputValidator: (value) => !value?.trim() ? "Escribe el motivo del rechazo" : undefined, showCancelButton: true, confirmButtonText: "Rechazar" });
        if (!resultado.isConfirmed) return;
        notas_revision = resultado.value.trim();
    } else {
        const ok = await confirm({ title: "¿Confirmar este pago?", text: "La suscripción se activará y el periodo quedará cubierto.", confirmText: "Sí, confirmar", tone: "primary", icon: "question" });
        if (!ok) return;
    }
    try {
        await http.post(`/api/plataforma/solicitudes-pago/${solicitud.id}/revisar`, { accion, notas_revision });
        await cargar();
        toastSuccess(accion === "confirmar" ? "Pago confirmado" : "Comprobante rechazado");
    } catch (error) {
        showError("Error", obtenerMensajeError(error) || "No se pudo revisar la solicitud.");
    }
}

async function cargar() {
    const { data } = await http.get(
        `/api/plataforma/empresas/${route.params.id}`,
    );

    asignarEmpresa(data);
}

function asignarEmpresa(data) {
    empresa.value = data;

    Object.assign(suscripcion, {
        plan_id: null,
        periodicidad: "mensual",
        estado: "pendiente",
        fecha_inicio: "",
        fecha_vencimiento: "",
        dias_gracia: 3,
        precio_acordado: null,
        notas: "",
        ...(empresa.value.suscripcion || {}),
    });
}

async function guardarSuscripcion() {
    if (guardandoSuscripcion.value) {
        return;
    }

    guardandoSuscripcion.value = true;
    mensajeSuscripcion.value = null;

    try {
        await http.put(
            `/api/plataforma/empresas/${empresa.value.id}/suscripcion`,
            suscripcion,
        );

        await cargar();

        mensajeSuscripcion.value = {
            tipo: "success",
            texto: "La suscripción se guardó correctamente.",
        };
    } catch (error) {
        mensajeSuscripcion.value = {
            tipo: "error",
            texto:
                obtenerMensajeError(error) ||
                "No se pudo guardar la suscripción.",
        };
    } finally {
        guardandoSuscripcion.value = false;
    }
}

async function registrarPago() {
    if (guardandoPago.value) {
        return;
    }

    guardandoPago.value = true;
    mensajePago.value = null;

    try {
        await http.post(
            `/api/plataforma/empresas/${empresa.value.id}/pagos`,
            pago,
        );

        Object.assign(pago, {
            importe: "",
            fecha_pago: hoy,
            periodo_inicio: hoy,
            periodo_fin: "",
            metodo: "transferencia",
            referencia: "",
            estado: "confirmado",
            idempotency_key: generarIdempotencyKey(),
        });

        await cargar();

        mensajePago.value = {
            tipo: "success",
            texto: "El pago se registró correctamente.",
        };
    } catch (error) {
        mensajePago.value = {
            tipo: "error",
            texto:
                obtenerMensajeError(error) ||
                "No se pudo registrar el pago.",
        };
    } finally {
        guardandoPago.value = false;
    }
}

async function cambiarEmpresa() {
    if (procesandoEmpresa.value) {
        return;
    }

    procesandoEmpresa.value = true;

    try {
        await http.put(
            `/api/plataforma/empresas/${empresa.value.id}`,
            {
                activo: !empresa.value.activo,
            },
        );

        await cargar();
    } catch (error) {
        window.alert(
            obtenerMensajeError(error) ||
                "No se pudo actualizar el estado de la empresa.",
        );
    } finally {
        procesandoEmpresa.value = false;
    }
}

async function guardarSucursal() {
    if (guardandoSucursal.value) {
        return;
    }

    guardandoSucursal.value = true;

    try {
        await http.post(
            `/api/plataforma/empresas/${empresa.value.id}/sucursales`,
            nuevaSucursal,
        );

        Object.assign(nuevaSucursal, {
            nombre: "",
            direccion: "",
        });

        await cargar();
    } catch (error) {
        window.alert(
            obtenerMensajeError(error) ||
                "No se pudo crear la sucursal.",
        );
    } finally {
        guardandoSucursal.value = false;
    }
}

async function toggleSucursal(sucursal) {
    try {
        await http.put(
            `/api/plataforma/empresas/${empresa.value.id}/sucursales/${sucursal.id}`,
            {
                ...sucursal,
                activo: !sucursal.activo,
            },
        );

        await cargar();
    } catch (error) {
        window.alert(
            obtenerMensajeError(error) ||
                "No se pudo actualizar la sucursal.",
        );
    }
}

async function crearCheckout() {
    if (procesandoStripe.value) {
        return;
    }

    procesandoStripe.value = "checkout";
    mensajeStripe.value = null;

    try {
        await http.put(
            `/api/plataforma/empresas/${empresa.value.id}/suscripcion`,
            suscripcion,
        );

        const { data } = await http.post(
            `/api/plataforma/empresas/${empresa.value.id}/stripe/checkout`,
        );

        stripeUrl.value = data.url;

        await cargar();

        mensajeStripe.value = {
            tipo: "success",
            texto: "El enlace de pago se generó correctamente.",
        };
    } catch (error) {
        mensajeStripe.value = {
            tipo: "error",
            texto:
                obtenerMensajeError(error) ||
                "No se pudo crear el enlace de pago.",
        };
    } finally {
        procesandoStripe.value = "";
    }
}

async function copiarStripe() {
    try {
        await navigator.clipboard.writeText(stripeUrl.value);

        mensajeStripe.value = {
            tipo: "success",
            texto: "El enlace se copió al portapapeles.",
        };
    } catch {
        mensajeStripe.value = {
            tipo: "error",
            texto: "No se pudo copiar el enlace.",
        };
    }
}

async function abrirPortal() {
    if (procesandoStripe.value) {
        return;
    }

    procesandoStripe.value = "portal";
    mensajeStripe.value = null;

    try {
        const { data } = await http.post(
            `/api/plataforma/empresas/${empresa.value.id}/stripe/portal`,
        );

        window.open(data.url, "_blank", "noopener");
    } catch (error) {
        mensajeStripe.value = {
            tipo: "error",
            texto:
                obtenerMensajeError(error) ||
                "No se pudo abrir el portal de facturación.",
        };
    } finally {
        procesandoStripe.value = "";
    }
}

async function guardarUsuario() {
    if (guardandoUsuario.value) {
        return;
    }

    guardandoUsuario.value = true;

    try {
        await http.post(
            `/api/plataforma/empresas/${empresa.value.id}/usuarios`,
            nuevoUsuario,
        );

        Object.assign(nuevoUsuario, {
            name: "",
            email: "",
            password: "",
            sucursal_id: "",
        });

        await cargar();
    } catch (error) {
        window.alert(
            obtenerMensajeError(error) ||
                "No se pudo crear el usuario.",
        );
    } finally {
        guardandoUsuario.value = false;
    }
}

async function toggleUsuario(usuario) {
    try {
        await http.put(
            `/api/plataforma/empresas/${empresa.value.id}/usuarios/${usuario.id}`,
            {
                name: usuario.name,
                email: usuario.email,
                sucursal_id: usuario.sucursal_id,
                activo: !usuario.activo,
                es_super_admin: usuario.es_super_admin,
            },
        );

        await cargar();
    } catch (error) {
        window.alert(
            obtenerMensajeError(error) ||
                "No se pudo actualizar el usuario.",
        );
    }
}

function obtenerMensajeError(error) {
    const errores = Object.values(
        error?.response?.data?.errors || {},
    )
        .flat()
        .join(" ");

    return errores || error?.response?.data?.message || "";
}

function capitalizar(valor) {
    if (!valor) {
        return "";
    }

    return valor.charAt(0).toUpperCase() + valor.slice(1);
}

function formatearFecha(fecha) {
    if (!fecha) {
        return "Sin fecha";
    }

    const valor = new Date(`${fecha}T00:00:00`);

    if (Number.isNaN(valor.getTime())) {
        return fecha;
    }

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(valor);
}

onMounted(cargarTodo);
</script>
