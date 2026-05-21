<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, X, XCircle } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

type FlashShape = { success?: string | null; error?: string | null };

const toasts = ref<Toast[]>([]);
let counter = 0;
const timers = new Map<number, ReturnType<typeof setTimeout>>();

const dismiss = (id: number) => {
    toasts.value = toasts.value.filter((t) => t.id !== id);
    const tm = timers.get(id);
    if (tm) {
        clearTimeout(tm);
        timers.delete(id);
    }
};

const push = (type: Toast['type'], message: string) => {
    const id = ++counter;
    toasts.value.push({ id, type, message });
    const tm = setTimeout(() => dismiss(id), 4500);
    timers.set(id, tm);
};

const consume = (flash?: FlashShape | null) => {
    if (!flash) return;
    if (flash.success) push('success', flash.success);
    if (flash.error) push('error', flash.error);
};

// Toda visita Inertia bem-sucedida — dispara mesmo quando mensagem se repete.
// Não usar onMounted: layout remonta a cada visita → causaria toast duplicado.
const removeListener = router.on('success', (event) => {
    const props = (event.detail.page.props ?? {}) as { flash?: FlashShape };
    consume(props.flash);
});

onBeforeUnmount(() => {
    removeListener();
    timers.forEach((tm) => clearTimeout(tm));
    timers.clear();
});
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-full max-w-sm flex-col gap-2">
            <transition-group
                name="toast"
                tag="div"
                class="flex flex-col gap-2"
            >
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    role="status"
                    :class="[
                        'pointer-events-auto flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg ring-1 ring-black/5 backdrop-blur transition',
                        t.type === 'success'
                            ? 'border-emerald-200 bg-emerald-50/95 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/40 dark:text-emerald-100'
                            : 'border-rose-200 bg-rose-50/95 text-rose-800 dark:border-rose-900/50 dark:bg-rose-900/40 dark:text-rose-100',
                    ]"
                >
                    <CheckCircle2 v-if="t.type === 'success'" class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                    <XCircle v-else class="mt-0.5 size-5 shrink-0 text-rose-600 dark:text-rose-300" />
                    <p class="flex-1 text-sm leading-relaxed">{{ t.message }}</p>
                    <button
                        type="button"
                        class="rounded p-1 transition hover:bg-black/5 dark:hover:bg-white/10"
                        @click="dismiss(t.id)"
                        aria-label="Fechar"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </transition-group>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-from {
    opacity: 0;
    transform: translateX(20px);
}
.toast-enter-active,
.toast-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
