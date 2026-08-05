<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex justify-end bg-slate-950/45"
            @click.self="$emit('close')"
        >
            <section
                class="flex h-full w-full max-w-5xl flex-col bg-slate-50 shadow-2xl"
            >
                <header
                    class="flex items-center justify-between border-b bg-white px-5 py-4"
                >
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            Estado de cuenta
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ proveedor?.nombre_comercial }} · sucursal activa
                        </p>
                    </div>
                    <button
                        class="rounded-xl p-2 hover:bg-slate-100"
                        @click="$emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <main class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div v-if="loading" class="flex justify-center py-16">
                        <Loader2
                            class="h-7 w-7 animate-spin text-emerald-600"
                        />
                    </div>
                    <template v-else>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <article
                                v-for="card in cards"
                                :key="card.label"
                                class="rounded-2xl border bg-white p-4 shadow-sm"
                            >
                                <p
                                    class="text-xs font-semibold uppercase text-slate-500"
                                >
                                    {{ card.label }}
                                </p>
                                <p
                                    class="mt-2 text-xl font-black"
                                    :class="card.class"
                                >
                                    {{ card.value }}
                                </p>
                            </article>
                        </div>

                        <section
                            class="rounded-2xl border bg-white p-4 shadow-sm"
                        >
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                            >
                                <div
                                    class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-3"
                                >
                                    <BaseInput
                                        v-model="filtros.desde"
                                        label="Desde"
                                        type="date"
                                    />
                                    <BaseInput
                                        v-model="filtros.hasta"
                                        label="Hasta"
                                        type="date"
                                    />
                                    <BaseSearchSelect
                                        v-model="filtros.tipo"
                                        label="Tipo"
                                        placeholder="Todos los movimientos"
                                        :items="tiposMovimiento"
                                        label-key="nombre"
                                        value-key="id"
                                    />
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="rounded-xl border px-4 py-2 text-sm font-semibold"
                                        @click="cargar()"
                                    >
                                        Filtrar
                                    </button>
                                    <button
                                        v-if="puedeAjustar"
                                        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"
                                        @click="mostrarAjuste = !mostrarAjuste"
                                    >
                                        Nuevo ajuste
                                    </button>
                                </div>
                            </div>
                            <form
                                v-if="mostrarAjuste"
                                class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-4"
                                @submit.prevent="ajustar"
                            >
                                <BaseSearchSelect
                                    v-model="ajuste.naturaleza"
                                    label="Operación"
                                    placeholder="Selecciona la Operación"
                                    :items="naturalezasAjuste"
                                    label-key="nombre"
                                    value-key="id"
                                    required
                                />
                                <BaseInput
                                    v-model="ajuste.monto"
                                    label="Monto"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    required
                                />
                                <div class="md:col-span-2">
                                    <BaseInput
                                        v-model="ajuste.motivo"
                                        label="Motivo obligatorio"
                                        required
                                    />
                                </div>
                                <button
                                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white md:col-start-4"
                                    :disabled="saving"
                                >
                                    Registrar ajuste
                                </button>
                            </form>
                        </section>

                        <section
                            class="overflow-hidden rounded-2xl border bg-white shadow-sm"
                        >
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead
                                        class="bg-slate-50 text-xs uppercase text-slate-500"
                                    >
                                        <tr>
                                            <th class="px-4 py-3 text-left">
                                                Fecha
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Movimiento
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Referencia
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Cargo
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Abono
                                            </th>
                                            <th class="px-4 py-3 text-right">
                                                Saldo
                                            </th>
                                            <th class="px-4 py-3 text-left">
                                                Usuario
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr
                                            v-for="m in movimientos"
                                            :key="m.id"
                                        >
                                            <td
                                                class="whitespace-nowrap px-4 py-3"
                                            >
                                                {{ fecha(m.created_at) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <strong>{{
                                                    etiqueta(m.tipo)
                                                }}</strong>
                                                <p
                                                    class="text-xs text-slate-500"
                                                >
                                                    {{ m.concepto }}
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">
                                                {{
                                                    m.compra?.folio ||
                                                    m.devolucion?.referencia ||
                                                    "—"
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right text-rose-600"
                                            >
                                                {{
                                                    Number(m.importe_firmado) <
                                                    0
                                                        ? money(
                                                              -m.importe_firmado,
                                                          )
                                                        : "—"
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right text-emerald-700"
                                            >
                                                {{
                                                    Number(m.importe_firmado) >
                                                    0
                                                        ? money(
                                                              m.importe_firmado,
                                                          )
                                                        : "—"
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-bold"
                                            >
                                                {{ money(m.saldo_resultante) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ m.user?.name || "—" }}
                                            </td>
                                        </tr>
                                        <tr v-if="!movimientos.length">
                                            <td
                                                colspan="7"
                                                class="px-4 py-12 text-center text-slate-400"
                                            >
                                                Sin movimientos para mostrar.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </template>
                </main>
            </section>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Loader2, X } from "lucide-vue-next";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import http from "@/lib/http";
import { error, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({ open: Boolean, proveedor: Object });
defineEmits(["close", "updated"]);
const auth = useAuthStore();
const puedeAjustar = computed(() => auth.can("proveedores.saldos.ajustar"));
const loading = ref(false),
    saving = ref(false),
    mostrarAjuste = ref(false),
    resumen = ref({}),
    movimientos = ref([]);
const filtros = reactive({ desde: "", hasta: "", tipo: "" });
const ajuste = reactive({ naturaleza: "credito", monto: "", motivo: "" });
const tiposMovimiento = [
    { id: "credito", nombre: "Saldo generado" },
    { id: "aplicacion", nombre: "Saldo aplicado" },
    { id: "ajuste_credito", nombre: "Ajuste a favor" },
    { id: "ajuste_debito", nombre: "Ajuste en contra" },
    { id: "reverso_credito", nombre: "Reversión de crédito" },
    { id: "reverso_aplicacion", nombre: "Reversión de aplicación" },
];
const naturalezasAjuste = [
    { id: "credito", nombre: "Agregar saldo" },
    { id: "debito", nombre: "Quitar saldo" },
];
const money = (v) =>
    new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(v || 0));
const fecha = (v) =>
    new Intl.DateTimeFormat("es-MX", {
        dateStyle: "short",
        timeStyle: "short",
    }).format(new Date(v));
const etiqueta = (t) =>
    ({
        credito: "Saldo generado",
        aplicacion: "Saldo aplicado",
        ajuste: "Ajuste",
        ajuste_credito: "Ajuste a favor",
        ajuste_debito: "Ajuste en contra",
        reverso_credito: "Reversión de crédito",
        reverso_aplicacion: "Reversión de aplicación",
    })[t] || t;
const cards = computed(() => [
    {
        label: "Saldo a favor",
        value: money(resumen.value.saldo_favor),
        class: "text-emerald-700",
    },
    {
        label: "Saldo aplicado",
        value: money(resumen.value.saldo_aplicado),
        class: "text-slate-900",
    },
    {
        label: "Cuentas por pagar",
        value: money(resumen.value.cuentas_por_pagar),
        class: "text-amber-700",
    },
    {
        label: "Compras vencidas",
        value: resumen.value.compras_vencidas || 0,
        class: "text-rose-700",
    },
]);
async function cargar() {
    if (!props.proveedor?.id) return;
    loading.value = true;
    try {
        const { data } = await http.get(
            `/api/proveedores/${props.proveedor.id}/estado-cuenta`,
            { params: filtros },
        );
        resumen.value = data.resumen;
        movimientos.value = data.movimientos.data;
    } catch {
        error("Error", "No se pudo cargar el estado de cuenta.");
    } finally {
        loading.value = false;
    }
}
async function ajustar() {
    saving.value = true;
    try {
        await http.post(
            `/api/proveedores/${props.proveedor.id}/ajustes-saldo`,
            ajuste,
        );
        ajuste.monto = "";
        ajuste.motivo = "";
        mostrarAjuste.value = false;
        toastSuccess("Ajuste registrado");
        await cargar();
    } catch (e) {
        error(
            "No se pudo registrar",
            e.response?.data?.message || "Revisa los datos.",
        );
    } finally {
        saving.value = false;
    }
}
watch(
    () => [props.open, props.proveedor?.id],
    ([open]) => {
        if (open) cargar();
    },
);
</script>
