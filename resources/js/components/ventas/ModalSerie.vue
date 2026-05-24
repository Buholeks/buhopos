<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { Search, Smartphone, X, CheckCircle } from "lucide-vue-next";

const props = defineProps({
    productoId: { type: Number, required: true },
    varianteId: { type: Number, default: null },
    nombreProducto: { type: String, default: "" },
});

const emit = defineEmits(["confirm", "cancel"]);

const series = ref([]);
const cargando = ref(true);
const busqueda = ref("");
const seleccionado = ref(null);

const seriesFiltradas = computed(() => {
    if (!busqueda.value) return series.value;
    const b = busqueda.value.toLowerCase();
    return series.value.filter(
        (s) =>
            s.imei?.toLowerCase().includes(b) ||
            s.serie?.toLowerCase().includes(b) ||
            s.variante_sku?.toLowerCase().includes(b) ||
            s.notas?.toLowerCase().includes(b),
    );
});

onMounted(async () => {
    try {
        const { data } = await axios.get("/api/series/disponibles", {
            params: {
                producto_id: props.productoId,
                variante_id: props.varianteId,
            },
        });
        series.value = data.series;
    } catch {
        series.value = [];
    } finally {
        cargando.value = false;
    }
});

function seleccionar(serie) {
    seleccionado.value = serie.id === seleccionado.value?.id ? null : serie;
}

function confirmar() {
    if (!seleccionado.value) return;
    emit("confirm", seleccionado.value);
}

import { computed } from "vue";
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
            @click.self="$emit('cancel')"
        >
            <div
                class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50"
                        >
                            <Smartphone class="h-4 w-4 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-[13px] font-semibold text-slate-800">
                                Seleccionar IMEI / Serie
                            </p>
                            <p class="text-[11px] text-slate-400">
                                {{ nombreProducto }}
                            </p>
                        </div>
                    </div>
                    <button
                        class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        @click="$emit('cancel')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Buscador -->
                <div class="border-b border-slate-100 px-4 py-3">
                    <div
                        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        <Search class="h-4 w-4 flex-shrink-0 text-slate-400" />
                        <input
                            v-model="busqueda"
                            class="w-full bg-transparent text-[13px] outline-none placeholder:text-slate-400"
                            placeholder="Buscar IMEI, serie..."
                            autofocus
                        />
                    </div>
                </div>

                <!-- Lista -->
                <div class="max-h-72 overflow-y-auto">
                    <div
                        v-if="cargando"
                        class="flex items-center justify-center py-12"
                    >
                        <p class="text-[13px] text-slate-400">
                            Cargando series disponibles…
                        </p>
                    </div>

                    <div
                        v-else-if="seriesFiltradas.length === 0"
                        class="flex flex-col items-center justify-center py-12"
                    >
                        <Smartphone class="mb-2 h-8 w-8 text-slate-200" />
                        <p class="text-[13px] text-slate-400">
                            {{
                                series.length === 0
                                    ? "Sin unidades disponibles"
                                    : "Sin resultados"
                            }}
                        </p>
                    </div>

                    <button
                        v-for="s in seriesFiltradas"
                        :key="s.id"
                        type="button"
                        class="flex w-full items-center gap-3 border-b border-slate-50 px-4 py-3 text-left transition last:border-b-0"
                        :class="
                            seleccionado?.id === s.id
                                ? 'bg-emerald-50 hover:bg-emerald-50'
                                : 'bg-white hover:bg-slate-50'
                        "
                        @click="seleccionar(s)"
                    >
                        <!-- Ícono selección -->
                        <div class="flex-shrink-0">
                            <CheckCircle
                                v-if="seleccionado?.id === s.id"
                                class="h-5 w-5 text-emerald-500"
                            />
                            <div
                                v-else
                                class="h-5 w-5 rounded-full border-2 border-slate-200"
                            />
                        </div>

                        <!-- Info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-[13px] font-semibold text-slate-800"
                                >
                                    {{ s.identificador }}
                                </span>
                                <span
                                    v-if="s.imei2"
                                    class="font-mono text-[11px] text-slate-400"
                                >
                                    / {{ s.imei2 }}
                                </span>
                                <span
                                    v-if="s.variante_sku"
                                    class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-600"
                                >
                                    {{ s.variante_sku }}
                                </span>
                            </div>
                            <p
                                v-if="s.notas"
                                class="mt-0.5 truncate text-[11px] text-slate-400"
                            >
                                {{ s.notas }}
                            </p>
                        </div>

                        <!-- Precio especial si tiene -->
                        <div
                            v-if="s.precio_venta"
                            class="flex-shrink-0 text-right"
                        >
                            <span
                                class="font-mono text-[12px] font-medium text-emerald-600"
                            >
                                ${{
                                    Number(s.precio_venta).toLocaleString(
                                        "es-MX",
                                    )
                                }}
                            </span>
                            <p class="text-[10px] text-slate-400">
                                precio esp.
                            </p>
                        </div>
                    </button>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center justify-between border-t border-slate-100 px-4 py-3"
                >
                    <span class="text-[12px] text-slate-400">
                        {{ seriesFiltradas.length }} disponible{{
                            seriesFiltradas.length !== 1 ? "s" : ""
                        }}
                    </span>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-4 py-1.5 text-[12px] text-slate-500 hover:bg-slate-50"
                            @click="$emit('cancel')"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-1.5 text-[12px] font-semibold transition"
                            :class="
                                seleccionado
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                    : 'cursor-not-allowed bg-slate-100 text-slate-400'
                            "
                            :disabled="!seleccionado"
                            @click="confirmar"
                        >
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
