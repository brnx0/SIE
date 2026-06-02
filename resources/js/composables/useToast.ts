import { ref } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

const toasts = ref<Toast[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
let counter = 0;

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
    timers.set(id, setTimeout(() => dismiss(id), 4500));
};

export const useToast = () => ({ toasts, push, dismiss });
