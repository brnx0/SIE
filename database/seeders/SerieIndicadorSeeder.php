<?php

namespace Database\Seeders;

use App\Models\Disciplina\Disciplina;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use App\Models\Serie\SerieIndicador;
use Illuminate\Database\Seeder;

class SerieIndicadorSeeder extends Seeder
{
    /**
     * Bloco BNCC EI02 — Educação Infantil (crianças bem pequenas).
     * Vinculado à série GRUPO 02 do segmento Creche.
     */
    public function run(): void
    {
        // Disciplinas (chave CSV → nome no banco)
        $disciplinaMap = [
            'O EU, O OUTRO E NÓS'                          => 'O EU, O OUTRO E NÓS',
            'CORPO, GESTOS E MOVIMENTOS'                   => 'CORPO, GESTOS E MOVIMENTOS',
            'TRAÇOS, SONS, CORES E FORMAS'                 => 'TRAÇOS, SONS, CORES E FORMAS',
            'ESCUTA, FALA, PENSAMENTO E IMAGINAÇÃO'        => 'ESCUTA, FALA, PENSAMENTO E IMAGINAÇÃO',
            'ESPAÇO, TEMPOS, QUANTIDADES, RELAÇÕES E TRANSFORMAÇÕES' => 'TEMPO, QUANTIDADES, RELAÇÕES E TRANSFORMAÇÕES',
        ];

        $indicadoresPorDisciplina = [
            'O EU, O OUTRO E NÓS' => [
                '(EI02EO01) Demonstrar atitudes de cuidado e solidariedade na interação com crianças e adultos.',
                '(EI02EO02) Demonstrar imagem positiva de si e confiança em sua capacidade para enfrentar dificuldades e desafios.',
                '(EI02EO03*M/BA) Compartilhar os objetos e os espaços com crianças de diferentes faixas etária, da mesma faixa etária e adultos.',
                '(EI02EO04) Comunicar-se com os colegas e os adultos, buscando compreendê-los e fazendo-se compreender.',
                '(EI02EO05) Perceber que as pessoas têm características físicas diferentes, respeitando essas diferenças.',
                '(EI02EO06) Respeitar regras básicas de convívio social nas interações e brincadeiras.',
                '(EI02EO07) Resolver conflitos nas interações e brincadeiras, com a orientação de um adulto.',
                '(TIEI02EO01) Perceber com a orientação do adulto as peculiaridades da nossa cultura, expressa nas diferentes manifestações populares (danças, músicas, artesanato, festas e crenças).',
                '(TIEI02EO02) Reconhecer a história dos povos ciganos, indígenas e afrodescendentes identificando, quais contribuições para a formação e predominância da cultura local.',
            ],
            'CORPO, GESTOS E MOVIMENTOS' => [
                '(EI02CG01) Apropriar-se de gestos e movimentos de sua cultura no cuidado de si e nos jogos e brincadeiras.',
                '(EI02CG02) Deslocar seu corpo no espaço, orientando-se por noções, como em frente, atrás, no alto, embaixo, dentro, fora etc., ao se envolver em brincadeiras e atividades de diferentes naturezas.',
                '(EI02CG03) Explorar formas de deslocamento no espaço (pular, saltar, dançar), combinando movimentos e seguindo orientações.',
                '(EI02CG04) Demonstrar progressiva independência no cuidado do seu corpo.',
                '(EI02CG05) Desenvolver progressivamente as habilidades manuais, adquirindo controle para desenhar, pintar, rasgar, folhear, entre outros.',
            ],
            'TRAÇOS, SONS, CORES E FORMAS' => [
                '(EI02TS01*M/BA) Criar, explorar e apreciar sons com materiais, objetos e instrumentos musicais, para acompanhar diversos ritmos de música.',
                '(EI02TS02) Utilizar materiais variados com possibilidades de manipulação (argila, massa de modelar), explorando cores, texturas, superfícies, planos, formas e volumes ao criar objetos tridimensionais.',
                '(EI02TS03) Utilizar diferentes fontes sonoras disponíveis no ambiente em brincadeiras cantadas, canções, músicas e melodias.',
            ],
            'ESCUTA, FALA, PENSAMENTO E IMAGINAÇÃO' => [
                '(EI02EF01) Dialogar com crianças e adultos, expressando desejos, necessidades, sentimentos e opiniões.',
                '(EI02EF02*M/BA) Conhecer e criar diferentes sons e reconhecer rimas e aliterações em cantigas de roda e textos poéticos.',
                '(EI02EF03) Demonstrar interesse e atenção ao ouvir a leitura de histórias e outros textos, diferenciando escrita de ilustrações e acompanhando, com orientação do adulto leitor, a direção da leitura (de cima para baixo, da esquerda para a direita).',
                '(EI02EF04) Formular e responder perguntas sobre fatos da história narrada, identificando cenários, personagens e principais acontecimentos.',
                '(EI02EF05) Relatar experiências e fatos acontecidos, histórias ouvidas, filmes ou peças teatrais assistidos etc.',
                '(EI02EF06) Criar e contar histórias oralmente, com base em imagens ou temas sugeridos.',
                '(EI02EF07) Manusear diferentes portadores textuais, demonstrando reconhecer seus usos sociais.',
                '(EI02EF08) Manipular textos e participar de situações de escuta, para ampliar seu contato com diferentes gêneros textuais (parlendas, histórias de aventura, tirinhas, cartazes de sala, cardápios, notícias etc.).',
                '(EI02EF09) Manusear diferentes instrumentos e suportes de escrita para desenhar, traçar letras e outros sinais gráficos.',
            ],
            'ESPAÇO, TEMPOS, QUANTIDADES, RELAÇÕES E TRANSFORMAÇÕES' => [
                '(EI02ET01) Explorar e descrever semelhanças e diferenças entre as características e propriedades dos objetos (textura, massa, tamanho).',
                '(EI02ET02) Observar, relatar e descrever incidentes do cotidiano e fenômenos naturais (luz solar, vento, chuva etc.).',
                '(EI02ET03) Compartilhar, com outras crianças, situações de cuidado de plantas e animais nos espaços da instituição e fora dela.',
                '(EI02ET04) Identificar relações espaciais (dentro e fora, em cima, embaixo, acima, abaixo, entre e do lado) e temporais (antes, durante e depois).',
                '(EI02ET05) Classificar objetos considerando determinado atributo (tamanho, peso, cor, forma etc.).',
                '(EI02ET06) Utilizar conceitos básicos de tempo (agora, antes, durante, depois, ontem, hoje, amanhã, lento, rápido, depressa, devagar).',
                '(EI02ET07) Contar oralmente objetos, pessoas, livros etc. em contextos diversos.',
                '(EI02ET08) Registrar com números a quantidade de crianças (meninas e meninos, presentes e ausentes) e a quantidade de objetos da mesma natureza (bonecas, bolas, livros etc.).',
                '(TIEI02ET01) Vivenciar, explorar e conhecer o espaço rural e a agricultura familiar.',
                '(TIEI02ET02) Participar de momentos de conscientização sobre a importância de preservar os recursos hídricos.',
                '(TIEI02ET03) Conhecer o processo histórico, percurso, contribuição, benefícios e impactos que a mina traz para o meio ambiente, economia e saúde pública do município.',
                '(TIEI02ET04) Identificar diferentes tipos de flores e plantas, valorizando o cultivo local.',
            ],
        ];

        // Séries alvo: todas as de CRECHE e PRÉ ESCOLA
        $segIds = Segmento::whereIn('seg_nome_reduzido', ['Creche', 'Pré-escolar'])
            ->pluck('seg_id')
            ->all();

        if (empty($segIds)) {
            $this->command?->warn('Segmentos Creche/Pré-escolar não encontrados — abortando SerieIndicadorSeeder.');
            return;
        }

        $series = Serie::whereIn('seg_id', $segIds)->get(['ser_id', 'ser_nome']);
        if ($series->isEmpty()) {
            $this->command?->warn('Nenhuma série encontrada em Creche/Pré-escolar — abortando SerieIndicadorSeeder.');
            return;
        }

        $disciplinas = Disciplina::whereIn('dis_nome', array_values($disciplinaMap))
            ->get()
            ->keyBy('dis_nome');

        $total = 0;
        foreach ($series as $serie) {
            foreach ($indicadoresPorDisciplina as $disciplinaCsv => $indicadores) {
                $disciplinaNome = $disciplinaMap[$disciplinaCsv] ?? null;
                $disciplina = $disciplinaNome ? $disciplinas[$disciplinaNome] ?? null : null;
                if (! $disciplina) {
                    $this->command?->warn("Disciplina não encontrada para '{$disciplinaCsv}' — pulando.");
                    continue;
                }

                $existentes = SerieIndicador::where('ind_ser_id', $serie->ser_id)
                    ->where('ind_dis_id', $disciplina->dis_id)
                    ->pluck('ind_descricao')
                    ->map(fn ($d) => mb_strtoupper(trim($d), 'UTF-8'))
                    ->flip();

                foreach ($indicadores as $desc) {
                    $desc = trim($desc);
                    $key = mb_strtoupper($desc, 'UTF-8');
                    if (isset($existentes[$key])) {
                        continue;
                    }

                    SerieIndicador::create([
                        'ind_ser_id'    => $serie->ser_id,
                        'ind_dis_id'    => $disciplina->dis_id,
                        'ind_descricao' => $desc,
                        'ind_fl_ativo'  => true,
                    ]);
                    $existentes[$key] = true;
                    $total++;
                }
            }
        }

        $this->command?->info("SerieIndicadorSeeder: {$total} indicador(es) inserido(s) em " . $series->count() . ' série(s).');
    }
}
