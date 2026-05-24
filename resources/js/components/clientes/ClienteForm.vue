<template>
    <form class="space-y-5" @submit.prevent="emit('submit')">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <BaseInput
                    v-model="model.nombre"
                    label="Nombre"
                    type="text"
                    placeholder="Ej. Juan Pérez"
                    required
                >
                    <template #icon>
                        <User class="h-4 w-4" />
                    </template>
                </BaseInput>

                <p v-if="errors?.nombre" class="mt-1 text-xs text-red-600">
                    {{ errors.nombre[0] }}
                </p>
            </div>

            <div>
                <BaseInput
                    v-model="model.correo"
                    label="Correo"
                    type="email"
                    placeholder="correo@dominio.com"
                >
                    <template #icon>
                        <Mail class="h-4 w-4" />
                    </template>
                </BaseInput>

                <p v-if="errors?.correo" class="mt-1 text-xs text-red-600">
                    {{ errors.correo[0] }}
                </p>
            </div>

            <div>
                <BaseInput
                    v-model="model.telefono"
                    label="Teléfono"
                    type="text"
                    placeholder="Ej. 9611234567"
                    required
                >
                    <template #icon>
                        <Phone class="h-4 w-4" />
                    </template>
                </BaseInput>

                <p v-if="errors?.telefono" class="mt-1 text-xs text-red-600">
                    {{ errors.telefono[0] }}
                </p>
            </div>

            <div class="sm:col-span-2">
                <BaseInput
                    v-model="model.direccion"
                    label="Dirección"
                    type="text"
                    placeholder="Calle, número, colonia, ciudad..."
                >
                    <template #icon>
                        <MapPin class="h-4 w-4" />
                    </template>
                </BaseInput>

                <p v-if="errors?.direccion" class="mt-1 text-xs text-red-600">
                    {{ errors.direccion[0] }}
                </p>
            </div>

            <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-xl"
                            :class="
                                model.activo
                                    ? 'bg-emerald-50 text-emerald-600'
                                    : 'bg-slate-200 text-slate-500'
                            "
                        >
                            <CheckCircle2 v-if="model.activo" class="h-4 w-4" />
                            <CircleOff v-else class="h-4 w-4" />
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                Cliente activo
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Si está desactivado, no se sugerirá en ventas ni operaciones.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                        :class="model.activo ? 'bg-emerald-600' : 'bg-slate-300'"
                        @click="model.activo = !model.activo"
                    >
                        <span class="sr-only">
                            {{ model.activo ? "Desactivar cliente" : "Activar cliente" }}
                        </span>

                        <span
                            class="inline-flex h-5 w-5 transform items-center justify-center rounded-full bg-white shadow-sm transition"
                            :class="model.activo ? 'translate-x-5' : 'translate-x-1'"
                        >
                            <Check
                                v-if="model.activo"
                                class="h-3 w-3 text-emerald-600"
                            />
                            <X
                                v-else
                                class="h-3 w-3 text-slate-400"
                            />
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
            <button
                type="button"
                class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="loading"
                @click="emit('cancel')"
            >
                Cancelar
            </button>

            <button
                type="submit"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="loading"
            >
                <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                {{ loading ? "Guardando..." : submitText || "Guardar" }}
            </button>
        </div>
    </form>
</template>

<script setup>
import BaseInput from "../ui/BaseInput.vue";

import {
    Check,
    CheckCircle2,
    CircleOff,
    Loader2,
    Mail,
    MapPin,
    Phone,
    Save,
    User,
    X,
} from "lucide-vue-next";

defineProps({
    model: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    submitText: { type: String, default: "" },
    errors: { type: Object, default: null },
});

const emit = defineEmits(["submit", "cancel"]);
</script>