<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditoriaController extends Controller
{
    /** event → rótulo em pt-BR. */
    private const ACAO_LABEL = [
        'created'  => 'Criou',
        'updated'  => 'Alterou',
        'deleted'  => 'Excluiu',
        'restored' => 'Restaurou',
    ];

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true)
            ? (int) $request->input('per_page')
            : 25;

        $query = Activity::query()
            ->with('causer:id,name')
            ->latest('id')
            ->when($request->filled('causer'), fn ($q) => $q->where('causer_id', $request->integer('causer')))
            ->when($request->filled('entidade'), fn ($q) => $q->where('log_name', $request->string('entidade')->toString()))
            ->when($request->filled('acao'), fn ($q) => $q->where('event', $request->string('acao')->toString()))
            ->when($request->filled('de'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('de')))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('ate')))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%' . $request->string('busca')->toString() . '%';
                $q->where(fn ($w) => $w
                    ->where('description', 'ilike', $termo)
                    ->orWhere('log_name', 'ilike', $termo)
                    ->orWhereHas('causer', fn ($c) => $c->where('name', 'ilike', $termo)));
            });

        $page = $query->paginate($perPage)->withQueryString();
        $page->getCollection()->transform(fn (Activity $a) => $this->mapAtividade($a));

        return Inertia::render('auditoria/Index', [
            'atividades' => $page,
            'filters'    => [
                'busca'    => $request->string('busca')->toString(),
                'causer'   => $request->input('causer'),
                'entidade' => $request->string('entidade')->toString(),
                'acao'     => $request->string('acao')->toString(),
                'de'       => $request->string('de')->toString(),
                'ate'      => $request->string('ate')->toString(),
                'per_page' => $perPage,
            ],
            'entidades' => $this->entidadesDisponiveis(),
            'usuarios'  => $this->usuariosComAtividade(),
            'acoes'     => collect(self::ACAO_LABEL)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    private function mapAtividade(Activity $a): array
    {
        // v5 grava as mudanças na coluna attribute_changes: {attributes:{...}, old:{...}}
        $changes = $a->attribute_changes;
        $novos   = (array) data_get($changes, 'attributes', []);
        $antigos = (array) data_get($changes, 'old', []);

        $campos = collect(array_keys($novos ?: $antigos))->map(fn ($campo) => [
            'campo' => $campo,
            'de'    => $this->fmt($antigos[$campo] ?? null),
            'para'  => $this->fmt($novos[$campo] ?? null),
        ])->all();

        return [
            'id'          => $a->id,
            'quando'      => optional($a->created_at)->toIso8601String(),
            'usuario'     => $a->causer?->name ?? 'Sistema',
            'acao'        => self::ACAO_LABEL[$a->event] ?? $a->event ?? $a->description,
            'acao_key'    => $a->event,
            'entidade'    => $a->log_name,
            'registro_id' => $a->subject_id,
            'mudancas'    => $campos,
        ];
    }

    private function fmt($v): string
    {
        if ($v === null || $v === '') return '—';
        if (is_bool($v)) return $v ? 'Sim' : 'Não';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return (string) $v;
    }

    private function entidadesDisponiveis(): array
    {
        return Activity::query()
            ->select('log_name')
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->all();
    }

    private function usuariosComAtividade(): array
    {
        return DB::table('activity_log as a')
            ->join('users as u', 'u.id', '=', 'a.causer_id')
            ->select('u.id', 'u.name')
            ->distinct()
            ->orderBy('u.name')
            ->get()
            ->all();
    }
}
