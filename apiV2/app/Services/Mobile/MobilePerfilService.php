<?php

namespace App\Services\Mobile;

use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MobilePerfilRepository;
use App\Repositories\UsuarioRepository;

class MobilePerfilService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly AlunoRepository $alunos,
        private readonly MobilePerfilRepository $perfilRepo,
        private readonly CheckinRepository $checkins,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>, headers?: array<string, string>}
     */
    public function perfil(int $userId, ?int $tenantId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'MISSING_TENANT',
                    'message' => 'Tenant não informado. Envie X-Tenant-Id ou utilize um token com tenant_id.',
                ],
            ];
        }

        if (! $this->usuarios->temAcessoTenant($userId, $tenantId)) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_ACCESS_DENIED',
                    'message' => 'Você não tem acesso a esta academia',
                ],
            ];
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return [
                'status' => 404,
                'body' => [
                    'success' => false,
                    'error' => 'Usuário não encontrado',
                ],
            ];
        }

        $aluno = $this->alunos->findPerfilByUsuario($userId, $tenantId) ?? [];
        $estatisticas = $this->perfilRepo->getEstatisticasCheckin($userId);
        $tenants = $this->perfilRepo->listarTenantsAtivosDoUsuario($userId);
        $plano = $this->perfilRepo->getPlanoUsuario($userId, $tenantId);
        $rankingModalidades = $this->checkins->rankingUsuarioPorModalidade($userId, $tenantId);

        $perfil = [
            'id' => $usuario['id'],
            'aluno_id' => $aluno['id'] ?? null,
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'email_global' => $usuario['email'] ?? null,
            'cpf' => $usuario['cpf'] ?? null,
            'telefone' => $usuario['telefone'] ?? null,
            'foto_caminho' => $aluno['foto_caminho'] ?? null,
            'cep' => $aluno['cep'] ?? null,
            'logradouro' => $aluno['logradouro'] ?? null,
            'numero' => $aluno['numero'] ?? null,
            'complemento' => $aluno['complemento'] ?? null,
            'bairro' => $aluno['bairro'] ?? null,
            'cidade' => $aluno['cidade'] ?? null,
            'estado' => $aluno['estado'] ?? null,
            'papel_id' => $usuario['papel_id'] ?? 1,
            'papel_nome' => $this->nomePapel($usuario['papel_id'] ?? 1),
            'membro_desde' => $usuario['created_at'],
            'tenants' => $tenants,
            'plano' => $plano,
            'estatisticas' => $estatisticas,
            'ranking_modalidades' => $rankingModalidades,
            'acesso' => $this->montarPayloadAcesso(null),
        ];

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $perfil,
            ],
            'headers' => [
                'Cache-Control' => 'private, max-age=300',
                'Vary' => 'Authorization, X-Tenant-Id',
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function verificarAcesso(int $userId, ?int $tenantId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'code' => 'MISSING_TENANT',
                    'message' => 'Tenant não informado',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'acesso' => $this->montarPayloadAcesso(null),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function tenants(int $userId): array
    {
        $tenants = $this->usuarios->getTenantsByUsuario($userId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'tenants' => $tenants,
                    'total' => count($tenants),
                ],
            ],
        ];
    }

    /**
     * @param  ?array<string, mixed>  $restricao
     * @return array<string, mixed>
     */
    private function montarPayloadAcesso(?array $restricao): array
    {
        $payload = [
            'permitido' => $restricao === null,
            'bloqueado' => $restricao !== null,
            'code' => null,
            'mensagem' => null,
            'matricula_id' => null,
            'status_codigo' => null,
        ];

        if ($restricao !== null) {
            $payload['code'] = $restricao['code'] ?? null;
            $payload['mensagem'] = $restricao['mensagem'] ?? null;
            $payload['matricula_id'] = $restricao['matricula_id'] ?? null;
            $payload['status_codigo'] = $restricao['status_codigo'] ?? null;
        }

        return $payload;
    }

    private function nomePapel(int $papelId): string
    {
        return match ($papelId) {
            1 => 'Aluno',
            2 => 'Professor',
            3 => 'Admin',
            4 => 'Super Admin',
            default => 'Usuário',
        };
    }
}
