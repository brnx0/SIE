// Validação client-side de arquivos de upload (tipo + tamanho).
// Retorna a mensagem de erro, ou null se o arquivo é válido.

export const TIPOS_IMAGEM = ['image/jpeg', 'image/png', 'image/webp'] as const;

const NOME_TIPO: Record<string, string> = {
    'image/jpeg': 'JPG',
    'image/png': 'PNG',
    'image/webp': 'WEBP',
    'application/pdf': 'PDF',
};

export const validarArquivo = (file: File, tipos: readonly string[], maxMb = 5): string | null => {
    if (tipos.length && !tipos.includes(file.type)) {
        const nomes = tipos.map((t) => NOME_TIPO[t] ?? t).join(', ');
        return `Formato inválido${file.type ? ` (${file.type})` : ''}. Envie um arquivo ${nomes}.`;
    }
    if (maxMb && file.size > maxMb * 1024 * 1024) {
        return `Arquivo muito grande (máximo ${maxMb} MB).`;
    }
    return null;
};

/** Atalho para imagens (JPG/PNG/WEBP). */
export const validarImagem = (file: File, maxMb = 5): string | null => validarArquivo(file, TIPOS_IMAGEM, maxMb);
