<?php

namespace App\Services\Comunicacao;

use App\Services\AppConfigService;
use App\Support\AcademyDateTime;
use App\Support\Comunicacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicações obrigatórias que impedem o check-in do aluno: vigentes, destinadas a ele e ainda sem a
 * ação exigida (leitura confirmada ou enquete enviada por inteiro). Única fonte da regra para todos os
 * fluxos de check-in da API v2 (a API Slim replica a mesma consulta).
 *
 * Com a Central desligada para a academia (configuração remota) nada bloqueia: o aluno não teria como
 * resolver. A versão do app não entra na avaliação, para um build antigo não escapar da trava.
 */
class PendenciaComunicacaoService
{
    public const CODIGO = 'COMUNICACOES_OBRIGATORIAS_PENDENTES';

    private static ?bool $tabelas = null;

    public function __construct(
        private readonly AppConfigService $config,
    ) {}

    /**
     * @return list<array{id: int, tipo: string, titulo: string, acao_exigida: string}>
     */
    public function listarPendenciasObrigatorias(int $tenantId, int $alunoId, bool $bloquear = false): array
    {
        if ($alunoId <= 0 || ! self::tabelasExistem()) {
            return [];
        }

        $q = DB::table('comunicacao_destinatarios as d')
            ->join('comunicacoes as c', 'c.id', '=', 'd.comunicacao_id')
            ->where('d.tenant_id', $tenantId)
            ->where('d.aluno_id', $alunoId)
            ->where('c.tenant_id', $tenantId)
            ->where('c.obrigatoria', 1)
            ->where(fn ($w) => $w->where(fn ($e) => $e->where('c.tipo', 'enquete')->whereNull('d.concluida_em'))
                ->orWhere(fn ($o) => $o->where('c.tipo', '<>', 'enquete')->whereNull('d.confirmada_em')));
        Comunicacao::whereVigente($q, AcademyDateTime::nowFormatted());
        $q->orderByDesc('c.prioridade')->orderBy('c.inicio_em')->orderBy('c.id');
        if ($bloquear) {
            $q->lockForUpdate();
        }

        return $q->get(['c.id', 'c.tipo', 'c.titulo'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'tipo' => (string) $r->tipo, 'titulo' => (string) $r->titulo, 'acao_exigida' => Comunicacao::acaoExigida((string) $r->tipo)])
            ->all();
    }

    public function centralAtiva(int $tenantId, ?int $usuarioId = null): bool
    {
        return $this->config->funcionalidadeAtiva('comunicacao', ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId]);
    }

    /**
     * Null quando o check-in pode seguir; senão a resposta 409 de domínio.
     * Use $bloquear dentro da transação que grava o check-in (trava as linhas do aluno até o commit,
     * inclusive contra uma publicação simultânea que inclua novas linhas para ele).
     *
     * @return array{status: int, body: array<string, mixed>}|null
     */
    public function verificarPermissaoCheckin(int $tenantId, int $alunoId, ?int $usuarioId = null, bool $bloquear = false, bool $registradoPelaEquipe = false): ?array
    {
        if (! self::tabelasExistem() || ! $this->centralAtiva($tenantId, $usuarioId)) {
            return null;
        }
        $pendencias = $this->listarPendenciasObrigatorias($tenantId, $alunoId, $bloquear);
        if ($pendencias === []) {
            return null;
        }

        return ['status' => 409, 'body' => self::corpo409($pendencias, $registradoPelaEquipe)];
    }

    /**
     * @param  list<array{id: int, tipo: string, titulo: string, acao_exigida: string}>  $pendencias
     * @return array<string, mixed>
     */
    public static function corpo409(array $pendencias, bool $registradoPelaEquipe = false): array
    {
        $total = count($pendencias);
        $mensagem = $registradoPelaEquipe
            ? "O aluno tem {$total} ".($total === 1 ? 'comunicação obrigatória pendente' : 'comunicações obrigatórias pendentes').'. Ele precisa resolver pelo app antes do check-in.'
            : 'Existem comunicações obrigatórias pendentes.';

        return [
            'success' => false,
            'codigo' => self::CODIGO,
            'code' => self::CODIGO,
            'mensagem' => $mensagem,
            'error' => $mensagem,
            'checkin_permitido' => false,
            'total_pendencias' => $total,
            'pendencias' => $pendencias,
        ];
    }

    private static function tabelasExistem(): bool
    {
        return self::$tabelas ??= Schema::hasTable('comunicacao_destinatarios') && Schema::hasTable('comunicacoes');
    }

    /** Testes recriam o schema entre casos. */
    public static function esquecerSchema(): void
    {
        self::$tabelas = null;
    }
}
