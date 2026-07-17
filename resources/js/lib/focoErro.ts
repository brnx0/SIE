/**
 * Foco automático no primeiro campo inválido.
 *
 * Quando a validação do backend volta, o erro pode estar fora da tela — em
 * formulários longos o usuário só vê o "nada aconteceu" e precisa caçar o campo.
 * Aqui a página rola até a primeira mensagem visível e foca o campo dela.
 *
 * A busca é pelo DOM (`[data-input-error]`, marcado em InputError.vue) em vez de
 * pela chave do erro: os campos não têm id/name, então não há como mapear
 * `errors.dpa_tema` para um elemento sem tocar nas ~48 telas que têm formulário.
 */

const FOCAVEL = [
    'input:not([type="hidden"]):not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'button:not([disabled])',
    '[contenteditable="true"]',
].join(', ');

/**
 * v-show deixa a mensagem no DOM; só interessa a que está de fato aparecendo.
 *
 * getClientRects() em vez de offsetParent: `display:none` zera os rects, mas
 * offsetParent também é null dentro de `position:fixed` (modais) — daria falso
 * negativo justamente onde há formulário.
 */
const visivel = (el: HTMLElement): boolean => el.getClientRects().length > 0;

/**
 * Campo dono da mensagem de erro.
 *
 * O campo vem ANTES da mensagem (Label → input → InputError), então varre os
 * irmãos anteriores e só então sobe um nível. Subir demais pegaria o campo
 * vizinho ou o botão de salvar, por isso o limite de níveis.
 */
function campoDoErro(mensagem: HTMLElement): HTMLElement | null {
    let no: HTMLElement | null = mensagem.parentElement;

    for (let nivel = 0; no && nivel < 3; nivel++) {
        let irmao = no.previousElementSibling as HTMLElement | null;

        while (irmao) {
            if (irmao.matches(FOCAVEL)) return irmao;

            const dentro = irmao.querySelector<HTMLElement>(FOCAVEL);
            if (dentro) return dentro;

            irmao = irmao.previousElementSibling as HTMLElement | null;
        }

        no = no.parentElement;
    }

    return null;
}

/** Rola até o primeiro erro visível e foca o campo. Sem erro visível, não faz nada. */
export function focarPrimeiroErro(): void {
    const mensagens = Array.from(document.querySelectorAll<HTMLElement>('[data-input-error]'));
    const primeiro = mensagens.find(visivel);

    if (!primeiro) return;

    const campo = campoDoErro(primeiro);
    const alvo = campo ?? primeiro;

    alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // preventScroll: o scrollIntoView acima já posiciona; sem isto o focus dá um
    // segundo salto, brusco, e cancela a rolagem suave.
    campo?.focus({ preventScroll: true });
}

/**
 * Espera o Vue pintar as mensagens antes de procurar.
 *
 * O evento `error` do Inertia chega antes do re-render, então buscar na hora
 * acha o DOM anterior (sem erro, ou com o erro da tentativa passada).
 */
export function focarPrimeiroErroAposRender(): void {
    requestAnimationFrame(() => requestAnimationFrame(focarPrimeiroErro));
}
