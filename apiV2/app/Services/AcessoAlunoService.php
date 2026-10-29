<?php

namespace App\Services;

use App\Repositories\AlunoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\SuperAdmin\AcessoSuporteService;
use App\Support\Papel;
use Illuminate\Support\Facades\DB;

/**
 * "Ver app como aluno": o admin da academia (ou o super admin no "acessar como academia") recebe um
 * token de 1h do aluno com acesso_aluno_id. A API confere a sessão a cada requisição e só aceita
 * leitura (JwtAuthenticate); encerrar, expirar ou perder o papel de admin corta o acesso na hora.
 */
class AcessoAlunoService
{
    /** Duração máxima de um acesso (segundos) */
    public const DURACAO = 3600;

    /** Escritas permitidas durante o acesso (o resto responde MODO_VISUALIZACAO) */
    public const ESCRITAS_LIVRES = ['v2/suporte/aluno/encerrar', 'v2/auth/logout'];

    public function __construct(
        private readonly AlunoRepository $alunos,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @param  array<string, mixed>|null  $acessoSuporte  sessão do "acessar como academia", quando é o super admin
     * @return array{status: int, body: array<string, mixed>}
     */
    public function iniciar(int $tenantId, int $operadorId, ?array $acessoSuporte, int $alunoId, ?string $motivo, ?string $ip, ?string $userAgent): array
    {
        $motivo = mb_substr(trim((string) $motivo), 0, 500);
        if (mb_strlen($motivo) < 10) {
            return $this->erro('Descreva o motivo do acesso (mínimo 10 caracteres). Ele fica registrado.', 422, 'MOTIVO_OBRIGATORIO');
        }
        $aluno = $this->alunos->findAlunoNoTenantPorId($alunoId, $tenantId);
        if (! $aluno) {
            return $this->erro('Aluno não encontrado nesta academia', 404, 'ALUNO_NAO_ENCONTRADO');
        }
        $alunoUsuarioId = (int) $aluno['usuario_id'];
        if ($alunoUsuarioId === $operadorId) {
            return $this->erro('Esta é a sua própria conta. Entre pelo app normalmente.', 422, 'PROPRIA_CONTA');
        }

        $agora = now();
        $expira = $agora->copy()->addSeconds(self::DURACAO);
        $acessoId = DB::transaction(function () use ($tenantId, $operadorId, $acessoSuporte, $alunoId, $alunoUsuarioId, $motivo, $ip, $userAgent, $agora, $expira) {
            // Um acesso aberto por operador: abrir outro aluno encerra o anterior
            DB::table('acessos_aluno')->where('operador_usuario_id', $operadorId)->whereNull('encerrado_em')
                ->update(['encerrado_em' => $agora, 'updated_at' => $agora]);

            return (int) DB::table('acessos_aluno')->insertGetId([
                'tenant_id' => $tenantId, 'aluno_id' => $alunoId, 'aluno_usuario_id' => $alunoUsuarioId,
                'operador_usuario_id' => $operadorId, 'acesso_suporte_id' => $acessoSuporte['id'] ?? null, 'motivo' => $motivo,
                'iniciado_em' => $agora, 'expira_em' => $expira,
                'ip' => $ip, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
        });

        $jwt = new JwtService((string) config('appcheckin.jwt_secret'), self::DURACAO);
        $token = $jwt->encode([
            'user_id' => $alunoUsuarioId,
            'email' => $aluno['email'],
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'acesso_aluno_id' => $acessoId,
        ]);

        // Mesmo formato do login do app, com só o papel de aluno: o app mostra a visão do aluno
        $papelAluno = array_values(array_filter($this->usuarios->getPapeis($alunoUsuarioId, $tenantId), fn ($p) => $p['id'] === Papel::ALUNO));
        $tenants = array_values(array_map(fn ($t) => ['papeis' => $papelAluno] + $t,
            array_filter($this->usuarios->getTenantsByUsuario($alunoUsuarioId), fn ($t) => (int) $t['tenant']['id'] === $tenantId)));

        return ['status' => 201, 'body' => [
            'message' => 'Acesso iniciado',
            'token' => $token,
            'user' => [
                'id' => $alunoUsuarioId, 'nome' => $aluno['nome'], 'email' => $aluno['email'], 'email_global' => $aluno['email'],
                'foto_base64' => null, 'papel_id' => Papel::ALUNO, 'papeis' => $papelAluno,
            ],
            'tenants' => $tenants,
            'acesso' => ['id' => $acessoId, 'expira_em' => $expira->toIso8601String(), 'somente_leitura' => true],
        ]];
    }

    /**
     * Sessão válida do token: aberta, no prazo, do mesmo aluno e academia, e quem abriu ainda pode
     * (admin ativo na academia, ou o "acessar como academia" do super admin ainda valendo).
     *
     * @return array<string, mixed>|null
     */
    public function sessaoValida(int $acessoId, int $alunoUsuarioId, int $tenantId): ?array
    {
        $row = DB::table('acessos_aluno')
            ->where('id', $acessoId)->where('aluno_usuario_id', $alunoUsuarioId)->where('tenant_id', $tenantId)
            ->whereNull('encerrado_em')->where('expira_em', '>', now())
            ->first(['id', 'tenant_id', 'aluno_id', 'operador_usuario_id', 'acesso_suporte_id', 'expira_em']);
        if (! $row) {
            return null;
        }
        $pode = $row->acesso_suporte_id
            ? app(AcessoSuporteService::class)->sessaoValida((int) $row->acesso_suporte_id, (int) $row->operador_usuario_id, $tenantId) !== null
            : DB::table('tenant_usuario_papel')->where('usuario_id', $row->operador_usuario_id)->where('tenant_id', $tenantId)
                ->where('papel_id', Papel::ADMIN)->where('ativo', 1)->exists();

        return $pode ? (array) $row : null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function encerrar(int $acessoId): array
    {
        DB::table('acessos_aluno')->where('id', $acessoId)->whereNull('encerrado_em')
            ->update(['encerrado_em' => now(), 'updated_at' => now()]);

        return ['status' => 200, 'body' => ['message' => 'Acesso encerrado']];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status, string $codigo): array
    {
        return ['status' => $status, 'body' => ['type' => 'error', 'code' => $codigo, 'message' => $mensagem]];
    }
}
