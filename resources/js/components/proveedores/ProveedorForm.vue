<template>
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div class="grid grid-cols-2 sm:grid-cols-8 gap-3">
            <div class="col-span-3">
                <BaseInput
                    v-model="model.nombre_comercial"
                    label="nombre comercial"
                    type="text"
                    placeholder="Ej. Comercializadora Buho"
                    required
                />
                <p
                    v-if="errors?.nombre_comercial"
                    class="text-xs text-rose-600 mt-1"
                >
                    {{ errors.nombre_comercial[0] }}
                </p>
            </div>
            <div class="col-span-2">
                <BaseInput
                    v-model="model.razon_social"
                    label="razon social"
                    type="text"
                    placeholder="Ej. Buho S.A. de C.V."
                    required
                />
                <p
                    v-if="errors?.razon_social"
                    class="text-xs text-rose-600 mt-1"
                >
                    {{ errors.razon_social[0] }}
                </p>
            </div>

            <div class="col-span-3">
                <BaseInput
                    v-model="model.contacto"
                    label="Persona de contacto"
                    type="text"
                    placeholder="Ej. Juan Pérez"
                    required
                />
                <p v-if="errors?.contacto" class="text-xs text-rose-600 mt-1">
                    {{ errors.contacto[0] }}
                </p>
            </div>
            <div class="col-span-3">
                <BaseInput
                    v-model="model.rfc"
                    label="RFC"
                    type="text"
                    placeholder="Ej. BUHO800101XXX"
                    required
                />
                <p v-if="errors?.rfc" class="text-xs text-rose-600 mt-1">
                    {{ errors.rfc[0] }}
                </p>
            </div>
            <div class="col-span-2">
                <BaseInput
                    v-model="model.telefono"
                    label="Teléfono"
                    type="text"
                    placeholder="Ej. 9671344072"
                    required
                />
                <p v-if="errors?.telefono" class="text-xs text-rose-600 mt-1">
                    {{ errors.telefono[0] }}
                </p>
            </div>
            <div class="col-span-2">
                <BaseInput
                    v-model="model.email"
                    label="Correo electrónico"
                    type="text"
                    placeholder="Ej. email@ejemplo.com"
                />
                <p v-if="errors?.email" class="text-xs text-rose-600 mt-1">
                    {{ errors.email[0] }}
                </p>
            </div>
        </div>
        <!-- para direccion -->
        <div class="grid grid-cols-1 sm:grid-cols-8 gap-3">
            <div class="sm:col-span-2">
                <BaseInput
                    v-model="model.calle"
                    label="calle"
                    type="text"
                    placeholder="Ej: honduras"
                />
                <p v-if="errors?.calle" class="text-xs text-rose-600 mt-1">
                    {{ errors.calle[0] }}
                </p>
            </div>
            <div class="sm:col-span-1">
                <BaseInput v-model="model.numero" label="numero" type="text" placeholder="Ej: 10" />
                <p v-if="errors?.numero" class="text-xs text-rose-600 mt-1">
                    {{ errors.numero[0] }}
                </p>
            </div>
            <div class="sm:col-span-2">
                <BaseInput
                    v-model="model.colonia"
                    label="colonia"
                    type="text"
                    placeholder="Ej: Mexicanos"
                />
                <p v-if="errors?.colonia" class="text-xs text-rose-600 mt-1">
                    {{ errors.colonia[0] }}
                </p>
            </div>
            <div class="sm:col-span-2">
                <BaseInput v-model="model.ciudad" label="ciudad" type="text" placeholder="Ej: San Cristobal" />
                <p v-if="errors?.ciudad" class="text-xs text-rose-600 mt-1">
                    {{ errors.ciudad[0] }}
                </p>
            </div>
            <div class="sm:col-span-2">
                <BaseInput v-model="model.estado" label="estado" type="text" placeholder="Ej: Chiapas"/>
                <p v-if="errors?.estado" class="text-xs text-rose-600 mt-1">
                    {{ errors.estado[0] }}
                </p>
            </div>
            <div class="sm:col-span-1">
                <BaseInput v-model="model.cp" label="CP" type="text" placeholder="Ej:29200"/>
                <p v-if="errors?.cp" class="text-xs text-rose-600 mt-1">
                    {{ errors.cp[0] }}
                </p>
            </div>
            <div
                class="sm:col-span-5 flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2"
            >
                <div>
                    <div class="text-sm font-medium text-slate-900">Activo</div>
                    <div class="text-xs text-slate-500">
                        Si está desactivado, no se sugiere en operaciones.
                    </div>
                </div>

                <button
                    type="button"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                    :class="model.activo ? 'bg-emerald-600' : 'bg-slate-300'"
                    @click="model.activo = !model.activo"
                >
                    <span
                        class="inline-block h-5 w-5 transform rounded-full bg-white transition"
                        :class="
                            model.activo ? 'translate-x-5' : 'translate-x-1'
                        "
                    />
                </button>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
            <button
                type="button"
                class="px-4 py-2 rounded-lg border hover:bg-slate-50"
                :disabled="loading"
                @click="emit('cancel')"
            >
                Cancelar
            </button>

            <button
                type="submit"
                class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-60"
                :disabled="loading"
            >
                {{ loading ? "Guardando..." : submitText || "Guardar" }}
            </button>
        </div>
    </form>
</template>

<script setup>
import BaseInput from "../ui/BaseInput.vue";

const props = defineProps({
    model: { type: Object, required: true }, // {nombre, correo, telefono, direccion, activo}
    loading: { type: Boolean, default: false },
    submitText: { type: String, default: "" },
    errors: { type: Object, default: null },
});

const emit = defineEmits(["submit", "cancel"]);
</script>
