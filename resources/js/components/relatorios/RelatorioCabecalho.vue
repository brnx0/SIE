<script setup lang="ts">
/**
 * Cabeçalho institucional padrão dos relatórios impressos (logomarca, textos da
 * entidade + município, brasão). Fonte única — inclui o nome da cidade.
 *
 * As classes de layout (.cabecalho-impressao, .cab-entidade, etc.) são estilizadas
 * pelo CSS @media print de CADA view (global, não-scoped), então este componente
 * só renderiza o markup e fornece o estilo da linha nova (.cab-municipio).
 */
interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    municipio: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

defineProps<{ parametros: Parametros | null }>();
</script>

<template>
    <header class="cabecalho-impressao">
        <div class="logomarca">
            <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca do Município" />
        </div>
        <div class="cab-textos">
            <div v-if="parametros?.msg_cab_estado" class="cab-linha">{{ parametros.msg_cab_estado }}</div>
            <div v-if="parametros?.msg_cab_secretaria" class="cab-linha">{{ parametros.msg_cab_secretaria }}</div>
            <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
            <div v-if="parametros?.municipio" class="cab-municipio">{{ parametros.municipio }}</div>
            <div v-if="parametros?.endereco" class="cab-end">{{ parametros.endereco }}</div>
        </div>
        <div class="brasao">
            <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="Brasão" />
        </div>
    </header>
</template>

<style>
/* Estilo da linha do município — só impressão; as demais classes vêm do @media
   print de cada view. Não-scoped de propósito (para valer dentro do #print-area). */
@media print {
    .cab-municipio {
        font-size: 10pt;
        font-weight: 700;
        text-transform: uppercase;
    }
}
</style>
