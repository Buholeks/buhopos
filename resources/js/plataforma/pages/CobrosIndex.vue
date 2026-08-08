<template>
    <div class="mx-auto w-full max-w-5xl space-y-5">
        <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <Landmark class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Configuración</p>
                    <h1 class="mt-0.5 text-xl font-bold text-slate-900">Cobros y datos bancarios</h1>
                    <p class="mt-1 text-sm text-slate-500">Define la cuenta que verán los clientes al pagar por transferencia o depósito.</p>
                </div>
            </div>
        </header>

        <section v-if="cargando" class="flex min-h-56 items-center justify-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="h-7 w-7 animate-spin text-emerald-600" />
        </section>

        <form v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" @submit.prevent="guardar">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold text-slate-900">Cuenta bancaria principal</h2>
                <p class="mt-0.5 text-sm text-slate-500">Debe pertenecer a la cuenta receptora de los pagos de suscripción.</p>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Banco</span><input v-model.trim="cuenta.banco" required maxlength="120" placeholder="Ej. BBVA" class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" /></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Beneficiario</span><input v-model.trim="cuenta.beneficiario" required maxlength="255" placeholder="Nombre o razón social" class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" /></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-600">CLABE</span><input v-model.trim="cuenta.clabe" inputmode="numeric" maxlength="18" placeholder="18 dígitos" class="h-11 w-full rounded-xl border border-slate-200 px-4 font-mono text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" /></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Número de cuenta</span><input v-model.trim="cuenta.numero_cuenta" maxlength="40" placeholder="Opcional si proporcionas CLABE" class="h-11 w-full rounded-xl border border-slate-200 px-4 font-mono text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" /></label>
                <label class="block sm:col-span-2"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Instrucciones adicionales</span><textarea v-model.trim="cuenta.instrucciones" maxlength="1000" rows="3" placeholder="Horario de revisión u otra indicación para el cliente" class="w-full rounded-xl border border-slate-200 p-4 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" /></label>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-xs text-slate-500"><ShieldCheck class="h-4 w-4 text-emerald-600" />La cuenta sólo se muestra a clientes autenticados.</p>
                <button type="submit" :disabled="guardando" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white disabled:opacity-50">
                    <LoaderCircle v-if="guardando" class="h-4 w-4 animate-spin" /><Save v-else class="h-4 w-4" />{{ guardando ? "Guardando…" : "Guardar datos bancarios" }}
                </button>
            </div>
        </form>

        <section v-if="!cargando" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div><h2 class="font-bold text-slate-900">Comprobantes por revisar</h2><p class="mt-0.5 text-sm text-slate-500">Transferencias y depósitos reportados por los clientes.</p></div>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">{{ solicitudes.length }} pendientes</span>
            </div>
            <div v-if="solicitudes.length" class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Empresa</th><th class="px-5 py-3">Referencia</th><th class="px-5 py-3">Método</th><th class="px-5 py-3 text-right">Importe</th><th class="px-5 py-3 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="solicitud in solicitudes" :key="solicitud.id" class="hover:bg-slate-50/70">
                            <td class="px-5 py-3.5"><p class="font-semibold text-slate-800">{{ solicitud.empresa.nombre }}</p><p class="text-xs text-slate-400">{{ solicitud.empresa.correo || 'Sin correo' }}</p></td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-600">{{ solicitud.referencia_unica }}</td>
                            <td class="px-5 py-3.5 capitalize text-slate-600">{{ solicitud.metodo }}</td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-900">{{ dinero(solicitud.importe) }}</td>
                            <td class="px-5 py-3.5"><div class="flex justify-end gap-2"><a :href="`/api/plataforma/solicitudes-pago/${solicitud.id}/comprobante`" target="_blank" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700">Ver</a><button type="button" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" @click="revisar(solicitud, 'rechazar')">Rechazar</button><button type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white" @click="revisar(solicitud, 'confirmar')">Confirmar</button></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-5 py-10 text-center"><ReceiptText class="mx-auto h-8 w-8 text-slate-300" /><p class="mt-3 text-sm font-semibold text-slate-700">No hay comprobantes pendientes</p></div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { Landmark, LoaderCircle, ReceiptText, Save, ShieldCheck } from "lucide-vue-next";
import http from "@/lib/http";
import { confirm, error as showError, swal, toastSuccess } from "@/lib/alert";

const cargando = ref(true);
const guardando = ref(false);
const cuenta = reactive({ banco: "", beneficiario: "", clabe: "", numero_cuenta: "", instrucciones: "" });
const solicitudes = ref([]);
const dinero = (valor) => new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(valor || 0));

async function cargar() {
    try {
        const [respuestaCuenta, respuestaSolicitudes] = await Promise.all([
            http.get("/api/plataforma/cuenta-cobro"),
            http.get("/api/plataforma/solicitudes-pago?estado=en_revision"),
        ]);
        if (respuestaCuenta.data) Object.assign(cuenta, respuestaCuenta.data);
        solicitudes.value = respuestaSolicitudes.data.data || [];
    } catch (error) {
        showError("Error", error?.response?.data?.message || "No se pudieron cargar los datos bancarios.");
    } finally {
        cargando.value = false;
    }
}

async function revisar(solicitud, accion) {
    let notas_revision = null;
    if (accion === "rechazar") {
        const resultado = await swal.fire({ title: "Rechazar comprobante", input: "textarea", inputLabel: "Motivo para el cliente", showCancelButton: true, confirmButtonText: "Rechazar", inputValidator: (valor) => !valor?.trim() ? "Escribe el motivo" : undefined });
        if (!resultado.isConfirmed) return;
        notas_revision = resultado.value.trim();
    } else {
        const aceptado = await confirm({ title: "¿Confirmar el pago?", text: `${solicitud.empresa.nombre} · ${dinero(solicitud.importe)}`, confirmText: "Sí, confirmar", tone: "primary", icon: "question" });
        if (!aceptado) return;
    }
    try {
        await http.post(`/api/plataforma/solicitudes-pago/${solicitud.id}/revisar`, { accion, notas_revision });
        solicitudes.value = solicitudes.value.filter((item) => item.id !== solicitud.id);
        toastSuccess(accion === "confirmar" ? "Pago confirmado" : "Comprobante rechazado");
    } catch (error) {
        showError("Error", error?.response?.data?.message || "No se pudo revisar el comprobante.");
    }
}

async function guardar() {
    guardando.value = true;
    try {
        const { data } = await http.put("/api/plataforma/cuenta-cobro", cuenta);
        Object.assign(cuenta, data);
        toastSuccess("Datos bancarios guardados");
    } catch (error) {
        const errores = error?.response?.data?.errors;
        showError("No se pudo guardar", errores ? Object.values(errores).flat().join(" ") : error?.response?.data?.message || "Revisa la información capturada.");
    } finally {
        guardando.value = false;
    }
}

onMounted(cargar);
</script>
