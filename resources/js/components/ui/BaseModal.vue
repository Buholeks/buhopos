<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Overlay -->
                <div
                    class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
                    @click="closeOnBackdrop ? emit('close') : null"
                />

                <!-- Modal -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-3 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-3 scale-95 opacity-0"
                >
                    <div
                        v-if="open"
                        class="relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                        :class="sizeClass"
                    >
                        <!-- Header -->
                        <div
                            v-if="title || subtitle || $slots.header"
                            class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4"
                        >
                            <slot name="header">
                                <div class="min-w-0">
                                    <h2 class="truncate text-base font-semibold text-slate-900">
                                        {{ title }}
                                    </h2>
                                    <p
                                        v-if="subtitle"
                                        class="mt-0.5 text-xs text-slate-500"
                                    >
                                        {{ subtitle }}
                                    </p>
                                </div>
                            </slot>

                            <button
                                v-if="showClose"
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                @click="emit('close')"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <slot />
                        </div>

                        <!-- Footer -->
                        <div
                            v-if="$slots.footer"
                            class="border-t border-slate-200 bg-slate-50 px-5 py-4"
                        >
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch } from "vue";
import { X } from "lucide-vue-next";

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: "" },
    subtitle: { type: String, default: "" },
    size: {
        type: String,
        default: "md",
        validator: (value) => ["sm", "md", "lg", "xl", "full"].includes(value),
    },
    showClose: { type: Boolean, default: true },
    closeOnEsc: { type: Boolean, default: true },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(["close"]);

const sizeClass = computed(() => {
    return {
        sm: "max-w-md",
        md: "max-w-xl",
        lg: "max-w-3xl",
        xl: "max-w-5xl",
        full: "max-w-[95vw]",
    }[props.size];
});

function handleEsc(event) {
    if (event.key === "Escape" && props.open && props.closeOnEsc) {
        emit("close");
    }
}

watch(
    () => props.open,
    (value) => {
        document.body.style.overflow = value ? "hidden" : "";
    },
);

onMounted(() => {
    window.addEventListener("keydown", handleEsc);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleEsc);
    document.body.style.overflow = "";
});
</script>