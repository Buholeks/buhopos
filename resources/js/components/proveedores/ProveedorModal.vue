<template>
    <div v-if="open" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div
                class="w-full max-w-4xl rounded-2xl bg-white shadow-lg border border-slate-200"
            >
                <div
                    class="flex items-center justify-between px-4 py-3 border-b"
                >
                    <div>
                        <div class="text-base font-semibold text-slate-900">
                            {{ title }}
                        </div>
                        <div v-if="subtitle" class="text-xs text-slate-500">
                            {{ subtitle }}
                        </div>
                    </div>

                    <button
                        class="px-2 py-1 rounded-lg hover:bg-slate-100"
                        @click="emit('close')"
                    >
                        Cerrar
                    </button>
                </div>

                <div class="p-4">
                    <ProveedorForm
                        :model="model"
                        :loading="loading"
                        :submitText="submitText"
                        :errors="errors"
                        @submit="emit('submit')"
                        @cancel="emit('close')"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import ProveedorForm from "./ProveedorForm.vue";

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: "Proveedor" },
    subtitle: { type: String, default: "" },
    model: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    submitText: { type: String, default: "" },
    errors: { type: Object, default: null },
});

const emit = defineEmits(["close", "submit"]);
</script>
