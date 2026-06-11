<template>
    <main class="space-y-4">
        <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <UserRoundCog class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">Mi perfil</h1>
                    <p class="text-xs text-slate-500">Administra tus datos y la configuración de tu organización.</p>
                </div>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                {{ auth.rolActual ?? (auth.esSuperAdmin ? "Super Admin" : "Sin rol") }}
            </span>
        </section>

        <div v-if="cargando" class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">
            <Loader2 class="mx-auto mb-2 h-5 w-5 animate-spin" />
            Cargando perfil...
        </div>

        <div v-else class="grid gap-4 xl:grid-cols-3">
            <ProfileCard title="Usuario" description="Datos personales y acceso al sistema" :icon="UserRound">
                <form class="space-y-4" @submit.prevent="guardarUsuario">
                    <BaseInput v-model="usuario.name" label="Nombre" :error="errorUsuario('name')" required />
                    <BaseInput v-model="usuario.email" label="Correo de acceso" type="email" :error="errorUsuario('email')" required />
                    <div class="border-t border-slate-100 pt-4">
                        <p class="mb-3 text-xs text-slate-500">Deja estos campos vacíos para conservar tu contraseña.</p>
                        <div class="space-y-4">
                            <BaseInput v-model="usuario.current_password" label="Contraseña actual" type="password" autocomplete="current-password" :error="errorUsuario('current_password')" />
                            <BaseInput v-model="usuario.password" label="Nueva contraseña" type="password" autocomplete="new-password" :error="errorUsuario('password')" />
                            <BaseInput v-model="usuario.password_confirmation" label="Confirmar nueva contraseña" type="password" autocomplete="new-password" />
                        </div>
                    </div>
                    <SaveButton :loading="guardandoUsuario" text="Guardar usuario" />
                </form>
            </ProfileCard>

            <ProfileCard title="Empresa" description="Información fiscal y de contacto" :icon="Building2">
                <form class="space-y-4" @submit.prevent="guardarEmpresa">
                    <BaseInput v-model="empresa.nombre" label="Nombre" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('nombre')" required />
                    <BaseInput v-model="empresa.propietario" label="Propietario" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('propietario')" />
                    <BaseInput v-model="empresa.rfc" label="RFC" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('rfc')" />
                    <BaseInput v-model="empresa.correo" label="Correo" type="email" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('correo')" />
                    <BaseInput v-model="empresa.telefono" label="Teléfono" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('telefono')" />
                    <BaseInput v-model="empresa.direccion" label="Dirección" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('direccion')" />
                    <SaveButton v-if="puedeEditarEmpresa" :loading="guardandoEmpresa" text="Guardar empresa" />
                    <ReadOnlyNotice v-else />
                </form>
            </ProfileCard>

            <ProfileCard title="Sucursal activa" description="Datos de la sucursal seleccionada" :icon="MapPin">
                <form class="space-y-4" @submit.prevent="guardarSucursal">
                    <BaseInput v-model="sucursal.nombre" label="Nombre" :disabled="!puedeEditarSucursal" :error="errorSucursal('nombre')" required />
                    <BaseInput v-model="sucursal.correo" label="Correo" type="email" :disabled="!puedeEditarSucursal" :error="errorSucursal('correo')" />
                    <BaseInput v-model="sucursal.telefono" label="Teléfono" :disabled="!puedeEditarSucursal" :error="errorSucursal('telefono')" />
                    <BaseInput v-model="sucursal.direccion" label="Dirección" :disabled="!puedeEditarSucursal" :error="errorSucursal('direccion')" />
                    <SaveButton v-if="puedeEditarSucursal" :loading="guardandoSucursal" text="Guardar sucursal" />
                    <ReadOnlyNotice v-else />
                </form>
            </ProfileCard>
        </div>
    </main>
</template>

