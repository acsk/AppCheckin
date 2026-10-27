<?php

namespace App\Services\Comunicacao;

use App\Support\AcademyDateTime;
use App\Support\Comunicacao;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Central de Comunicação do aluno no app. O aluno é sempre o do token na academia do token: id de outra
 * pessoa ou de outra academia simplesmente não é encontrado (404).
 *
 * Visível: publicada, já iniciada e (vigente ou, se encerrada, com manter_historico). Cancelada some.
 */
class MobileComunicacaoService
{
    public function __construct(
        private readonly PendenciaComunicacaoService $pendencias,
        private readonly EnqueteService $enquetes,
        private readonly ComunicacaoService $comunicacoes,
    ) {}

    /** Aluno do usuário com papel de aluno ativo na academia, ou null. */
    public function alunoDoUsuario(int $usuarioId, int $tenantId): ?int
    {
        $id = DB::table('alunos as a')
            ->join('tenant_usuario_papel as tup', 'tup.usuario_id', '=', 'a.usuario_id')
            ->where('a.usuario_id', $usuarioId)->where('tup.tenant_id', $tenantId)
            ->where('tup.papel_id', 1)->where('tup.ativo', 1)
            ->value('a.id');

        return $id ? (int) $id : null;
    }

    /**
     * Badge do sino, pendências que travam o check-in e destaques a abrir como modal.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resumo(int $usuarioId, ?int $tenantId): array
    {
        $alunoId = $tenantId ? $this->alunoDoUsuario($usuarioId, $tenantId) : null;
        if (! $alunoId) {
            return $this->ok(['nao_lidas' => 0, 'total_pendencias' => 0, 'pendencias' => [], 'destaques' => [], 'checkin_permitido' => true]);
        }
        $agora = AcademyDateTime::nowFormatted();
        $vigentes = $this->visiveis($tenantId, $alunoId, $agora, true);
        $naoLidas = (clone $vigentes)->whereNull('d.visualizada_em')->count();
        $destaques = (clone $vigentes)->where('c.exibir_modal', 1)
            ->where(fn ($w) => $w->whereNull('d.visualizada_em')->orWhere(fn ($o) => $o->where('c.obrigatoria', 1)->where(fn ($r) => $r
                ->where(fn ($e) => $e->where('c.tipo', 'enquete')->whereNull('d.concluida_em'))
                ->orWhere(fn ($x) => $x->where('c.tipo', '<>', 'enquete')->whereNull('d.confirmada_em')))))
            ->orderByDesc('c.obrigatoria')->orderByDesc('c.prioridade')->orderBy('c.inicio_em')
            ->pluck('c.id')->map(fn ($id) => (int) $id)->all();
        $pendencias = $this->pendencias->centralAtiva($tenantId, $usuarioId) ? $this->pendencias->listarPendenciasObrigatorias($tenantId, $alunoId) : [];

        return $this->ok([
            'nao_lidas' => $naoLidas,
            'total_pendencias' => count($pendencias),
            'pendencias' => $pendencias,
            'destaques' => $destaques,
            'checkin_permitido' => $pendencias === [],
        ]);
    }

    /**
     * @param  string  $filtro  todas | nao_lidas | obrigatorias | enquetes
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $usuarioId, ?int $tenantId, string $filtro = 'todas', int $pagina = 1): array
    {
        $alunoId = $tenantId ? $this->alunoDoUsuario($usuarioId, $tenantId) : null;
        if (! $alunoId) {
            return $this->ok(['comunicacoes' => [], 'paginacao' => ['pagina' => 1, 'total' => 0, 'paginas' => 0]]);
        }
        $agora = AcademyDateTime::nowFormatted();
        $q = $this->visiveis($tenantId, $alunoId, $agora, false);
        match ($filtro) {
            'nao_lidas' => $q->whereNull('d.visualizada_em'),
            'obrigatorias' => $q->where('c.obrigatoria', 1),
            'enquetes' => $q->where('c.tipo', 'enquete'),
            default => null,
        };
        $total = (clone $q)->count();
        $porPagina = 30;
        $linhas = $q->orderByRaw('CASE WHEN (c.obrigatoria = 1 AND ((c.tipo = ? AND d.concluida_em IS NULL) OR (c.tipo <> ? AND d.confirmada_em IS NULL))) THEN 0 ELSE 1 END', ['enquete', 'enquete'])
            ->orderByDesc('c.prioridade')->orderByDesc('c.inicio_em')->orderByDesc('c.id')
            ->offset((max(1, $pagina) - 1) * $porPagina)->limit($porPagina)
            ->get($this->colunas())->map(fn ($r) => $this->formatar((array) $r, $agora))->all();

        return $this->ok(['comunicacoes' => $linhas, 'paginacao' => ['pagina' => max(1, $pagina), 'total' => $total, 'paginas' => (int) ceil($total / $porPagina)]]);
    }

    /**
     * Abre a comunicação (marca como visualizada; notificação opcional também conta como lida).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function detalhe(int $usuarioId, ?int $tenantId, int $id): array
    {
        $alunoId = $tenantId ? $this->alunoDoUsuario($usuarioId, $tenantId) : null;
        $agora = AcademyDateTime::nowFormatted();
        $r = $alunoId ? $this->visiveis($tenantId, $alunoId, $agora, false)->where('c.id', $id)->first($this->colunas()) : null;
        if (! $r) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        $r = (array) $r;
        $marcar = ['visualizada_em' => $r['visualizada_em'] ?? $agora];
        if ($r['tipo'] === 'notificacao' && ! $r['obrigatoria'] && $r['confirmada_em'] === null) {
            $marcar += ['confirmada_em' => $agora, 'confirmada_por_usuario_id' => $usuarioId];
        }
        if ($r['visualizada_em'] === null || isset($marcar['confirmada_em'])) {
            DB::table('comunicacao_destinatarios')->where('tenant_id', $tenantId)->where('comunicacao_id', $id)->where('aluno_id', $alunoId)
                ->update($marcar + ['updated_at' => now()]);
            $r = $marcar + $r;
        }

        $item = $this->formatar($r, $agora) + [
            'conteudo' => $r['conteudo'],
            'configuracoes' => array_diff_key(json_decode((string) $r['configuracoes'], true) ?: [], ['canais' => 1]),
        ];
        if ($r['tipo'] === 'enquete') {
            $config = $item['configuracoes']['enquete'] ?? [];
            $item['perguntas'] = $this->comunicacoes->perguntas($tenantId, $id);
            $item['minhas_respostas'] = empty($config['anonima']) ? (object) $this->enquetes->respostasDoAluno($tenantId, $id, $alunoId) : (object) [];
            $item['pode_responder'] = $item['status'] === 'ativa' && ($item['concluida'] === false || ! empty($config['permitir_alterar']));
            if (! empty($config['exibir_resultados']) && ($item['concluida'] || $item['status'] === 'encerrada')) {
                $item['resultados'] = $this->enquetes->resultados($tenantId, $id);
            }
            if (! empty($config['exibir_participantes'])) {
                $item['participantes'] = DB::table('comunicacao_destinatarios')->where('tenant_id', $tenantId)->where('comunicacao_id', $id)->whereNotNull('concluida_em')->count();
            }
        }

        return $this->ok(['comunicacao' => $item]);
    }

    /**
     * "Li e estou ciente". Idempotente: confirmar de novo não muda o registro.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function confirmarLeitura(int $usuarioId, ?int $tenantId, int $id): array
    {
        $alunoId = $tenantId ? $this->alunoDoUsuario($usuarioId, $tenantId) : null;
        $agora = AcademyDateTime::nowFormatted();
        $r = $alunoId ? $this->visiveis($tenantId, $alunoId, $agora, false)->where('c.id', $id)->first(['c.id', 'c.tipo', 'd.confirmada_em']) : null;
        if (! $r) {
            return $this->erro('Comunicação não encontrada', 404);
        }
        if ($r->tipo === 'enquete') {
            return $this->erro('Enquete se conclui enviando as respostas.', 422);
        }
        $mudou = DB::table('comunicacao_destinatarios')->where('tenant_id', $tenantId)->where('comunicacao_id', $id)
            ->where('aluno_id', $alunoId)->whereNull('confirmada_em')
            ->update(['confirmada_em' => $agora, 'confirmada_por_usuario_id' => $usuarioId, 'visualizada_em' => DB::raw('COALESCE(visualizada_em, '.DB::getPdo()->quote($agora).')'), 'updated_at' => now()]);
        if ($mudou) {
            $this->comunicacoes->auditar($tenantId, $id, 'leitura_confirmada', $usuarioId, $alunoId);
        }

        return $this->ok(['ja_confirmada' => ! $mudou, 'message' => 'Leitura confirmada'] + $this->pendenciasRestantes($tenantId, $alunoId, $usuarioId));
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function responderEnquete(int $usuarioId, ?int $tenantId, int $id, mixed $respostas): array
    {
        $alunoId = $tenantId ? $this->alunoDoUsuario($usuarioId, $tenantId) : null;
        if (! $alunoId) {
            return $this->erro('Enquete não encontrada ou encerrada.', 404);
        }
        $r = $this->enquetes->registrarRespostaEnquete($tenantId, $alunoId, $usuarioId, $id, $respostas);
        if ($r['status'] !== 200) {
            return $r;
        }

        return $this->ok(array_diff_key($r['body'], ['success' => 1]) + $this->pendenciasRestantes($tenantId, $alunoId, $usuarioId));
    }

    /** @return array{total_pendencias: int, checkin_permitido: bool} */
    private function pendenciasRestantes(int $tenantId, int $alunoId, int $usuarioId): array
    {
        $n = $this->pendencias->centralAtiva($tenantId, $usuarioId) ? count($this->pendencias->listarPendenciasObrigatorias($tenantId, $alunoId)) : 0;

        return ['total_pendencias' => $n, 'checkin_permitido' => $n === 0];
    }

