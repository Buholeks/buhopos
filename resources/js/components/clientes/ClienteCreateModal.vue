<template>
    <BaseModal
        :open="open"
        title="Nuevo cliente"
        subtitle="Registra al cliente sin salir de esta operación."
        size="lg"
        :close-on-esc="!saving"
        :close-on-backdrop="!saving"
        @close="close"
    >
        <ClienteForm
            :model="form"
            :loading="saving"
            submit-text="Registrar cliente"
            :errors="formErrors"
            @submit="save"
            @cancel="close"
        />
    </BaseModal>
</template>

<script setup>
import { ref, watch } from "vue";
import http from "@/lib/http";
import { error, toastSuccess, toastWarning } from "@/lib/alert";
import BaseModal from "@/components/ui/BaseModal.vue";
import ClienteForm from "@/components/clientes/ClienteForm.vue";

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "created"]);

const saving = ref(false);
const formErrors = ref(null);
const form = ref(emptyForm());

function emptyForm() {
    return {
        nombre: "",
        correo: "",
        telefono: "",
        direccion: "",
        activo: true,
    };
}

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.value = emptyForm();
        formErrors.value = null;
    },
);

function close() {
    if (saving.value) return;
    emit("close");
}

async function save() {
    saving.value = true;
    formErrors.value = null;

    try {
        const { data } = await http.post("/api/clientes", form.value);
        toastSuccess("Cliente registrado correctamente.");
        emit("created", data);
        emit("close");
    } catch (e) {
        if (e?.response?.status === 422) {
            formErrors.value = e.response.data.errors || {};
            toastWarning("Hay campos con errores, revisa el formulario.");
        } else {
            error("Error", "No se pudo guardar el cliente.");
        }
    } finally {
        saving.value = false;
    }
}
</script>
