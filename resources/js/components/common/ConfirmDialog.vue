<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle, Loader2, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Variant = 'danger' | 'warning' | 'success';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        message?: string;
        variant?: Variant;
        confirmLabel?: string;
        cancelLabel?: string;
        loading?: boolean;
    }>(),
    { title: 'Confirmar', message: '', variant: 'warning', confirmLabel: 'Confirmar', cancelLabel: 'Cancelar', loading: false },
);

const emit = defineEmits<{ (e: 'update:open', v: boolean): void; (e: 'confirm'): void; (e: 'cancel'): void }>();

const VARIANTS: Record<Variant, { ring: string; btn: string; icon: typeof Trash2 }> = {
    danger:  { ring: 'bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300', btn: 'bg-rose-600 text-white hover:bg-rose-700', icon: Trash2 },
    warning: { ring: 'bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300', btn: 'bg-amber-600 text-white hover:bg-amber-700', icon: AlertTriangle },
    success: { ring: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300', btn: 'bg-emerald-600 text-white hover:bg-emerald-700', icon: ShieldCheck },
};
const style = computed(() => VARIANTS[props.variant]);

const onCancel = () => { emit('cancel'); emit('update:open', false); };
</script>

<template>
    <Dialog :open="open" @update:open="(v) => { if (!v) onCancel(); }">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="mx-auto mb-1 grid size-14 place-items-center rounded-full" :class="style.ring">
                    <component :is="style.icon" class="size-7" />
                </div>
                <DialogTitle class="text-center text-base">{{ title }}</DialogTitle>
                <DialogDescription v-if="message" class="whitespace-pre-line text-center text-sm leading-relaxed">{{ message }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:justify-center">
                <Button variant="outline" :disabled="loading" @click="onCancel">{{ cancelLabel }}</Button>
                <Button :class="style.btn" :disabled="loading" @click="emit('confirm')">
                    <Loader2 v-if="loading" class="mr-2 size-4 animate-spin" />
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
