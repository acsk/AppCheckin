<?php

namespace App\Services;

use PDO;

/**
 * Comunicações obrigatórias que travam o check-in (mesma regra de PendenciaComunicacaoService da API v2).
 * Os builds antigos que ainda fazem check-in por aqui não têm a Central: a resposta 409 pede para
 * atualizar o app, onde a pendência é resolvida.
 *
 * Central desligada na configuração remota publicada (funcionalidade, bloqueio ou rollout) não trava.
 */
class ComunicacaoPendenciaService
{
    public const CODIGO = 'COMUNICACOES_OBRIGATORIAS_PENDENTES';

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null corpo do 409, ou null quando o check-in pode seguir
     */
    public function verificarPermissaoCheckin(int $tenantId, int $alunoId, ?int $usuarioId = null, bool $registradoPelaEquipe = false): ?array
    {
        if ($tenantId <= 0 || $alunoId <= 0) {
            return null;
        }
        try {
            if (!$this->centralAtiva($tenantId, $usuarioId)) {
                return null;
            }
            $agora = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                SELECT c.id, c.tipo, c.titulo
                FROM comunicacao_destinatarios d
                INNER JOIN comunicacoes c ON c.id = d.comunicacao_id
                WHERE d.tenant_id = :t1 AND d.aluno_id = :aluno AND c.tenant_id = :t2
                  AND c.obrigatoria = 1 AND c.status = 'publicada' AND c.deleted_at IS NULL
                  AND (c.inicio_em IS NULL OR c.inicio_em <= :agora1)
                  AND (c.termino_em IS NULL OR c.termino_em > :agora2)
                  AND ((c.tipo = 'enquete' AND d.concluida_em IS NULL) OR (c.tipo <> 'enquete' AND d.confirmada_em IS NULL))
                ORDER BY c.prioridade DESC, c.inicio_em, c.id
            ");
            $stmt->execute(['t1' => $tenantId, 't2' => $tenantId, 'aluno' => $alunoId, 'agora1' => $agora, 'agora2' => $agora]);
            $pendencias = array_map(fn ($r) => [
                'id' => (int) $r['id'],
                'tipo' => (string) $r['tipo'],
                'titulo' => (string) $r['titulo'],
                'acao_exigida' => $r['tipo'] === 'enquete' ? 'responder' : 'confirmar_leitura',
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\PDOException $e) {
            // Tabelas ainda não criadas (migration da API v2 pendente)
            if (str_contains($e->getMessage(), "doesn't exist") || ($e->errorInfo[1] ?? null) === 1146) {
                return null;
            }
            throw $e;
        }
        if ($pendencias === []) {
            return null;
        }
        $total = count($pendencias);
        $mensagem = $registradoPelaEquipe
            ? "O aluno tem {$total} " . ($total === 1 ? 'comunicação obrigatória pendente' : 'comunicações obrigatórias pendentes') . '. Ele precisa resolver pelo app antes do check-in.'
            : 'Existem comunicações obrigatórias pendentes. Atualize o aplicativo para ver e confirmar.';

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

    /** Aluno do usuário (cadastro único entre academias). */
    public function alunoDoUsuario(int $usuarioId): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM alunos WHERE usuario_id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    /** Mesma leitura do AppConfigResolver da v2 (academia ?? global ?? padrão ligado; bloqueio e rollout globais). */
    private function centralAtiva(int $tenantId, ?int $usuarioId): bool
    {
        try {
            $conteudo = $this->db->query('SELECT conteudo FROM app_config_publicacoes ORDER BY id DESC LIMIT 1')->fetchColumn();
        } catch (\PDOException $e) {
            return true;
        }
        $cfg = $conteudo ? (json_decode((string) $conteudo, true) ?: []) : [];
        $global = $cfg['global'] ?? [];
        $academia = $cfg['tenants'][(string) $tenantId] ?? [];

        $ligada = $academia['funcionalidade.comunicacao'] ?? $global['funcionalidade.comunicacao'] ?? true;
        if (!$ligada || !empty($global['bloqueio.comunicacao'])) {
            return false;
        }
        $pct = (int) ($global['rollout.comunicacao'] ?? 100);

        return $pct >= 100 || ($usuarioId !== null && crc32("comunicacao:{$usuarioId}") % 100 < $pct);
    }
}
