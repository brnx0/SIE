<script setup lang="ts">
import { Maximize2, X } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

// Miniatura de imagem que amplia em um lightbox (tela cheia) ao clicar.
// Usado nos inputs de foto/imagem (aluno, funcionário, logo da escola, logomarca/brasão).
const props = withDefaults(
    defineProps<{
        src: string;
        alt?: string;
        /** object-fit da miniatura (o lightbox sempre usa contain). */
        fit?: 'cover' | 'contain';
    }>(),
    { alt: '', fit: 'cover' },
);

const aberto = ref(false);

const onEsc = (e: KeyboardEvent) => {
    if (e.key === 'Escape') aberto.value = false;
};
watch(aberto, (v) => {
    if (v) document.addEventListener('keydown', onEsc);
    else document.removeEventListener('keydown', onEsc);
});
onBeforeUnmount(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <div class="group/zoom relative size-full">
        <img
            :src="props.src"
            :alt="props.alt"
            class="size-full cursor-zoom-in"
            :class="props.fit === 'contain' ? 'object-contain' : 'object-cover'"
            title="Clique para ampliar"
            @click="aberto = true"
        />
        <div
            class="pointer-events-none absolute inset-0 grid place-items-center bg-black/35 text-white opacity-0 transition group-hover/zoom:opacity-100"
            aria-hidden="true"
        >
            <Maximize2 class="size-6" />
        </div>

        <Teleport to="body">
            <div
                v-if="aberto"
                class="fixed inset-0 z-[100] grid place-items-center bg-black/80 p-4 backdrop-blur-sm"
                @click.self="aberto = false"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 grid size-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="Fechar"
                    @click="aberto = false"
                >
                    <X class="size-6" />
                </button>
                <img
                    :src="props.src"
                    :alt="props.alt"
                    class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                />
            </div>
        </Teleport>
    </div>
</template>
