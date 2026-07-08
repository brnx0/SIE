<?php

namespace App\Services\Censo;

use App\Models\Censo\CensoLote;
use App\Services\Censo\Contracts\LayoutVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orquestra a importação do Censo. Fase A (stage): lê o arquivo de migração,
 * detecta a versão do layout, parseia linha-a-linha e grava em staging (imp_censo_*).
 * NÃO toca tabelas edu_* — isso é a fase B (commit), a implementar.
 *
 * Ver docs/regras/importacao_censo.md.
 */
class CensoImportManager
{
    private const INSERT_CHUNK = 500;

    public function __construct(private LayoutResolver $resolver)
    {
    }

    /**
     * Fase A — parse + staging.
     *
     * @param  string    $path       caminho do .txt (encoding ISO-8859-1)
     * @param  int       $anlId      ano letivo destino (escolhido no upload)
     * @param  int|null  $createdBy  id do usuário (nulo em CLI)
     */
    public function stage(string $path, int $anlId, ?int $createdBy = null): CensoLote
    {
        if (! is_file($path)) {
            throw new RuntimeException("Arquivo não encontrado: {$path}");
        }

        $lote = CensoLote::create([
            'icl_arquivo_nome' => basename($path),
            'icl_arquivo_path' => $path,
            'icl_anl_id'       => $anlId,
            'icl_status'       => 'PENDENTE',
            'icl_created_by'   => $createdBy,
        ]);

        return $this->runStage($lote);
    }

    /**
     * Parseia o arquivo de um lote já criado (PENDENTE) para o staging.
     * Reusável pelo Job da tela de upload.
     */
    public function runStage(CensoLote $lote): CensoLote
    {
        $path = $lote->icl_arquivo_path;
        if (! $path || ! is_file($path)) {
            throw new RuntimeException("Arquivo do lote não encontrado: {$path}");
        }

        $version = $this->resolver->detect($this->sampleSignature($path));

        // Reparse limpo: descarta staging anterior deste lote.
        $lote->registros()->delete();

        [$totalLinhas, $totalErros, $contadores] = $this->parseIntoStaging($path, $version, (int) $lote->icl_id);

        $lote->update([
            'icl_versao_layout' => $version->year(),
            'icl_status'        => 'PARSEADO',
            'icl_total_linhas'  => $totalLinhas,
            'icl_total_erros'   => $totalErros,
            'icl_contadores'    => $contadores,
        ]);

        return $lote->refresh();
    }

    /**
     * 1ª passada leve: amostra o nº de campos por tipo de registro para detecção.
     *
     * @return array<string,int>
     */
    private function sampleSignature(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir: {$path}");
        }

        $sig = [];
        $seen = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $tipo = substr($line, 0, 2);
            if (! isset($sig[$tipo])) {
                $sig[$tipo] = substr_count($line, '|') + 1;
            }
            // basta ver um punhado de tipos distintos
            if (++$seen > 5000 && count($sig) >= 6) {
                break;
            }
        }
        fclose($handle);

        unset($sig['99']); // marcador de fim não entra na assinatura

        return $sig;
    }

    /**
     * 2ª passada: parseia e insere em imp_censo_registro em lotes.
     *
     * @return array{0:int,1:int,2:array<string,int>}  [totalLinhas, totalErros, contadores]
     */
    private function parseIntoStaging(string $path, LayoutVersion $version, int $loteId): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir: {$path}");
        }

        $tiposValidos = $version->tiposValidos();
        $signature = $version->signature();

        $buffer = [];
        $totalLinhas = 0;
        $totalErros = 0;
        $contadores = [];
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $totalLinhas++;

            $tipo = substr($line, 0, 2);
            if ($tipo === '99') {
                continue; // fim do arquivo
            }

            $campos = array_map(
                fn ($v) => $this->toUtf8($v),
                explode('|', $line)
            );

            [$status, $erro] = $this->validate($tipo, $campos, $tiposValidos, $signature);
            if ($status === 'ERRO') {
                $totalErros++;
            }

            $contadores[$tipo] = ($contadores[$tipo] ?? 0) + 1;

            $buffer[] = [
                'icr_icl_id'     => $loteId,
                'icr_linha'      => $totalLinhas,
                'icr_tipo'       => $tipo,
                'icr_campos'     => json_encode($campos, JSON_UNESCAPED_UNICODE),
                'icr_dados'      => json_encode($this->mapFields($version, $tipo, $campos), JSON_UNESCAPED_UNICODE),
                'icr_status'     => $status,
                'icr_erro'       => $erro,
                'icr_created_at' => $now,
            ];

            if (count($buffer) >= self::INSERT_CHUNK) {
                DB::table('imp_censo_registro')->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('imp_censo_registro')->insert($buffer);
        }
        fclose($handle);

        ksort($contadores);

        return [$totalLinhas, $totalErros, $contadores];
    }

    /**
     * Aplica o fieldMap da versão sobre o array bruto (posição 1-based).
     *
     * @param  array<int,string>  $campos  (0-indexed após explode)
     * @return array<string,string|null>
     */
    private function mapFields(LayoutVersion $version, string $tipo, array $campos): array
    {
        $dados = [];
        foreach ($version->fieldMap($tipo) as $pos => $nome) {
            $dados[$nome] = $campos[$pos - 1] ?? null; // pos 1-based → índice pos-1
        }

        return $dados;
    }

    /**
     * Validação básica de fase A.
     *
     * @return array{0:string,1:?string}  [status, erro]
     */
    private function validate(string $tipo, array $campos, array $tiposValidos, array $signature): array
    {
        if (! in_array($tipo, $tiposValidos, true)) {
            return ['ERRO', "Tipo de registro desconhecido: {$tipo}"];
        }

        $esperado = $signature[$tipo] ?? null;
        $qtd = count($campos);
        if ($esperado !== null && $qtd !== $esperado) {
            return ['AVISO', "Nº de campos ({$qtd}) diverge do esperado ({$esperado})."];
        }

        // Identidade mínima: INEP da escola (col 2) presente em todos os registros.
        if (($campos[1] ?? '') === '') {
            return ['ERRO', 'INEP da escola (coluna 2) ausente.'];
        }

        return ['OK', null];
    }

    /** Converte para UTF-8 a partir de ISO-8859-1 quando necessário. */
    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }
}
