<template>
    <table class="w-full border-collapse">
        <thead class="bg-slate-50">
            <tr
                class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >
                <th class="w-14 border-b border-slate-200 px-3 py-2"></th>
                <th class="border-b border-slate-200 px-3 py-2">Producto</th>
                <th class="border-b border-slate-200 px-3 py-2">Código</th>
                <th class="border-b border-slate-200 px-3 py-2">
                    Categoría / Marca
                </th>
                <th class="border-b border-slate-200 px-3 py-2 text-right">
                    Costo
                </th>
                <th class="border-b border-slate-200 px-3 py-2 text-right">
                    Venta
                </th>
                <th class="border-b border-slate-200 px-3 py-2 text-center">
                    Variantes
                </th>
                <th class="border-b border-slate-200 px-3 py-2 text-center">
                    Estado
                </th>
                <th class="border-b border-slate-200 px-3 py-2 text-right">
                    Acciones
                </th>
            </tr>
        </thead>

        <tbody>
            <tr
                v-for="p in productos"
                :key="p.id"
                class="cursor-pointer border-b border-slate-100 hover:bg-slate-50/60"
                @dblclick="$emit('editar', p)"
                title="Doble click para editar producto"
            >
                <td class="px-3 py-2">
                    <div
                        class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                    >
                        <img
                            v-if="p.imagen_url"
                            :src="p.imagen_url"
                            :alt="p.nombre"
                            class="h-full w-full object-contain"
                        />
                        <Image v-else class="h-5 w-5 text-slate-400" />
                    </div>
                </td>

                <td class="px-3 py-2">
                    <p class="text-sm font-medium text-slate-900">
                        {{ p.nombre }}
                    </p>
                    <p
                        v-if="p.unidad_medida"
                        class="mt-0.5 text-xs text-slate-500"
                    >
                        {{ p.unidad_medida.nombre }} ({{
                            p.unidad_medida.abreviatura
                        }})
                    </p>
                </td>

                <td class="px-3 py-2">
                    <span
                        class="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-xs text-slate-700"
                        >{{ p.codigo }}</span
                    >
                </td>

                <td class="px-3 py-2">
                    <p class="text-sm text-slate-900">
                        {{ p.categoria?.nombre ?? "—" }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ p.marca?.nombre ?? "" }}
                    </p>
                </td>

                <td
                    class="px-3 py-2 text-right font-mono text-sm text-slate-800"
                >
                    {{ formatPrecio(p.precio_costo) }}
                </td>
                <td
                    class="px-3 py-2 text-right font-mono text-sm text-slate-800"
                >
                    {{ formatPrecio(p.precio_venta) }}
                </td>

                <!-- Variantes -->
                <td class="px-3 py-2 text-center">
                    <button
                        @click.stop="$emit('variantes', p)"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="
                            p.tiene_variantes
                                ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                                : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                        "
                        :title="
                            p.tiene_variantes
                                ? 'Ver / agregar / editar variantes'
                                : 'Crear variantes'
                        "
                    >
                        <LayoutGrid class="h-4 w-4" />

                        <span v-if="p.tiene_variantes">Variantes</span>
                        <span v-else class="inline-flex items-center gap-1">
                            <Plus class="h-3.5 w-3.5" />
                            Variantes
                        </span>

                        <!-- Badge -->
                        <span
                            class="inline-flex items-center justify-center rounded-full bg-white/70 px-2 py-0.5 font-mono text-[10px]"
                        >
                            <template v-if="p.variantes_count != null">
                                {{ p.variantes_count }}
                            </template>

                            <template v-else>
                                <Check
                                    v-if="p.tiene_variantes"
                                    class="h-3.5 w-3.5 text-blue-700"
                                    title="Tiene variantes"
                                />
                                <span v-else>0</span>
                            </template>
                        </span>
                    </button>

                    <p class="mt-1 text-[10px] text-slate-400">
                        Doble click en una variante = editar
                    </p>
                </td>

                <td class="px-3 py-2 text-center">
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            p.activo
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-500'
                        "
                    >
                        {{ p.activo ? "Activo" : "Inactivo" }}
                    </span>
                </td>

                <!-- Acciones -->
                <td class="px-3 py-2 text-right">
                    <div class="inline-flex items-center gap-1">
                        <button
                            @click.stop="$emit('variantes', p)"
                            title="Variantes"
                            class="rounded-md p-2 text-blue-600 hover:bg-blue-50"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </button>

                        <button
                            @click.stop="$emit('editar', p)"
                            title="Editar"
                            class="rounded-md p-2 text-amber-600 hover:bg-amber-50"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>

                        <button
                            @click.stop="$emit('eliminar', p)"
                            title="Eliminar"
                            class="rounded-md p-2 text-red-600 hover:bg-red-50"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import {
    Image,
    LayoutGrid,
    Pencil,
    Trash2,
    Check,
    Plus,
} from "lucide-vue-next";

defineProps({
    productos: { type: Array, default: () => [] },
    formatPrecio: { type: Function, required: true },
});

defineEmits(["editar", "eliminar", "variantes"]);
</script>
