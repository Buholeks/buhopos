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
                    <div class="mt-1 flex flex-wrap gap-1">
                        <span
                            v-for="badge in pendientes(p)"
                            :key="badge"
                            class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                        >
                            {{ badge }}
                        </span>
                    </div>
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
                        <span v-if="p.modelo?.nombre">/ {{ p.modelo.nombre }}</span>
                    </p>
                    <p
                        v-if="categoriaNombre(p) !== (p.categoria?.nombre ?? 'Sin categoria')"
                        class="text-[10px] text-slate-400"
                    >
                        {{ categoriaNombre(p) }}
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

                <td class="px-3 py-2 text-center">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                        :class="
                            p.activo
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-500'
                        "
                        :title="p.activo ? 'Desactivar producto' : 'Activar producto'"
                        @click.stop="$emit('toggle-activo', p)"
                    >
                        {{ p.activo ? "Activo" : "Inactivo" }}
                    </button>
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
                            @click.stop="$emit('duplicar', p)"
                            title="Duplicar producto"
                            class="rounded-md p-2 text-slate-600 hover:bg-slate-100"
                        >
                            <Copy class="h-4 w-4" />
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
    Copy,
    Trash2,
} from "lucide-vue-next";

const props = defineProps({
    productos: { type: Array, default: () => [] },
    formatPrecio: { type: Function, required: true },
    categoriaNombre: { type: Function, default: (p) => p.categoria?.nombre ?? "Sin categoria" },
});

defineEmits(["editar", "duplicar", "toggle-activo", "eliminar", "variantes"]);

function pendientes(p) {
    return [
        !p.categoria_id ? "Sin categoria" : null,
        !p.unidad_medida_id ? "Sin unidad" : null,
        !p.marca_id ? "Sin marca" : null,
        p.marca_id && !p.modelo_id ? "Sin modelo" : null,
        Number(p.precio_venta ?? 0) <= 0 ? "Sin precio" : null,
    ].filter(Boolean);
}

function categoriaNombre(p) {
    return props.categoriaNombre(p);
}
</script>