    private function visiveis(int $tenantId, int $alunoId, string $agora, bool $somenteVigentes): Builder
    {
        $q = DB::table('comunicacao_destinatarios as d')
            ->join('comunicacoes as c', 'c.id', '=', 'd.comunicacao_id')
            ->where('d.tenant_id', $tenantId)->where('d.aluno_id', $alunoId)->where('c.tenant_id', $tenantId);
        if ($somenteVigentes) {
            return Comunicacao::whereVigente($q, $agora);
        }

        return $q->whereNull('c.deleted_at')->whereIn('c.status', ['publicada', 'encerrada'])
            ->where(fn ($w) => $w->whereNull('c.inicio_em')->orWhere('c.inicio_em', '<=', $agora))
            ->where(fn ($w) => $w->where('c.manter_historico', 1)
                ->orWhere(fn ($v) => $v->where('c.status', 'publicada')->where(fn ($t) => $t->whereNull('c.termino_em')->orWhere('c.termino_em', '>', $agora))));
    }

    /** @return list<string> */
    private function colunas(): array
    {
        return ['c.id', 'c.tipo', 'c.titulo', 'c.descricao', 'c.conteudo', 'c.icone', 'c.classificacao', 'c.prioridade', 'c.obrigatoria',
            'c.exibir_modal', 'c.inicio_em', 'c.termino_em', 'c.status', 'c.configuracoes', 'c.publicada_em',
            'd.visualizada_em', 'd.confirmada_em', 'd.concluida_em'];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function formatar(array $r, string $agora): array
    {
        $status = Comunicacao::statusEfetivo((string) $r['status'], $r['inicio_em'], $r['termino_em'], $agora);
        $concluida = $r['tipo'] === 'enquete' ? $r['concluida_em'] !== null : $r['confirmada_em'] !== null;
        $config = json_decode((string) ($r['configuracoes'] ?? ''), true) ?: [];

        return [
            'id' => (int) $r['id'], 'tipo' => $r['tipo'], 'tipo_nome' => Comunicacao::TIPOS[$r['tipo']] ?? $r['tipo'],
            'titulo' => $r['titulo'], 'descricao' => $r['descricao'], 'icone' => $r['icone'], 'classificacao' => $r['classificacao'],
            'prioridade' => (int) $r['prioridade'], 'obrigatoria' => (bool) $r['obrigatoria'], 'exibir_modal' => (bool) $r['exibir_modal'],
            'inicio_em' => $r['inicio_em'] ?? $r['publicada_em'], 'termino_em' => $r['termino_em'], 'status' => $status,
            'imagem_url' => $config['imagem_url'] ?? null,
            'lida' => $r['visualizada_em'] !== null, 'concluida' => $concluida,
            'acao_exigida' => Comunicacao::acaoExigida($r['tipo']),
            'pendente' => (bool) $r['obrigatoria'] && ! $concluida && $status === 'ativa',
            'visualizada_em' => $r['visualizada_em'], 'confirmada_em' => $r['confirmada_em'], 'concluida_em' => $r['concluida_em'],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function ok(array $body): array
    {
        return ['status' => 200, 'body' => ['success' => true, 'message' => $body['message'] ?? null, 'data' => $body]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => $mensagem, 'message' => $mensagem]];
    }
}
