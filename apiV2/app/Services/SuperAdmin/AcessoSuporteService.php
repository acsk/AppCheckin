<?php

namespace App\Services\SuperAdmin;

use App\Services\JwtService;
use App\Support\Papel;
use Illuminate\Support\Facades\DB;

/**
 * "Acessar como academia": o super admin recebe um token de tempo limitado para uma academia e, com
 * ele, a API o trata como admin daquela academia (JwtAuthenticate). A sessão é conferida a cada
 * requisição, então encerrar ou expirar corta o acesso na hora. A academia vê os acessos.
 */
class AcessoSuporteService
{
    /** Duração máxima de um acesso (segundos) */
    public const DURACAO = 2 * 3600;

    /**
     * Motivos ativos para escolher ao acessar (motivos_acesso_suporte).
     *
     * @return list<array{id: int, nome: string, exige_detalhe: bool}>
     */
    public function motivos(): array
    {
        return DB::table('motivos_acesso_suporte')->where('ativo', 1)->orderBy('ordem')->orderBy('nome')
            ->get(['id', 'nome', 'exige_detalhe'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'nome' => $m->nome, 'exige_detalhe' => (bool) $m->exige_detalhe])
            ->all();
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function iniciar(int $tenantId, int $superAdminId, mixed $motivoId, ?string $detalhe, ?string $ip, ?string $userAgent): array
    {
        $motivo = is_numeric($motivoId)
            ? DB::table('motivos_acesso_suporte')->where('id', (int) $motivoId)->where('ativo', 1)->first(['id', 'exige_detalhe'])
            : null;
        if (! $motivo) {
            return $this->erro('Escolha o motivo do acesso. A academia vai ver esse motivo.', 422, 'MOTIVO_OBRIGATORIO');
        }
        $detalhe = mb_substr(trim((string) $detalhe), 0, 500);
        if ($motivo->exige_detalhe && mb_strlen($detalhe) < 10) {
            return $this->erro('Descreva o motivo do acesso (mínimo 10 caracteres). A academia vai ver esse texto.', 422, 'DETALHE_OBRIGATORIO');
        }
        if ($tenantId === 1) {
            return $this->erro('A academia do sistema não pode ser acessada assim', 422, 'TENANT_SISTEMA');
        }
        $academia = DB::table('tenants')->where('id', $tenantId)->where('ativo', 1)->first(['id', 'nome']);
        if (! $academia) {
            return $this->erro('Academia não encontrada ou inativa', 404, 'TENANT_NAO_ENCONTRADO');
        }
        $email = DB::table('usuarios')->where('id', $superAdminId)->value('email');

        $agora = now();
        $expira = $agora->copy()->addSeconds(self::DURACAO);
        $acessoId = DB::transaction(function () use ($tenantId, $superAdminId, $motivo, $detalhe, $ip, $userAgent, $agora, $expira) {
            // Um acesso aberto por super admin: entrar em outra academia encerra o anterior
            DB::table('acessos_suporte')->where('usuario_id', $superAdminId)->whereNull('encerrado_em')
                ->update(['encerrado_em' => $agora, 'updated_at' => $agora]);

            return (int) DB::table('acessos_suporte')->insertGetId([
                'tenant_id' => $tenantId, 'usuario_id' => $superAdminId, 'motivo_id' => (int) $motivo->id, 'detalhe' => $detalhe !== '' ? $detalhe : null,
                'iniciado_em' => $agora, 'expira_em' => $expira,
                'ip' => $ip, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
        });

        $jwt = new JwtService((string) config('appcheckin.jwt_secret'), self::DURACAO);
        $token = $jwt->encode([
            'user_id' => $superAdminId,
            'email' => $email,
            'tenant_id' => $tenantId,
            'acesso_suporte_id' => $acessoId,
        ]);

        return ['status' => 201, 'body' => [
            'message' => 'Acesso iniciado',
            'token' => $token,
            'acesso' => ['id' => $acessoId, 'expira_em' => $expira->toDateTimeString()],
            'academia' => ['id' => (int) $academia->id, 'nome' => $academia->nome],
        ]];
    }

    /**
     * Sessão válida do token (mesmo usuário e academia, não encerrada, não expirada, e o usuário
     * ainda é super admin), ou null.
     *
     * @return array<string, mixed>|null
     */
    public function sessaoValida(int $acessoId, int $usuarioId, int $tenantId): ?array
    {
        $row = DB::table('acessos_suporte as a')
            ->join('tenants as t', 't.id', '=', 'a.tenant_id')
            ->where('a.id', $acessoId)->where('a.usuario_id', $usuarioId)->where('a.tenant_id', $tenantId)
            ->whereNull('a.encerrado_em')->where('a.expira_em', '>', now())
            ->first(['a.id', 'a.tenant_id', 'a.usuario_id', 'a.expira_em', 't.nome as tenant_nome']);
        if (! $row) {
            return null;
        }
        $superAdmin = DB::table('tenant_usuario_papel')
            ->where('usuario_id', $usuarioId)->where('papel_id', Papel::SUPER_ADMIN)->where('ativo', 1)->exists();

        return $superAdmin ? (array) $row : null;
    }

    public function registrarAcao(int $acessoId, string $metodo, string $rota, int $status): void
    {
        DB::table('acessos_suporte_acoes')->insert([
            'acesso_suporte_id' => $acessoId, 'metodo' => $metodo, 'rota' => mb_substr($rota, 0, 255), 'status' => $status, 'created_at' => now(),
        ]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function encerrar(int $acessoId, int $usuarioId): array
    {
        DB::table('acessos_suporte')->where('id', $acessoId)->where('usuario_id', $usuarioId)->whereNull('encerrado_em')
            ->update(['encerrado_em' => now(), 'updated_at' => now()]);

        return ['status' => 200, 'body' => ['message' => 'Acesso encerrado']];
    }

    /**
     * Acessos do suporte a esta academia, para a própria academia ver.
     *
     * @return list<array<string, mixed>>
     */
    public function listarDoTenant(int $tenantId, int $limite = 50): array
    {
        $acoes = DB::table('acessos_suporte_acoes')->groupBy('acesso_suporte_id')
            ->selectRaw('acesso_suporte_id, COUNT(*) as total');

        return DB::table('acessos_suporte as a')
            ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('motivos_acesso_suporte as m', 'm.id', '=', 'a.motivo_id')
            ->leftJoinSub($acoes, 'x', 'x.acesso_suporte_id', '=', 'a.id')
            ->where('a.tenant_id', $tenantId)
            ->orderByDesc('a.iniciado_em')
            ->limit($limite)
            ->get(['a.id', 'u.nome as usuario_nome', 'm.nome as motivo', 'a.detalhe', 'a.iniciado_em', 'a.expira_em', 'a.encerrado_em', DB::raw('COALESCE(x.total, 0) as alteracoes')])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'usuario_nome' => $r->usuario_nome,
                'motivo' => $r->motivo,
                'detalhe' => $r->detalhe,
                'iniciado_em' => $r->iniciado_em,
                'encerrado_em' => $r->encerrado_em ?? (strtotime((string) $r->expira_em) < time() ? $r->expira_em : null),
                'em_andamento' => $r->encerrado_em === null && strtotime((string) $r->expira_em) > time(),
                'alteracoes' => (int) $r->alteracoes,
            ])
            ->all();
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status, string $codigo): array
    {
        return ['status' => $status, 'body' => ['type' => 'error', 'code' => $codigo, 'message' => $mensagem]];
    }
}
