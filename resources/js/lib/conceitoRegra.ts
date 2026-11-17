/**
 * Regra do modo 'conceito' — a mesma do legado (Maracás) e do backend
 * (App\Support\CalculoNota::conceitoPelaRegra). Mantenha as duas iguais.
 *
 * Unidade: aplicada aos conceitos das avaliações. Ano: aos conceitos das unidades.
 *  1. Maioria absoluta: um conceito com mais ocorrências que todos os outros juntos vence.
 *  2. Menor, menor, meio, maior (R, R, S, H em qualquer ordem) → maior.
 *  3. Empate menor × maior, sem nenhum do meio (R, H / R, R, H, H) → maior.
 *  4. Demais: média dos pesos arredondada PARA CIMA (1,5 → 2; 2,33 → 3).
 *
 * @param pesos  pesos dos conceitos lançados (vazios já removidos)
 * @param escala pesos de todos os conceitos cadastrados
 * @returns o peso do conceito resultante, ou null se não houver lançamento
 */
export function pesoPelaRegraDoConceito(pesos: number[], escala: number[]): number | null {
    const ps = pesos.map(Number).filter((p) => Number.isFinite(p) && p > 0);
    if (!ps.length || !escala.length) return null;

    const n = ps.length;
    const qtd = new Map<number, number>();
    for (const p of ps) qtd.set(p, (qtd.get(p) ?? 0) + 1);

    for (const [peso, q] of qtd) {
        if (q > n - q) return peso;
    }

    const menor = Math.min(...escala.map(Number));
    const maior = Math.max(...escala.map(Number));
    const qMenor = qtd.get(menor) ?? 0;
    const qMaior = qtd.get(maior) ?? 0;
    const qMeio = n - qMenor - qMaior;

    if (qMenor === 2 && qMeio === 1 && qMaior === 1) return maior;
    if (qMenor === qMaior && qMenor > 0 && qMeio === 0) return maior;

    const media = ps.reduce((s, p) => s + p, 0) / n;
    return Math.ceil(Number(media.toFixed(6)));
}
