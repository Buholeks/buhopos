<template>
    <main class="mx-auto w-full max-w-7xl space-y-5">
        <!-- Encabezado -->
        <header
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"
                    >
                        <CreditCard class="h-5 w-5" />
                    </div>

                    <div class="min-w-0">
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600"
                        >
                            Cuenta
                        </p>

                        <h1
                            class="mt-0.5 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                        >
                            Plan y facturación
                        </h1>

                        <p class="mt-1 text-sm text-slate-500">
                            Administra el plan, capacidad y método de pago de
                            <span class="font-semibold text-slate-700">
                                {{ datos?.empresa?.nombre || "tu empresa" }}
                            </span>
                            .
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500"
            />
        </header>

        <!-- Cargando -->
        <section
            v-if="cargando"
            class="flex min-h-64 items-center justify-center rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="flex flex-col items-center gap-3 text-center">
                <LoaderCircle class="h-8 w-8 animate-spin text-emerald-600" />

                <div>
                    <p class="text-sm font-semibold text-slate-700">
                        Cargando facturación
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        Consultando tu plan y suscripción…
                    </p>
                </div>
            </div>
        </section>

        <section
            v-else-if="cargaFallida"
            class="flex min-h-64 flex-col items-center justify-center rounded-2xl border border-rose-200 bg-white p-6 text-center shadow-sm"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600"
            >
                <TriangleAlert class="h-6 w-6" />
            </div>
            <h2 class="mt-4 font-bold text-slate-900">
                No pudimos cargar tu facturación
            </h2>
            <p class="mt-1 max-w-md text-sm text-slate-500">
                Revisa tu conexión e inténtalo nuevamente. Tus datos y pagos no
                se modificaron.
            </p>
            <button
                type="button"
                class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                @click="cargar"
            >
                <RefreshCw class="h-4 w-4" />
                Intentar de nuevo
            </button>
        </section>

        <template v-else-if="datos">
            <!-- Resumen -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-emerald-50"
                    />

                    <div
                        class="relative flex items-start justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400"
                            >
                                Plan actual
                            </p>

                            <p
                                class="mt-2 text-xl font-bold tracking-tight text-slate-900"
                            >
                                {{
                                    datos.suscripcion?.plan?.nombre ||
                                    "Sin plan"
                                }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                        >
                            <Layers3 class="h-5 w-5" />
                        </div>
                    </div>

                    <div
                        v-if="datos.suscripcion?.plan_pendiente"
                        class="relative mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5"
                    >
                        <Clock3
                            class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                        />

                        <p class="text-xs font-medium text-amber-800">
                            Cambiará a
                            <span class="font-bold">
                                {{ datos.suscripcion.plan_pendiente.nombre }}
                            </span>
                            el
                            {{ fecha(datos.suscripcion.cambio_plan_en) }}.
                        </p>
                    </div>

                    <div
                        v-else
                        class="relative mt-4 flex items-end justify-between gap-3"
                    >
                        <p class="text-xs text-slate-400">
                            Plan activo para la empresa.
                        </p>
                        <p
                            v-if="datos.suscripcion?.plan"
                            class="text-sm font-bold text-slate-700"
                        >
                            {{ dinero(datos.suscripcion.periodicidad === 'anual' ? datos.suscripcion.plan.precio_anual : datos.suscripcion.plan.precio_mensual)
                            }}<span class="font-medium text-slate-400"
                                >/{{ datos.suscripcion.periodicidad === 'anual' ? 'año' : 'mes' }}</span
                            >
                        </p>
                    </div>
                </article>

                <article
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full"
                        :class="estadoBackgroundClass"
                    />

                    <div
                        class="relative flex items-start justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400"
                            >
                                Estado
                            </p>

                            <p
                                class="mt-2 text-xl font-bold capitalize tracking-tight"
                                :class="colorEstado"
                            >
                                {{ textoEstado }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                            :class="estadoIconClass"
                        >
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                    </div>

                    <div
                        class="relative mt-4 flex items-center gap-2 text-xs text-slate-500"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="estadoDotClass"
                        />
                        {{ descripcionEstado }}
                    </div>
                </article>

                <article
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:col-span-2 xl:col-span-1"
                >
                    <div
                        class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-sky-50"
                    />

                    <div
                        class="relative flex items-start justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400"
                            >
                                Próximo cobro estimado
                            </p>

                            <p
                                class="mt-2 text-xl font-bold tracking-tight text-slate-900"
                            >
                                {{ fecha(proximoCobro) }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"
                        >
                            <CalendarDays class="h-5 w-5" />
                        </div>
                    </div>

                    <div
                        class="relative mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-400"
                                >Renovación del plan pagado</span
                            >
                            <span class="font-semibold text-slate-700">{{
                                fecha(datos.suscripcion?.fecha_vencimiento)
                            }}</span>
                        </div>
                        <div
                            v-if="promocionActiva"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-slate-400"
                                >Acceso promocional hasta</span
                            >
                            <span class="font-semibold text-amber-700">{{
                                fecha(promocionActiva)
                            }}</span>
                        </div>
                    </div>

                    <button
                        v-if="accionPrincipal"
                        type="button"
                        class="relative mt-4 inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 hover:text-emerald-800"
                        @click="irAAccionPrincipal"
                    >
                        {{ accionPrincipal }}
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>
                </article>
            </section>

            <!-- Planes -->
            <section
                v-if="datos.puede_gestionar && datos.planes?.length"
                id="planes-disponibles"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="font-bold text-slate-900">
                            Planes disponibles
                        </h2>

                        <p class="mt-0.5 text-sm text-slate-500">
                            Selecciona el plan que mejor se adapte a tu
                            operación.
                        </p>
                    </div>

                    <div class="flex w-fit rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                        <button type="button" class="rounded-lg px-3 py-1.5 transition" :class="periodicidadSeleccionada === 'mensual' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" @click="periodicidadSeleccionada = 'mensual'">Mensual</button>
                        <button type="button" class="rounded-lg px-3 py-1.5 transition" :class="periodicidadSeleccionada === 'anual' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-500'" @click="periodicidadSeleccionada = 'anual'">Anual · ahorra</button>
                    </div>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="plan in planesDisponibles"
                        :key="plan.id"
                        class="relative flex flex-col overflow-hidden rounded-2xl border p-5 transition"
                        :class="
                            esPlanActual(plan)
                                ? 'border-emerald-400 bg-emerald-50/50 shadow-sm ring-1 ring-emerald-100'
                                : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md'
                        "
                    >
                        <div
                            v-if="esPlanActual(plan) || esPlanRecomendado(plan)"
                            class="absolute right-0 top-0 rounded-bl-xl bg-emerald-600 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white"
                        >
                            {{
                                esPlanActual(plan)
                                    ? "Plan actual"
                                    : "Recomendado"
                            }}
                        </div>

                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    esPlanActual(plan)
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-slate-100 text-slate-600'
                                "
                            >
                                <PackageCheck class="h-5 w-5" />
                            </div>

                            <div class="min-w-0 pr-16">
                                <h3
                                    class="truncate text-base font-bold text-slate-900"
                                >
                                    {{ plan.nombre }}
                                </h3>

                                <p
                                    v-if="plan.descripcion"
                                    class="mt-0.5 line-clamp-2 text-xs text-slate-500"
                                >
                                    {{ plan.descripcion }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="flex items-end gap-1">
                                <p
                                    class="text-3xl font-black tracking-tight text-slate-900"
                                >
                                    {{ dinero(precioPlan(plan)) }}
                                </p>

                                <span
                                    class="pb-1 text-xs font-medium text-slate-400"
                                >
                                    / {{ periodicidadSeleccionada === 'anual' ? 'año' : 'mes' }}
                                </span>
                            </div>
                            <p v-if="periodicidadSeleccionada === 'anual' && ahorroPlan(plan) > 0" class="mt-1 text-xs font-semibold text-emerald-700">
                                Ahorras {{ dinero(ahorroPlan(plan)) }} al año
                            </p>
                        </div>

                        <div
                            class="my-5 h-px bg-slate-100"
                            :class="esPlanActual(plan) ? 'bg-emerald-100' : ''"
                        />

                        <ul class="flex-1 space-y-3">
                            <li
                                class="flex items-center gap-2.5 text-sm text-slate-600"
                            >
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                                >
                                    <Store class="h-3.5 w-3.5" />
                                </div>

                                <span>
                                    {{
                                        limite(
                                            plan.sucursales_incluidas,
                                            "sucursal",
                                            "sucursales",
                                        )
                                    }}
                                </span>
                            </li>

                            <li
                                class="flex items-center gap-2.5 text-sm text-slate-600"
                            >
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                                >
                                    <UsersRound class="h-3.5 w-3.5" />
                                </div>

                                <span>
                                    {{
                                        limite(
                                            plan.usuarios_incluidos,
                                            "usuario",
                                            "usuarios",
                                        )
                                    }}
                                </span>
                            </li>
                        </ul>

                        <button
                            v-if="!esPlanActual(plan)"
                            type="button"
                            :disabled="cambiandoPlanId !== null"
                            class="mt-5 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="cambiarPlan(plan)"
                        >
                            <LoaderCircle
                                v-if="cambiandoPlanId === plan.id"
                                class="h-4 w-4 animate-spin"
                            />
                            <ArrowRight v-else class="h-4 w-4" />

                            {{
                                datos.suscripcion?.plan_id
                                    ? cambiandoPlanId === plan.id
                                        ? "Cambiando…"
                                        : "Cambiar a este plan"
                                    : "Seleccionar plan"
                            }}
                        </button>

                        <div
                            v-else
                            class="mt-5 flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-100/70 text-sm font-bold text-emerald-700"
                        >
                            <CircleCheck class="h-4 w-4" />
                            Plan seleccionado
                        </div>
                    </article>
                </div>

                <div
                    class="flex items-start gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-500"
                >
                    <Info class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />

                    <p>
                        Los aumentos se activan de inmediato con prorrateo. Las
                        reducciones se aplican en el siguiente corte.
                    </p>
                </div>
            </section>

            <!-- Sin permisos -->
            <section
                v-if="!datos.puede_gestionar"
                class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-800"
            >
                <LockKeyhole class="mt-0.5 h-5 w-5 shrink-0" />

                <div>
                    <h2 class="font-bold">Administración restringida</h2>

                    <p class="mt-1 text-sm text-amber-700">
                        Solo el superadministrador puede modificar el plan y la
                        información de facturación.
                    </p>
                </div>
            </section>

            <!-- Pagos no configurados -->
            <section
                v-else-if="!datos.stripe_configurado"
                class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-800"
            >
                <TriangleAlert class="mt-0.5 h-5 w-5 shrink-0" />

                <div>
                    <h2 class="font-bold">Pagos no disponibles</h2>

                    <p class="mt-1 text-sm text-rose-700">
                        El pago con tarjeta todavía no está disponible. Contacta
                        a soporte para habilitarlo.
                    </p>
                </div>
            </section>

            <!-- Pago con tarjeta -->
            <section
                v-else-if="
                    datos.suscripcion?.plan && !datos.suscripcion.plan.es_prueba
                "
                id="pago-con-tarjeta"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"
                        >
                            <WalletCards class="h-5 w-5" />
                        </div>

                        <div>
                            <h2 class="font-bold text-slate-900">
                                Método de pago
                            </h2>

                            <p class="mt-0.5 text-sm text-slate-500">
                                Paga tu suscripción de forma rápida y segura.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="datos.puede_gestionar"
                    class="border-b border-slate-100 bg-slate-50/60 px-5 py-3"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 text-left"
                        :aria-expanded="mostrarCodigoPromocional"
                        aria-controls="formulario-codigo-promocional"
                        @click="
                            mostrarCodigoPromocional = !mostrarCodigoPromocional
                        "
                    >
                        <span
                            class="flex min-w-0 items-center gap-2 text-xm font-semibold text-slate-600"
                        >
                            <TicketPercent
                                class="h-4 w-4 shrink-0 text-amber-600"
                            />
                            ¿Tienes un código promocional?
                        </span>
                        <ChevronDown
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                            :class="
                                mostrarCodigoPromocional ? 'rotate-180' : ''
                            "
                        />
                    </button>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <form
                            v-if="mostrarCodigoPromocional"
                            id="formulario-codigo-promocional"
                            class="mt-3 flex max-w-xl flex-col gap-2 sm:flex-row sm:items-start"
                            @submit.prevent="canjearCodigo"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="relative">
                                    <Tag
                                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        v-model.trim="codigo"
                                        required
                                        maxlength="40"
                                        autocomplete="off"
                                        spellcheck="false"
                                        aria-label="Código promocional"
                                        placeholder="BUHO-XXXXXXXXXX"
                                        class="h-12 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 font-mono text-xs font-semibold uppercase text-slate-900 outline-none transition placeholder:font-normal placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                        @input="errorCodigo = ''"
                                    />
                                </div>
                                <p
                                    v-if="errorCodigo"
                                    class="mt-1 text-xs font-medium text-rose-600"
                                >
                                    {{ errorCodigo }}
                                </p>
                            </div>
                            <button
                                type="submit"
                                :disabled="aplicandoCodigo || !codigo.trim()"
                                class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <LoaderCircle
                                    v-if="aplicandoCodigo"
                                    class="h-3.5 w-3.5 animate-spin"
                                />
                                <BadgeCheck v-else class="h-3.5 w-3.5" />
                                {{ aplicandoCodigo ? "Aplicando…" : "Aplicar" }}
                            </button>
                        </form>
                    </Transition>
                </div>

                <div
                    v-if="datos.cuenta_cobro"
                    class="flex gap-2 border-b border-slate-100 px-5 py-3"
                >
                    <button
                        type="button"
                        :disabled="volviendoTarjeta"
                        class="rounded-lg px-3 py-2 text-xs font-bold transition disabled:opacity-50"
                        :class="
                            metodoPago === 'tarjeta'
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-600'
                        "
                        @click="seleccionarTarjeta"
                    >
                        Tarjeta
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-xs font-bold transition"
                        :class="
                            metodoPago === 'manual'
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-600'
                        "
                        @click="metodoPago = 'manual'"
                    >
                        Transferencia o depósito
                    </button>
                </div>

                <div v-if="metodoPago === 'manual'" class="p-5">
                    <div
                        v-if="!datos.solicitud_pago_manual"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-5"
                    >
                        <div class="flex items-start gap-3">
                            <Banknote class="mt-0.5 h-5 w-5 text-emerald-600" />
                            <div>
                                <p class="font-bold text-slate-800">
                                    Paga mediante tu banco
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    Generaremos una referencia y programaremos
                                    el fin de la renovación automática de
                                    Stripe.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <select
                                v-model="metodoManual"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500"
                            >
                                <option value="transferencia">
                                    Transferencia bancaria
                                </option>
                                <option value="deposito">
                                    Depósito bancario
                                </option>
                            </select>
                            <button
                                type="button"
                                :disabled="creandoSolicitud"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white disabled:opacity-50"
                                @click="crearSolicitudManual"
                            >
                                <LoaderCircle
                                    v-if="creandoSolicitud"
                                    class="h-4 w-4 animate-spin"
                                />
                                Generar instrucciones
                            </button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-5"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <p class="font-bold text-slate-800">
                                    {{
                                        datos.solicitud_pago_manual.estado ===
                                        "en_revision"
                                            ? "Comprobante en revisión"
                                            : "Instrucciones de pago"
                                    }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Referencia
                                    {{
                                        datos.solicitud_pago_manual
                                            .referencia_unica
                                    }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold capitalize text-amber-700"
                                    >{{
                                        datos.solicitud_pago_manual.estado.replace(
                                            "_",
                                            " ",
                                        )
                                    }}</span
                                >
                                <button
                                    type="button"
                                    :disabled="cancelandoSolicitud"
                                    class="rounded-lg px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                                    @click="cancelarSolicitud"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <div
                            class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-bold uppercase text-slate-400"
                                >
                                    Banco
                                </p>
                                <p
                                    class="mt-1 text-sm font-semibold text-slate-800"
                                >
                                    {{
                                        datos.solicitud_pago_manual.cuenta_cobro
                                            .banco
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[11px] font-bold uppercase text-slate-400"
                                >
                                    Beneficiario
                                </p>
                                <p
                                    class="mt-1 text-sm font-semibold text-slate-800"
                                >
                                    {{
                                        datos.solicitud_pago_manual.cuenta_cobro
                                            .beneficiario
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[11px] font-bold uppercase text-slate-400"
                                >
                                    CLABE
                                </p>
                                <button
                                    type="button"
                                    class="mt-1 text-left font-mono text-sm font-semibold text-slate-800 hover:text-emerald-700"
                                    @click="
                                        copiar(
                                            datos.solicitud_pago_manual
                                                .cuenta_cobro.clabe,
                                        )
                                    "
                                >
                                    {{
                                        datos.solicitud_pago_manual.cuenta_cobro
                                            .clabe || "—"
                                    }}
                                    <Copy class="inline h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div>
                                <p
                                    class="text-[11px] font-bold uppercase text-slate-400"
                                >
                                    Cuenta
                                </p>
                                <button
                                    type="button"
                                    class="mt-1 text-left font-mono text-sm font-semibold text-slate-800 hover:text-emerald-700"
                                    @click="
                                        copiar(
                                            datos.solicitud_pago_manual
                                                .cuenta_cobro.numero_cuenta,
                                        )
                                    "
                                >
                                    {{
                                        datos.solicitud_pago_manual.cuenta_cobro
                                            .numero_cuenta || "—"
                                    }}
                                    <Copy class="inline h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div>
                                <p
                                    class="text-[11px] font-bold uppercase text-slate-400"
                                >
                                    Referencia
                                </p>
                                <button
                                    type="button"
                                    class="mt-1 text-left font-mono text-sm font-semibold text-slate-800 hover:text-emerald-700"
                                    @click="
                                        copiar(
                                            datos.solicitud_pago_manual
                                                .referencia_unica,
                                        )
                                    "
                                >
                                    {{
                                        datos.solicitud_pago_manual
                                            .referencia_unica
                                    }}
                                    <Copy class="inline h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div>
                                <p
                                    class="text-[11px] font-bold uppercase text-slate-400"
                                >
                                    Importe exacto
                                </p>
                                <p
                                    class="mt-1 text-sm font-bold text-slate-900"
                                >
                                    {{
                                        dinero(
                                            datos.solicitud_pago_manual.importe,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="
                                datos.solicitud_pago_manual.cuenta_cobro
                                    .instrucciones
                            "
                            class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800"
                        >
                            {{
                                datos.solicitud_pago_manual.cuenta_cobro
                                    .instrucciones
                            }}
                        </p>

                        <p
                            v-if="datos.solicitud_pago_manual.notas_revision"
                            class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700"
                        >
                            {{ datos.solicitud_pago_manual.notas_revision }}
                        </p>

                        <form
                            v-if="
                                datos.solicitud_pago_manual.estado !==
                                'en_revision'
                            "
                            class="grid gap-3 sm:grid-cols-2"
                            @submit.prevent="subirComprobante"
                        >
                            <label
                                class="block text-xs font-semibold text-slate-600"
                                >Fecha del pago<input
                                    v-model="comprobante.fecha_reportada"
                                    type="date"
                                    required
                                    class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
                            /></label>
                            <label
                                class="block text-xs font-semibold text-slate-600"
                                >Número de operación<input
                                    v-model.trim="comprobante.numero_operacion"
                                    class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
                                    placeholder="Opcional"
                            /></label>
                            <label
                                class="block text-xs font-semibold text-slate-600 sm:col-span-2"
                                >Comprobante (PDF, JPG o PNG)<input
                                    ref="inputComprobante"
                                    type="file"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="mt-1 block w-full text-sm text-slate-500"
                            /></label>
                            <button
                                type="submit"
                                :disabled="subiendoComprobante"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white disabled:opacity-50 sm:col-start-2"
                            >
                                <LoaderCircle
                                    v-if="subiendoComprobante"
                                    class="h-4 w-4 animate-spin"
                                />
                                Enviar comprobante
                            </button>
                        </form>
                        <p v-else class="text-sm text-slate-500">
                            Tu suscripción se activará cuando administración
                            confirme el movimiento bancario.
                        </p>
                    </div>
                </div>

                <div
                    v-if="metodoPago === 'tarjeta' && puedeCrearCheckout"
                    class="p-5"
                >
                    <div
                        class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-slate-50 p-5 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm ring-1 ring-slate-200"
                            >
                                <CreditCard class="h-5 w-5" />
                            </div>

                            <div>
                                <p class="font-semibold text-slate-800">
                                    Agrega una tarjeta
                                </p>

                                <p
                                    class="mt-1 max-w-2xl text-sm text-slate-500"
                                >
                                    Registra una tarjeta de crédito o débito
                                    para activar y renovar tu suscripción.
                                </p>

                                <p
                                    class="mt-3 flex items-center gap-1.5 text-xs text-slate-500"
                                >
                                    <ShieldCheck
                                        class="h-4 w-4 text-emerald-600"
                                    />
                                    Procesado de forma segura por Stripe
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="iniciandoPago"
                            class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="montarCheckout"
                        >
                            <LoaderCircle
                                v-if="iniciandoPago"
                                class="h-4 w-4 animate-spin"
                            />
                            <CreditCard v-else class="h-4 w-4" />
                            {{
                                iniciandoPago
                                    ? "Preparando pago…"
                                    : "Pagar con tarjeta"
                            }}
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="metodoPago === 'tarjeta'"
                    class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                        >
                            <CircleCheck class="h-5 w-5" />
                        </div>

                        <div>
                            <p class="font-semibold text-slate-800">
                                Tu tarjeta está lista para las renovaciones
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                Puedes cambiarla aquí sin salir de BuhoPOS.
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="datos.empresa.stripe_customer_id"
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                        @click="abrirEditorTarjeta"
                    >
                        <CreditCard class="h-4 w-4" />
                        Actualizar tarjeta
                    </button>
                </div>
            </section>

            <!-- Historial de pagos -->
            <section
                v-if="datos.suscripcion"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-slate-50"
                    :class="mostrarHistorial ? 'border-b border-slate-100' : ''"
                    :disabled="!datos.suscripcion.pagos?.length"
                    :aria-expanded="mostrarHistorial"
                    @click="mostrarHistorial = !mostrarHistorial"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600"
                    >
                        <ReceiptText class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-bold text-slate-900">
                            Historial de pagos
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{
                                datos.suscripcion.pagos?.length
                                    ? `${datos.suscripcion.pagos.length} movimientos recientes`
                                    : "Aún no hay pagos registrados"
                            }}
                        </p>
                    </div>
                    <ChevronDown
                        v-if="datos.suscripcion.pagos?.length"
                        class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                        :class="mostrarHistorial ? 'rotate-180' : ''"
                    />
                </button>

                <div
                    v-if="datos.suscripcion.pagos?.length && mostrarHistorial"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead
                            class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                        >
                            <tr>
                                <th class="px-5 py-3">Fecha</th>
                                <th class="px-5 py-3">Periodo</th>
                                <th class="px-5 py-3">Referencia</th>
                                <th class="px-5 py-3">Método</th>
                                <th class="px-5 py-3 text-right">Importe</th>
                                <th class="px-5 py-3 text-right">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="pago in datos.suscripcion.pagos"
                                :key="pago.id"
                                class="text-slate-600 hover:bg-slate-50/70"
                            >
                                <td
                                    class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-800"
                                >
                                    {{ fecha(pago.fecha_pago) }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    {{ fechaCorta(pago.periodo_inicio) }} –
                                    {{ fechaCorta(pago.periodo_fin) }}
                                </td>
                                <td
                                    class="max-w-48 truncate px-5 py-3.5 font-mono text-xs"
                                >
                                    {{ pago.referencia || "—" }}
                                </td>
                                <td class="px-5 py-3.5 capitalize">
                                    {{ pago.metodo || "—" }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-5 py-3.5 text-right font-bold text-slate-900"
                                >
                                    {{ dinero(pago.importe) }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold capitalize"
                                        :class="claseEstadoPago(pago.estado)"
                                    >
                                        {{ pago.estado }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="mostrarEditorTarjeta"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="titulo-modal-tarjeta"
                    aria-describedby="descripcion-modal-tarjeta"
                    @click.self="cerrarEditorTarjeta"
                    @keydown.esc="cerrarEditorTarjeta"
                >
                    <form
                        class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6"
                        @submit.prevent="guardarNuevaTarjeta"
                    >
                        <div
                            class="mb-5 flex items-start justify-between gap-4"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                                >
                                    <CreditCard class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3
                                        id="titulo-modal-tarjeta"
                                        class="font-bold text-slate-900"
                                    >
                                        Actualizar tarjeta
                                    </h3>
                                    <p
                                        id="descripcion-modal-tarjeta"
                                        class="mt-0.5 text-sm text-slate-500"
                                    >
                                        La nueva tarjeta se usará en tus
                                        próximas renovaciones.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Cerrar"
                                @click="cerrarEditorTarjeta"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div
                            v-if="cargandoEditorTarjeta"
                            class="flex min-h-36 items-center justify-center gap-2 text-sm text-slate-500"
                        >
                            <LoaderCircle
                                class="h-5 w-5 animate-spin text-emerald-600"
                            />
                            Preparando formulario seguro…
                        </div>
                        <div v-show="!cargandoEditorTarjeta" class="space-y-4">
                            <label class="block">
                                <span
                                    class="mb-1.5 block text-xs font-semibold text-slate-600"
                                    >Nombre del titular</span
                                >
                                <input
                                    ref="inputTitular"
                                    v-model.trim="nombreTitular"
                                    required
                                    maxlength="120"
                                    autocomplete="cc-name"
                                    placeholder="Como aparece en la tarjeta"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                />
                            </label>
                            <label class="block">
                                <span
                                    class="mb-1.5 block text-xs font-semibold text-slate-600"
                                    >Datos de la tarjeta</span
                                >
                                <div
                                    id="stripe-payment-element"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-3.5 transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
                                />
                            </label>
                        </div>

                        <div
                            v-if="!cargandoEditorTarjeta"
                            class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                        >
                            <button
                                type="button"
                                :disabled="guardandoTarjeta"
                                class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                                @click="cerrarEditorTarjeta"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="guardandoTarjeta"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <LoaderCircle
                                    v-if="guardandoTarjeta"
                                    class="h-4 w-4 animate-spin"
                                />
                                <ShieldCheck v-else class="h-4 w-4" />
                                Guardar tarjeta
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="mostrarCheckout"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="titulo-modal-checkout"
                    @click.self="cerrarCheckout"
                    @keydown.esc="cerrarCheckout"
                >
                    <div
                        class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-4"
                        >
                            <div class="flex items-center gap-2">
                                <Lock class="h-4 w-4 text-emerald-600" />

                                <p
                                    id="titulo-modal-checkout"
                                    class="text-sm font-semibold text-slate-700"
                                >
                                    Pago seguro con tarjeta
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Cerrar formulario de pago"
                                @click="cerrarCheckout"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div
                            id="stripe-embedded-checkout"
                            class="min-h-96 overflow-hidden rounded-xl border border-slate-200 bg-white"
                        />
                    </div>
                </div>
            </Transition>
        </Teleport>
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { loadStripe } from "@stripe/stripe-js";
import {
    ArrowRight,
    BadgeCheck,
    Banknote,
    CalendarDays,
    ChevronDown,
    CircleAlert,
    CircleCheck,
    Clock3,
    Copy,
    CreditCard,
    Info,
    Layers3,
    LoaderCircle,
    Lock,
    LockKeyhole,
    PackageCheck,
    ReceiptText,
    RefreshCw,
    ShieldCheck,
    Store,
    Tag,
    TicketPercent,
    TriangleAlert,
    UsersRound,
    WalletCards,
    X,
} from "lucide-vue-next";
import http from "@/lib/http";
import { confirm, error as showError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();

const datos = ref(null);
const cargando = ref(true);
const cargaFallida = ref(false);
const cambiandoPlanId = ref(null);
const periodicidadSeleccionada = ref("mensual");
const aplicandoCodigo = ref(false);
const iniciandoPago = ref(false);
const mostrarCheckout = ref(false);
const mostrarEditorTarjeta = ref(false);
const cargandoEditorTarjeta = ref(false);
const guardandoTarjeta = ref(false);
const nombreTitular = ref("");
const codigo = ref("");
const errorCodigo = ref("");
const mostrarCodigoPromocional = ref(false);
const mostrarHistorial = ref(false);
const inputTitular = ref(null);
const inputComprobante = ref(null);
const metodoPago = ref("tarjeta");
const metodoManual = ref("transferencia");
const creandoSolicitud = ref(false);
const subiendoComprobante = ref(false);
const cancelandoSolicitud = ref(false);
const volviendoTarjeta = ref(false);
const comprobante = ref({
    fecha_reportada: new Date().toISOString().slice(0, 10),
    numero_operacion: "",
});

let checkout = null;
let stripeTarjeta = null;
let elementosTarjeta = null;
let elementoTarjeta = null;
let secretoTarjeta = null;

const promocionActiva = computed(() => {
    const valor = datos.value?.suscripcion?.acceso_promocional_hasta;

    if (!valor) {
        return null;
    }

    const fechaFinal = new Date(`${String(valor).slice(0, 10)}T23:59:59`);

    return fechaFinal >= new Date() ? valor : null;
});

const proximoCobro = computed(() => {
    const fechas = [];
    const renovacion = datos.value?.suscripcion?.fecha_vencimiento;

    if (renovacion) {
        fechas.push(new Date(`${String(renovacion).slice(0, 10)}T00:00:00`));
    }

    if (promocionActiva.value) {
        const finPromocion = new Date(
            `${String(promocionActiva.value).slice(0, 10)}T00:00:00`,
        );
        finPromocion.setDate(finPromocion.getDate() + 1);
        fechas.push(finPromocion);
    }

    if (!fechas.length) return null;

    return fechas
        .sort((a, b) => b.getTime() - a.getTime())[0]
        .toISOString()
        .slice(0, 10);
});

const puedeCrearCheckout = computed(() => {
    const suscripcion = datos.value?.suscripcion;

    return (
        !suscripcion?.stripe_subscription_id ||
        ["cancelada", "pendiente"].includes(suscripcion?.estado)
    );
});

const accionPrincipal = computed(() => {
    if (!datos.value?.puede_gestionar || !datos.value?.stripe_configurado)
        return null;
    if (
        !datos.value?.suscripcion?.plan ||
        datos.value.suscripcion.plan.es_prueba
    )
        return "Elegir un plan";
    if (puedeCrearCheckout.value) return "Completar el pago";
    return "Administrar método de pago";
});

const estadoSuscripcion = computed(
    () => datos.value?.suscripcion?.estado || "sin suscripción",
);

const textoEstado = computed(() => {
    if (estadoSuscripcion.value === "activa" && promocionActiva.value) {
        return "activa con promoción";
    }

    return String(estadoSuscripcion.value).replaceAll("_", " ");
});

const colorEstado = computed(() => {
    if (["activa", "prueba"].includes(estadoSuscripcion.value)) {
        return "text-emerald-700";
    }

    if (
        ["vencida", "suspendida", "cancelada"].includes(estadoSuscripcion.value)
    ) {
        return "text-rose-700";
    }

    return "text-amber-700";
});

const estadoDotClass = computed(() => {
    if (["activa", "prueba"].includes(estadoSuscripcion.value)) {
        return "bg-emerald-500";
    }

    if (
        ["vencida", "suspendida", "cancelada"].includes(estadoSuscripcion.value)
    ) {
        return "bg-rose-500";
    }

    return "bg-amber-500";
});

const estadoIconClass = computed(() => {
    if (["activa", "prueba"].includes(estadoSuscripcion.value)) {
        return "bg-emerald-50 text-emerald-600";
    }

    if (
        ["vencida", "suspendida", "cancelada"].includes(estadoSuscripcion.value)
    ) {
        return "bg-rose-50 text-rose-600";
    }

    return "bg-amber-50 text-amber-600";
});

const estadoBackgroundClass = computed(() => {
    if (["activa", "prueba"].includes(estadoSuscripcion.value)) {
        return "bg-emerald-50";
    }

    if (
        ["vencida", "suspendida", "cancelada"].includes(estadoSuscripcion.value)
    ) {
        return "bg-rose-50";
    }

    return "bg-amber-50";
});

const descripcionEstado = computed(() => {
    const estado = estadoSuscripcion.value;

    if (estado === "activa" && promocionActiva.value) {
        return "Tu plan está activo mediante acceso promocional.";
    }

    const descripciones = {
        activa: "Tu suscripción está operando normalmente.",
        prueba: "Actualmente estás usando un periodo de prueba.",
        pendiente: "Tu suscripción requiere completar el pago.",
        vencida: "El periodo contratado ya terminó.",
        suspendida: "El acceso se encuentra temporalmente suspendido.",
        cancelada: "La suscripción fue cancelada.",
        "sin suscripción": "Todavía no existe una suscripción activa.",
    };

    return (
        descripciones[estado] || "Consulta la información de tu suscripción."
    );
});

function fecha(valor) {
    if (!valor) {
        return "Por definir";
    }

    return new Intl.DateTimeFormat("es-MX", {
        dateStyle: "long",
    }).format(new Date(`${String(valor).slice(0, 10)}T00:00:00`));
}

function fechaCorta(valor) {
    if (!valor) return "—";

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(`${String(valor).slice(0, 10)}T00:00:00`));
}

function dinero(valor) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(valor || 0));
}

function limite(valor, singular, plural) {
    if (valor == null) {
        return `${plural} ilimitados`;
    }

    return `${valor} ${Number(valor) === 1 ? singular : plural}`;
}

function esPlanActual(plan) {
    return plan.id === datos.value?.suscripcion?.plan_id && periodicidadSeleccionada.value === (datos.value?.suscripcion?.periodicidad || "mensual");
}

const planesDisponibles = computed(() =>
    (datos.value?.planes || []).filter((plan) => periodicidadSeleccionada.value === "mensual" || plan.precio_anual !== null),
);

function precioPlan(plan) {
    return periodicidadSeleccionada.value === "anual" ? plan.precio_anual : plan.precio_mensual;
}

function ahorroPlan(plan) {
    return Math.max(0, Number(plan.precio_mensual || 0) * 12 - Number(plan.precio_anual || 0));
}

function esPlanRecomendado(plan) {
    const planes = datos.value?.planes || [];
    return (
        planes.length > 1 &&
        plan.id === planes[Math.floor(planes.length / 2)]?.id
    );
}

function claseEstadoPago(estado) {
    if (["confirmado", "pagado"].includes(estado))
        return "bg-emerald-50 text-emerald-700";
    if (["fallido", "rechazado", "cancelado"].includes(estado))
        return "bg-rose-50 text-rose-700";
    return "bg-amber-50 text-amber-700";
}

function irAAccionPrincipal() {
    const destino =
        datos.value?.suscripcion?.plan &&
        !datos.value.suscripcion.plan.es_prueba
            ? "pago-con-tarjeta"
            : "planes-disponibles";
    document
        .getElementById(destino)
        ?.scrollIntoView({ behavior: "smooth", block: "start" });
}

async function copiar(valor) {
    if (!valor) return;
    await navigator.clipboard.writeText(valor);
    toastSuccess("Copiado al portapapeles");
}

async function crearSolicitudManual() {
    if (creandoSolicitud.value) return;

    creandoSolicitud.value = true;
    try {
        await http.post("/api/facturacion/pago-manual", {
            metodo: metodoManual.value,
        });
        const { data } = await http.get("/api/facturacion");
        datos.value = data;
        metodoPago.value = "manual";
        toastSuccess("Instrucciones de pago generadas");
    } catch (excepcion) {
        fallo(excepcion, "No se pudieron generar las instrucciones de pago.");
    } finally {
        creandoSolicitud.value = false;
    }
}

async function cancelarSolicitud() {
    const aceptado = await confirm({
        title: "¿Cancelar esta solicitud?",
        text: "El comprobante dejará de estar disponible para revisión y podrás generar nuevas instrucciones.",
        confirmText: "Sí, cancelar",
        tone: "danger",
    });
    if (!aceptado) return;
    cancelandoSolicitud.value = true;
    try {
        const id = datos.value.solicitud_pago_manual.id;
        await http.delete(`/api/facturacion/pago-manual/${id}`);
        datos.value.solicitud_pago_manual = null;
        toastSuccess("Solicitud cancelada");
    } catch (excepcion) {
        fallo(excepcion, "No se pudo cancelar la solicitud.");
    } finally {
        cancelandoSolicitud.value = false;
    }
}

async function seleccionarTarjeta() {
    if (datos.value?.suscripcion?.modalidad_cobro !== "manual") {
        metodoPago.value = "tarjeta";
        return;
    }
    const aceptado = await confirm({
        title: "¿Volver al cobro con tarjeta?",
        text: "Se cancelará cualquier solicitud manual abierta y la renovación automática volverá a usar Stripe.",
        confirmText: "Sí, volver a tarjeta",
        tone: "primary",
        icon: "question",
    });
    if (!aceptado) return;
    volviendoTarjeta.value = true;
    try {
        const { data: resultado } = await http.post(
            "/api/facturacion/volver-tarjeta",
        );
        const { data } = await http.get("/api/facturacion");
        datos.value = data;
        metodoPago.value = "tarjeta";
        toastSuccess(resultado.mensaje);
    } catch (excepcion) {
        fallo(excepcion, "No se pudo cambiar el método de renovación.");
    } finally {
        volviendoTarjeta.value = false;
    }
}

async function subirComprobante() {
    const archivo = inputComprobante.value?.files?.[0];
    if (!archivo) return;
    subiendoComprobante.value = true;
    const form = new FormData();
    form.append("comprobante", archivo);
    form.append("fecha_reportada", comprobante.value.fecha_reportada);
    if (comprobante.value.numero_operacion)
        form.append("numero_operacion", comprobante.value.numero_operacion);
    try {
        const id = datos.value.solicitud_pago_manual.id;
        const { data } = await http.post(
            `/api/facturacion/pago-manual/${id}/comprobante`,
            form,
        );
        datos.value.solicitud_pago_manual = data;
        toastSuccess("Comprobante enviado para revisión");
    } catch (excepcion) {
        fallo(excepcion, "No se pudo enviar el comprobante.");
    } finally {
        subiendoComprobante.value = false;
    }
}

function fallo(excepcion, defecto) {
    const errores = excepcion?.response?.data?.errors;

    const detalle = errores
        ? Object.values(errores).flat().join(" ")
        : excepcion?.response?.data?.message || defecto;

    return showError("Error", detalle);
}

async function cargar() {
    cargando.value = true;
    cargaFallida.value = false;

    try {
        const response = await http.get("/api/facturacion");
        datos.value = response.data;
        if (response.data?.suscripcion?.periodicidad) {
            periodicidadSeleccionada.value = response.data.suscripcion.periodicidad;
        }
    } catch (excepcion) {
        datos.value = null;
        cargaFallida.value = true;
        fallo(excepcion, "No se pudo cargar la información de facturación.");
    } finally {
        cargando.value = false;
    }
}

async function cambiarPlan(plan) {
    const actual = datos.value?.suscripcion?.plan;
    const periodicidadActual = datos.value?.suscripcion?.periodicidad || "mensual";
    const precioActual = periodicidadActual === "anual" ? actual?.precio_anual : actual?.precio_mensual;
    const diferencia = Number(precioPlan(plan)) * (periodicidadSeleccionada.value === "mensual" ? 12 : 1)
        - Number(precioActual || 0) * (periodicidadActual === "mensual" ? 12 : 1);
    const esMejora = diferencia > 0;
    const aplicacion = actual
        ? esMejora
            ? "Se aplicará de inmediato y Stripe calculará el prorrateo."
            : `Se aplicará en el siguiente corte (${fecha(datos.value?.suscripcion?.fecha_vencimiento)}).`
        : "Después podrás registrar tu tarjeta para activar la suscripción.";
    const confirmado = await confirm({
        title: actual
            ? "¿Confirmar cambio de plan?"
            : "¿Seleccionar este plan?",
        text: actual
            ? `${actual.nombre} (${dinero(precioActual)}/${periodicidadActual === "anual" ? "año" : "mes"}) → ${plan.nombre} (${dinero(precioPlan(plan))}/${periodicidadSeleccionada.value === "anual" ? "año" : "mes"}). ${aplicacion}`
            : `${plan.nombre} por ${dinero(precioPlan(plan))} al ${periodicidadSeleccionada.value === "anual" ? "año" : "mes"}. ${aplicacion}`,
        confirmText: actual ? "Sí, cambiar plan" : "Sí, seleccionar plan",
        icon: "question",
        tone: esMejora || !actual ? "primary" : "warning",
    });

    if (!confirmado) {
        return;
    }

    cambiandoPlanId.value = plan.id;

    try {
        const { data } = await http.post("/api/facturacion/plan", {
            plan_id: plan.id,
            periodicidad: periodicidadSeleccionada.value,
        });

        toastSuccess(data.mensaje);

        await cargar();
        await auth.fetchUser();
    } catch (excepcion) {
        fallo(excepcion, "No se pudo cambiar el plan.");
    } finally {
        cambiandoPlanId.value = null;
    }
}

async function canjearCodigo() {
    if (!codigo.value.trim()) {
        return;
    }

    aplicandoCodigo.value = true;
    errorCodigo.value = "";

    try {
        const { data } = await http.post(
            "/api/facturacion/codigo-promocional",
            {
                codigo: codigo.value.trim().toUpperCase(),
            },
        );

        codigo.value = "";
        mostrarCodigoPromocional.value = false;

        toastSuccess(
            `${data.mensaje} Acceso disponible hasta el ${fecha(
                data.acceso_promocional_hasta,
            )}.`,
        );

        await cargar();
        await auth.fetchUser();
    } catch (excepcion) {
        const errores = excepcion?.response?.data?.errors;
        errorCodigo.value =
            errores?.codigo?.[0] ||
            excepcion?.response?.data?.message ||
            "No se pudo aplicar el código.";
    } finally {
        aplicandoCodigo.value = false;
    }
}

async function montarCheckout() {
    iniciandoPago.value = true;
    mostrarCheckout.value = true;
    document.body.style.overflow = "hidden";

    try {
        const stripe = await loadStripe(datos.value.stripe_key);

        if (!stripe) {
            throw new Error("No fue posible iniciar el pago seguro.");
        }

        checkout = await stripe.createEmbeddedCheckoutPage({
            fetchClientSecret: async () => {
                const { data } = await http.post("/api/facturacion/checkout");

                return data.client_secret;
            },

            onComplete: async () => {
                checkout?.destroy();
                checkout = null;
                mostrarCheckout.value = false;
                document.body.style.overflow = "";

                await auth.fetchUser();
                await cargar();

                toastSuccess("El pago se procesó correctamente.");
            },
        });

        checkout.mount("#stripe-embedded-checkout");
    } catch (excepcion) {
        checkout?.destroy();
        checkout = null;
        mostrarCheckout.value = false;
        document.body.style.overflow = "";

        fallo(excepcion, "No se pudo cargar el formulario de pago.");
    } finally {
        iniciandoPago.value = false;
    }
}

function cerrarCheckout() {
    checkout?.destroy();
    checkout = null;
    mostrarCheckout.value = false;
    document.body.style.overflow = "";
}

async function abrirEditorTarjeta() {
    if (mostrarEditorTarjeta.value) return;

    nombreTitular.value = auth.user?.name || "";
    mostrarEditorTarjeta.value = true;
    document.body.style.overflow = "hidden";
    cargandoEditorTarjeta.value = true;

    try {
        const [{ data }, stripe] = await Promise.all([
            http.post("/api/facturacion/tarjeta/preparar"),
            loadStripe(datos.value.stripe_key),
        ]);
        if (!stripe)
            throw new Error("No fue posible iniciar el formulario seguro.");

        stripeTarjeta = stripe;
        secretoTarjeta = data.client_secret;
        elementosTarjeta = stripe.elements();
        elementoTarjeta = elementosTarjeta.create("card", {
            disableLink: true,
            hidePostalCode: true,
            style: {
                base: {
                    color: "#0f172a",
                    fontFamily: "ui-sans-serif, system-ui, sans-serif",
                    fontSize: "16px",
                    "::placeholder": { color: "#94a3b8" },
                },
                invalid: { color: "#e11d48" },
            },
        });
        await nextTick();
        inputTitular.value?.focus();
        elementoTarjeta.mount("#stripe-payment-element");
    } catch (excepcion) {
        cerrarEditorTarjeta();
        fallo(
            excepcion,
            "No se pudo cargar el formulario para actualizar la tarjeta.",
        );
    } finally {
        cargandoEditorTarjeta.value = false;
    }
}

async function guardarNuevaTarjeta() {
    if (
        !stripeTarjeta ||
        !elementoTarjeta ||
        !secretoTarjeta ||
        !nombreTitular.value.trim()
    )
        return;

    guardandoTarjeta.value = true;
    try {
        const { error: stripeError, setupIntent } =
            await stripeTarjeta.confirmCardSetup(secretoTarjeta, {
                payment_method: {
                    card: elementoTarjeta,
                    billing_details: { name: nombreTitular.value.trim() },
                },
            });
        if (stripeError) throw stripeError;

        const { data } = await http.post("/api/facturacion/tarjeta", {
            setup_intent_id: setupIntent.id,
        });
        toastSuccess(data.mensaje);
        cerrarEditorTarjeta();
        await cargar();
    } catch (excepcion) {
        showError(
            "Error",
            excepcion?.message || "No se pudo guardar la tarjeta.",
        );
    } finally {
        guardandoTarjeta.value = false;
    }
}

function cerrarEditorTarjeta() {
    elementoTarjeta?.destroy();
    elementoTarjeta = null;
    elementosTarjeta = null;
    stripeTarjeta = null;
    secretoTarjeta = null;
    mostrarEditorTarjeta.value = false;
    cargandoEditorTarjeta.value = false;
    document.body.style.overflow = "";
}

onMounted(cargar);

onBeforeUnmount(() => {
    checkout?.destroy();
    elementoTarjeta?.destroy();
    document.body.style.overflow = "";
});
</script>
