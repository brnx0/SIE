<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download, Printer, X } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    url: string;
    filename?: string;
    title?: string;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const iframeRef = ref<HTMLIFrameElement | null>(null);

const imprimir = () => {
    try {
        iframeRef.value?.contentWindow?.focus();
        iframeRef.value?.contentWindow?.print();
    } catch {
        const w = window.open(props.url, '_blank');
        w?.focus();
    }
};

const baixar = () => {
    const a = document.createElement('a');
    a.href = props.url;
    a.download = props.filename ?? 'plano.pdf';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="emit('close')">
        <div class="flex h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-card shadow-2xl">
            <div class="flex items-center justify-between border-b bg-muted/40 px-5 py-3">
                <h2 class="text-base font-semibold">{{ title ?? 'Pré-visualização' }}</h2>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="imprimir">
                        <Printer class="mr-2 size-4" /> Imprimir
                    </Button>
                    <Button variant="outline" size="sm" @click="baixar">
                        <Download class="mr-2 size-4" /> Baixar
                    </Button>
                    <Button variant="ghost" size="sm" @click="emit('close')">
                        <X class="size-5" />
                    </Button>
                </div>
            </div>
            <div class="flex-1 bg-muted/20">
                <iframe ref="iframeRef" :src="url" class="h-full w-full border-0" />
            </div>
        </div>
    </div>
</template>
