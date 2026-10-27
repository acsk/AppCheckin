<?php

namespace App\Services\Comunicacao;

use App\Support\AcademyDateTime;
use App\Support\Comunicacao;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios da Central (sempre restritos à academia do token). Em enquete anônima nenhuma resposta sai
 * com nome: a lista de destinatários mostra só quem participou.
 */
class ComunicacaoRelatorioService
{
    public function __construct(
        private readonly EnqueteService $enquetes,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function indicadores(int $tenantId, ?string $desde = null, ?string $ate = null): array
    {
        $publicadas = DB::table('comunicacoes as c')->where('c.tenant_id', $tenantId)->whereNull('c.deleted_at')
            ->whereIn('c.status', ['publicada', 'encerrada']);
        if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $publicadas->where('c.publicada_em', '>=', $desde.' 00:00:00');
        }
        if ($ate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            $publicadas->where('c.publicada_em', '<=', $ate.' 23:59:59');
        }
        $ids = (clone $publicadas)->pluck('c.id')->all();
        $porTipo = (clone $publicadas)->groupBy('c.tipo')->selectRaw('c.tipo, COUNT(*) as total')->pluck('total', 'tipo')->map(fn ($v) => (int) $v)->all();

        $d = DB::table('comunicacao_destinatarios as d')->join('comunicacoes as c', 'c.id', '=', 'd.comunicacao_id')
            ->where('d.tenant_id', $tenantId)->whereIn('d.comunicacao_id', $ids ?: [0]);
        $agg = (clone $d)->selectRaw("
            COUNT(*) as entregas,
            COUNT(DISTINCT d.aluno_id) as alcancados,
            SUM(CASE WHEN c.tipo <> 'enquete' THEN 1 ELSE 0 END) as entregas_leitura,
            SUM(CASE WHEN c.tipo <> 'enquete' AND d.confirmada_em IS NOT NULL THEN 1 ELSE 0 END) as leituras,
            SUM(CASE WHEN c.tipo = 'enquete' THEN 1 ELSE 0 END) as entregas_enquete,
            SUM(CASE WHEN c.tipo = 'enquete' AND d.concluida_em IS NOT NULL THEN 1 ELSE 0 END) as enquetes_respondidas
        ")->first();

        $vigentes = DB::table('comunicacao_destinatarios as d')->join('comunicacoes as c', 'c.id', '=', 'd.comunicacao_id')
            ->where('d.tenant_id', $tenantId)->where('c.tenant_id', $tenantId)->where('c.obrigatoria', 1)
            ->where(fn ($w) => $w->where(fn ($e) => $e->where('c.tipo', 'enquete')->whereNull('d.concluida_em'))
                ->orWhere(fn ($o) => $o->where('c.tipo', '<>', 'enquete')->whereNull('d.confirmada_em')));
        Comunicacao::whereVigente($vigentes, AcademyDateTime::nowFormatted());

        $taxa = fn ($a, $b) => $b > 0 ? round($a * 100 / $b, 1) : null;

        return ['status' => 200, 'body' => ['success' => true,
            'publicadas' => count($ids),
            'por_tipo' => $porTipo + array_fill_keys(array_keys(Comunicacao::TIPOS), 0),
            'alunos_alcancados' => (int) ($agg->alcancados ?? 0),
            'entregas' => (int) ($agg->entregas ?? 0),
            'leituras_confirmadas' => (int) ($agg->leituras ?? 0),
            'taxa_confirmacao' => $taxa((int) ($agg->leituras ?? 0), (int) ($agg->entregas_leitura ?? 0)),
            'enquetes_respondidas' => (int) ($agg->enquetes_respondidas ?? 0),
            'taxa_participacao' => $taxa((int) ($agg->enquetes_respondidas ?? 0), (int) ($agg->entregas_enquete ?? 0)),
            'pendencias_obrigatorias' => $vigentes->count(),
            'alunos_bloqueados' => (clone $vigentes)->distinct()->count('d.aluno_id'),
        ]];
    }

    /**
     * Quem recebeu, quem confirmou/respondeu e quem está pendente.
     *
     * @param  string  $situacao  todos | concluidos | pendentes
     * @return array{status: int, body: array<string, mixed>}
     */
    public function destinatarios(int $tenantId, int $comunicacaoId, string $situacao = 'todos', string $busca = '', int $pagina = 1, int $porPagina = 50): array
    {
        $c = DB::table('comunicacoes')->where('id', $comunicacaoId)->where('tenant_id', $tenantId)->whereNull('deleted_at')->first(['id', 'tipo']);
        if (! $c) {
            return ['status' => 404, 'body' => ['success' => false, 'error' => 'Comunicação não encontrada']];
        }
        $campo = $c->tipo === 'enquete' ? 'd.concluida_em' : 'd.confirmada_em';
        $q = DB::table('comunicacao_destinatarios as d')
            ->join('alunos as a', 'a.id', '=', 'd.aluno_id')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->where('d.tenant_id', $tenantId)->where('d.comunicacao_id', $comunicacaoId);
        if ($situacao === 'concluidos') {
            $q->whereNotNull($campo);
        } elseif ($situacao === 'pendentes') {
            $q->whereNull($campo);
        }
        if (($busca = trim($busca)) !== '') {
            $q->where(fn ($w) => $w->where('u.nome', 'like', "%{$busca}%")->orWhere('u.email', 'like', "%{$busca}%"));
        }
        $total = (clone $q)->count();
        $porPagina = max(10, min(5000, $porPagina));
        $linhas = $q->orderBy('u.nome')->offset((max(1, $pagina) - 1) * $porPagina)->limit($porPagina)
            ->get(['d.aluno_id', 'u.nome', 'u.email', 'u.telefone', 'd.disponibilizada_em', 'd.visualizada_em', 'd.confirmada_em', 'd.concluida_em'])
            ->map(fn ($r) => [
                'aluno_id' => (int) $r->aluno_id, 'nome' => $r->nome, 'email' => $r->email, 'telefone' => $r->telefone,
                'disponibilizada_em' => $r->disponibilizada_em, 'visualizada_em' => $r->visualizada_em,
                'concluida_em' => $c->tipo === 'enquete' ? $r->concluida_em : $r->confirmada_em,
                'situacao' => ($c->tipo === 'enquete' ? $r->concluida_em : $r->confirmada_em) ? 'concluido' : 'pendente',
            ])->all();

        return ['status' => 200, 'body' => ['success' => true, 'destinatarios' => $linhas,
            'paginacao' => ['pagina' => max(1, $pagina), 'por_pagina' => $porPagina, 'total' => $total, 'paginas' => (int) ceil($total / $porPagina)]]];
    }

    /**
     * Distribuição das respostas. $comAlunos (permissão de ver respostas) só identifica em enquete não anônima.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resultadosEnquete(int $tenantId, int $comunicacaoId, bool $comAlunos): array
    {
        $c = DB::table('comunicacoes')->where('id', $comunicacaoId)->where('tenant_id', $tenantId)->whereNull('deleted_at')->first(['id', 'tipo', 'configuracoes']);
        if (! $c || $c->tipo !== 'enquete') {
            return ['status' => 404, 'body' => ['success' => false, 'error' => 'Enquete não encontrada']];
        }
        $anonima = (bool) (json_decode((string) $c->configuracoes, true)['enquete']['anonima'] ?? false);

        return ['status' => 200, 'body' => ['success' => true, 'anonima' => $anonima] + $this->enquetes->resultados($tenantId, $comunicacaoId, $comAlunos && ! $anonima)];
    }

    /**
     * Respostas por aluno (exportação). Recusado em enquete anônima.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function respostasIndividuais(int $tenantId, int $comunicacaoId): array
    {
        $c = DB::table('comunicacoes')->where('id', $comunicacaoId)->where('tenant_id', $tenantId)->whereNull('deleted_at')->first(['id', 'tipo', 'configuracoes']);
        if (! $c || $c->tipo !== 'enquete') {
            return ['status' => 404, 'body' => ['success' => false, 'error' => 'Enquete não encontrada']];
        }
        if (json_decode((string) $c->configuracoes, true)['enquete']['anonima'] ?? false) {
            return ['status' => 403, 'body' => ['success' => false, 'error' => 'Enquete anônima: as respostas não são identificadas.', 'code' => 'ENQUETE_ANONIMA']];
        }
        $linhas = DB::table('enquete_respostas as r')
            ->join('enquete_perguntas as p', 'p.id', '=', 'r.pergunta_id')
            ->leftJoin('enquete_alternativas as a', 'a.id', '=', 'r.alternativa_id')
            ->join('alunos as al', 'al.id', '=', 'r.aluno_id')
            ->join('usuarios as u', 'u.id', '=', 'al.usuario_id')
            ->where('r.tenant_id', $tenantId)->where('r.comunicacao_id', $comunicacaoId)
            ->orderBy('u.nome')->orderBy('p.ordem')->orderBy('a.ordem')
            ->get(['u.nome', 'u.email', 'p.pergunta', 'p.tipo_resposta', 'a.texto as alternativa', 'r.valor_texto', 'r.valor_numero', 'r.respondida_em'])
            ->map(fn ($r) => [
                'aluno' => $r->nome, 'email' => $r->email, 'pergunta' => $r->pergunta,
                'resposta' => $r->alternativa ?? ($r->valor_numero !== null ? (string) $r->valor_numero : ($r->tipo_resposta === 'sim_nao' ? ($r->valor_texto === 'sim' ? 'Sim' : 'Não') : $r->valor_texto)),
                'respondida_em' => $r->respondida_em,
            ])->all();

        return ['status' => 200, 'body' => ['success' => true, 'respostas' => $linhas]];
    }
}