<script setup>
import { defineComponent, h, onMounted, reactive, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import { Building2, Loader2, MapPin, Save, ShieldAlert, UserRound, UserRoundCog } from "lucide-vue-next";

const auth = useAuthStore();
const cargando = ref(true);
const guardandoUsuario = ref(false);
const guardandoEmpresa = ref(false);
const guardandoSucursal = ref(false);
const puedeEditarEmpresa = ref(false);
const puedeEditarSucursal = ref(false);
const erroresUsuario = ref({});
const erroresEmpresa = ref({});
const erroresSucursal = ref({});

const usuario = reactive({ name: "", email: "", current_password: "", password: "", password_confirmation: "" });
const empresa = reactive({ nombre: "", propietario: "", rfc: "", correo: "", telefono: "", direccion: "" });
const sucursal = reactive({ nombre: "", correo: "", telefono: "", direccion: "" });

const ProfileCard = defineComponent({
    props: { title: String, description: String, icon: [Object, Function] },
    setup(props, { slots }) {
        return () => h("section", { class: "self-start overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" }, [
            h("div", { class: "flex items-center gap-3 border-b border-slate-100 px-4 py-3" }, [
                h("div", { class: "grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600" }, [h(props.icon, { class: "h-4 w-4" })]),
                h("div", {}, [h("h2", { class: "text-sm font-semibold text-slate-900" }, props.title), h("p", { class: "text-xs text-slate-500" }, props.description)]),
            ]),
            h("div", { class: "p-4" }, slots.default?.()),
        ]);
    },
});

const SaveButton = defineComponent({
    props: { loading: Boolean, text: String },
    setup(props) {
        return () => h("button", {
            type: "submit",
            disabled: props.loading,
            class: "inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50",
        }, [h(props.loading ? Loader2 : Save, { class: `h-4 w-4 ${props.loading ? "animate-spin" : ""}` }), props.loading ? "Guardando..." : props.text]);
    },
});

const ReadOnlyNotice = defineComponent({
    setup() {
        return () => h("div", { class: "flex gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800" }, [
            h(ShieldAlert, { class: "mt-0.5 h-4 w-4 shrink-0" }),
            h("span", {}, "Tu rol no tiene permiso para editar esta información."),
        ]);
    },
});

const errorUsuario = (campo) => erroresUsuario.value?.[campo]?.[0] ?? "";
const errorEmpresa = (campo) => erroresEmpresa.value?.[campo]?.[0] ?? "";
const errorSucursal = (campo) => erroresSucursal.value?.[campo]?.[0] ?? "";

onMounted(cargarPerfil);

async function cargarPerfil() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/perfil");
        aplicarPerfil(data);
    } catch (error) {
        toastError(error?.response?.data?.message || "No se pudo cargar el perfil.");
    } finally {
        cargando.value = false;
    }
}

function aplicarPerfil(data) {
    Object.assign(usuario, { name: data.name ?? "", email: data.email ?? "", current_password: "", password: "", password_confirmation: "" });
    Object.assign(empresa, normalizar(data.empresa, empresa));
    Object.assign(sucursal, normalizar(data.sucursal, sucursal));
    puedeEditarEmpresa.value = !!data.puede_editar_empresa;
    puedeEditarSucursal.value = !!data.puede_editar_sucursal;
    auth._setUser(data);
}

function normalizar(origen, destino) {
    return Object.fromEntries(Object.keys(destino).map((key) => [key, origen?.[key] ?? ""]));
}

async function guardarUsuario() {
    guardandoUsuario.value = true;
    erroresUsuario.value = {};
    try {
        const { data } = await http.put("/api/perfil/usuario", usuario);
        aplicarPerfil(data);
        toastSuccess("Perfil de usuario actualizado.");
    } catch (error) {
        erroresUsuario.value = error?.response?.data?.errors ?? {};
        toastError(error?.response?.data?.message || "No se pudo actualizar el usuario.");
    } finally {
        guardandoUsuario.value = false;
    }
}

async function guardarEmpresa() {
    guardandoEmpresa.value = true;
    erroresEmpresa.value = {};
    try {
        const { data } = await http.put("/api/perfil/empresa", empresa);
        aplicarPerfil(data);
        toastSuccess("Empresa actualizada.");
    } catch (error) {
        erroresEmpresa.value = error?.response?.data?.errors ?? {};
        toastError(error?.response?.data?.message || "No se pudo actualizar la empresa.");
    } finally {
        guardandoEmpresa.value = false;
    }
}

async function guardarSucursal() {
    guardandoSucursal.value = true;
    erroresSucursal.value = {};
    try {
        const { data } = await http.put("/api/perfil/sucursal", sucursal);
        aplicarPerfil(data);
        await auth.fetchSucursales();
        toastSuccess("Sucursal actualizada.");
    } catch (error) {
        erroresSucursal.value = error?.response?.data?.errors ?? {};
        toastError(error?.response?.data?.message || "No se pudo actualizar la sucursal.");
    } finally {
        guardandoSucursal.value = false;
    }
}
</script>
