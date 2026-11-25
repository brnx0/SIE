import './boasVindas.css';

/**
 * Animação de boas-vindas pós-login.
 *
 * Vive direto no <body> (fora da árvore do Inertia) porque precisa sobreviver à troca
 * Login → Dashboard. Começa idêntica ao fundo do login (continuidade), uma onda azul
 * toma a tela, saúda o usuário pelo nome e sobe como cortina revelando o sistema.
 * Clique ou tecla pula. Respeita prefers-reduced-motion (vira um fade curto).
 */

const ID = 'sie-boas-vindas';
const DURACAO_MS = 2600;
const SAIDA_MS = 750;

function saudacao(): string {
    const h = new Date().getHours();
    if (h < 12) return 'Bom dia';
    if (h < 18) return 'Boa tarde';
    return 'Boa noite';
}

/** "MARIA DA SILVA" → "Maria" (nomes costumam vir em caixa alta). */
function primeiroNome(nome: string): string {
    const p = (nome ?? '').trim().split(/\s+/)[0] ?? '';
    return p ? p.charAt(0).toLocaleUpperCase('pt-BR') + p.slice(1).toLocaleLowerCase('pt-BR') : '';
}

export function tocarBoasVindas(opts: { nome: string; logoUrl?: string | null }): void {
    if (typeof document === 'undefined' || document.getElementById(ID)) return;

    const reduzido = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    const el = document.createElement('div');
    el.id = ID;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    if (reduzido) el.classList.add('bv-reduzido');
    el.innerHTML = `
        <div class="bv-base"><div class="bv-doodles"></div></div>
        <div class="bv-onda">
            <div class="bv-brilho"></div>
            <div class="bv-doodles"></div>
        </div>
        <div class="bv-conteudo">
            <div class="bv-selo">
                <span class="bv-anel"></span>
                <span class="bv-anel bv-anel--2"></span>
                <div class="bv-logo"></div>
            </div>
            <p class="bv-ola"><span class="bv-saudacao"></span><span class="bv-nome"></span></p>
            <p class="bv-sub">Bem-vindo ao Sistema Integrado de Educação</p>
            <div class="bv-barra"><span></span></div>
        </div>`;

    const nome = primeiroNome(opts.nome);
    el.querySelector('.bv-saudacao')!.textContent = nome ? `${saudacao()},` : saudacao();
    el.querySelector('.bv-nome')!.textContent = nome;

    const logo = el.querySelector('.bv-logo') as HTMLElement;
    if (opts.logoUrl) {
        const img = document.createElement('img');
        img.src = opts.logoUrl;
        img.alt = '';
        logo.appendChild(img);
    } else {
        el.querySelector('.bv-selo')!.remove();
    }

    document.body.appendChild(el);

    let saiu = false;
    const sair = () => {
        if (saiu) return;
        saiu = true;
        el.classList.add('bv-saindo');
        window.setTimeout(() => el.remove(), reduzido ? 250 : SAIDA_MS);
        window.removeEventListener('keydown', sair);
    };

    window.setTimeout(sair, reduzido ? 1200 : DURACAO_MS);
    el.addEventListener('click', sair);
    window.addEventListener('keydown', sair);
}
