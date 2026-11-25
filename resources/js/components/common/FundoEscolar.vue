<script setup lang="ts">
// Fundo das telas públicas (início, login): azul que clareia de cima p/ baixo + doodles
// escolares (mesmo padrão do PEI). O pai precisa de `relative isolate overflow-hidden`.
</script>

<template>
    <div
        class="absolute inset-0 -z-20 bg-[linear-gradient(to_bottom,#5d9bd8_0%,#a9cbec_38%,#e3eefa_72%,#f5f9fd_100%)] dark:bg-[linear-gradient(to_bottom,#143b6e_0%,#0d2647_45%,#081627_100%)]"
    />
    <div class="fundo-doodles absolute inset-0 -z-10 bg-[#0c2a52] opacity-[0.11] dark:bg-white dark:opacity-[0.06]" aria-hidden="true" />
</template>

<style scoped>
/* PNG com fundo transparente e traços no alpha: a mask deixa só os traços, na cor do bg-*. */
.fundo-doodles {
    -webkit-mask-image: url('/img/login/fundo-icones.png');
    mask-image: url('/img/login/fundo-icones.png');
    -webkit-mask-size: 640px;
    mask-size: 640px;
    -webkit-mask-repeat: repeat;
    mask-repeat: repeat;
}
</style>
