<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aluno\StoreAlunoRequest;
use App\Http\Requests\Aluno\UpdateAlunoRequest;
use App\Models\Aluno\Aluno;
use App\Models\Parametro\ParametroEntidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlunoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('alunos/Index', [
            'alunos'  => $this->baseQuery($request)->paginate($perPage)->withQueryString(),
            'filters' => [
                'search'   => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $alunos = $this->baseQuery($request)->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($alunos, $request);
        }

        return $this->exportCsv($alunos);
    }

    public function create(): Response
    {
        return Inertia::render('alunos/Create');
    }

    public function store(StoreAlunoRequest $request): RedirectResponse
    {
        $this->checkHomonimo($request);

        $aluno = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['saude', 'aln_foto']);

            if ($request->hasFile('aln_foto')) {
                $data['aln_foto'] = $request->file('aln_foto')->store('alunos', 'public');
            }

            $params = ParametroEntidade::firstOrFail();
            if ($params->par_fl_gerar_matricula_auto && empty($data['aln_nr_matricula'])) {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [727301]);
                $maxMatricula = (int) DB::table('edu_aluno')->max('aln_nr_matricula');
                $data['aln_nr_matricula'] = $maxMatricula + 1;
            }

            $aluno = Aluno::create($data);
            $aluno->saude()->create($request->input('saude', []));

            return $aluno;
        });

        return to_route('alunos.edit', $aluno)->with('success', 'Aluno cadastrado com sucesso.');
    }

    public function edit(Aluno $aluno): Response
    {
        $aluno->load(['municipioNascimento:mun_id,mun_nome,mun_uf', 'saude']);

        $matriculas = $aluno->matriculas()
            ->with([
                'turma.escola:esc_id,esc_nome',
                'turma.segmento:seg_id,seg_nome',
                'turma.serie:ser_id,ser_nome',
                'turma.anoLetivo:anl_id,anl_ano',
                'situacaoSaida:tas_cod,tas_descricao',
            ])
            ->orderByDesc('tma_dt_matricula')
            ->get([
                'tma_id', 'tma_tur_id', 'tma_situacao',
                'tma_dt_matricula', 'tma_tas_cod_saida',
            ]);

        return Inertia::render('alunos/Edit', [
            'aluno'      => $aluno,
            'matriculas' => $matriculas->map(fn ($m) => [
                'tma_id'           => $m->tma_id,
                'tma_situacao'     => $m->tma_situacao,
                'tma_dt_matricula' => $m->tma_dt_matricula?->format('Y-m-d'),
                'situacao_saida'   => $m->situacaoSaida?->tas_descricao,
                'anl_ano'          => $m->turma?->anoLetivo?->anl_ano,
                'esc_nome'         => $m->turma?->escola?->esc_nome,
                'tur_turno'        => $m->turma?->tur_turno,
                'seg_nome'         => $m->turma?->segmento?->seg_nome,
                'ser_nome'         => $m->turma?->serie?->ser_nome,
            ]),
        ]);
    }

    public function update(UpdateAlunoRequest $request, Aluno $aluno): RedirectResponse
    {
        $this->checkHomonimo($request, $aluno->aln_id);

        DB::transaction(function () use ($request, $aluno) {
            $data = $request->safe()->except(['saude', 'aln_foto']);

            if ($request->hasFile('aln_foto')) {
                if ($aluno->aln_foto) {
                    Storage::disk('public')->delete($aluno->aln_foto);
                }
                $data['aln_foto'] = $request->file('aln_foto')->store('alunos', 'public');
            }

            $aluno->update($data);

            $saudeData = $request->input('saude', []);
            if ($aluno->saude) {
                $aluno->saude->update($saudeData);
            } else {
                $aluno->saude()->create($saudeData);
            }
        });

        return to_route('alunos.edit', $aluno)->with('success', 'Aluno atualizado com sucesso.');
    }

    public function destroy(Aluno $aluno): RedirectResponse
    {
        if ($aluno->aln_foto) {
            Storage::disk('public')->delete($aluno->aln_foto);
        }

        return $this->safeDelete($aluno)
            ?? to_route('alunos.index')->with('success', 'Aluno removido com sucesso.');
    }

    protected function checkHomonimo(Request $request, ?int $excludeId = null): void
    {
        $params = ParametroEntidade::firstOrFail();

        if (! $params->par_fl_alertar_homonimos) return;
        if ($request->boolean('confirm_homonimo')) return;

        $nome   = $request->input('aln_nome');
        $dtNasc = $request->input('aln_dt_nascimento');

        if (! $nome || ! $dtNasc) return;

        $matches = Aluno::query()
            ->whereRaw('unaccent(upper(aln_nome)) = unaccent(upper(?))', [$nome])
            ->whereDate('aln_dt_nascimento', $dtNasc)
            ->when($excludeId, fn ($q) => $q->where('aln_id', '!=', $excludeId))
            ->orderBy('aln_id')
            ->limit(20)
            ->get(['aln_id', 'aln_nome', 'aln_dt_nascimento', 'aln_cpf', 'aln_nr_matricula']);

        if ($matches->isEmpty()) return;

        $payload = $matches->map(fn ($a) => [
            'aln_id'             => $a->aln_id,
            'aln_nome'           => $a->aln_nome,
            'aln_dt_nascimento'  => $a->aln_dt_nascimento?->format('Y-m-d'),
            'aln_cpf'            => $a->aln_cpf,
            'aln_nr_matricula'   => $a->aln_nr_matricula,
        ])->all();

        throw ValidationException::withMessages([
            'homonimo' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function baseQuery(Request $request)
    {
        $search = $request->string('search')->toString();

        return Aluno::query()
            ->with('municipioNascimento:mun_id,mun_nome,mun_uf')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('aln_nome', 'ilike', "%{$search}%")
                        ->orWhere('aln_cpf', 'like', "%{$search}%")
                        ->orWhere('aln_nr_matricula', 'like', "%{$search}%");
                });
            })
            ->orderBy('aln_nome');
    }

    private function exportCsv($alunos): StreamedResponse
    {
        $filename = 'alunos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($alunos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Matrícula', 'Nome', 'CPF', 'Nascimento', 'Município', 'UF'], ';');
            foreach ($alunos as $a) {
                $cpf = $a->aln_cpf
                    ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $a->aln_cpf)
                    : '';
                fputcsv($out, [
                    $a->aln_nr_matricula ?? '',
                    $a->aln_nome,
                    $cpf,
                    $a->aln_dt_nascimento?->format('d/m/Y') ?? '',
                    $a->municipioNascimento?->mun_nome ?? '',
                    $a->municipioNascimento?->mun_uf ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($alunos, Request $request): HttpResponse
    {
        $collection = collect($alunos);
        $filename   = 'alunos_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.alunos_pdf', [
            'alunos'  => $collection,
            'total'   => $collection->count(),
            'search'  => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
