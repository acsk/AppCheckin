<?php

namespace App\Services\Mobile;

use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MobilePerfilRepository;
use App\Repositories\UsuarioRepository;
use App\Support\AniversarioUtil;

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

        $dataNascimento = $aluno['data_nascimento'] ?? null;
        $aniversario = AniversarioUtil::payload(
            is_string($dataNascimento) ? $dataNascimento : null
        );

        $perfil = [
            'id' => $usuario['id'],
            'aluno_id' => $aluno['id'] ?? null,
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'email_global' => $usuario['email'] ?? null,
            'cpf' => $usuario['cpf'] ?? null,
            'telefone' => $usuario['telefone'] ?? null,
            'data_nascimento' => $dataNascimento,
            'aniversario_hoje' => $aniversario['aniversario_hoje'],
            'idade' => $aniversario['idade'],
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

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function uploadFoto(int $userId, ?int $tenantId, mixed $uploadedFile): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Usuário não encontrado'],
            ];
        }

        $aluno = $this->alunos->findAlunoComFotoNoTenant($userId, $tenantId);
        if (! $aluno) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Aluno não encontrado para este usuário'],
            ];
        }

        if (! $uploadedFile || ! $uploadedFile->isValid()) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Nenhuma imagem foi enviada. Use o campo "foto" em multipart/form-data',
                ],
            ];
        }

        $mimeType = $uploadedFile->getMimeType() ?? '';
        $permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (! in_array($mimeType, $permitidos, true)) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WebP',
                    'mime_enviado' => $mimeType,
                ],
            ];
        }

        $tamanhoMaximo = 5 * 1024 * 1024;
        if ($uploadedFile->getSize() > $tamanhoMaximo) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Arquivo muito grande. Máximo 5MB',
                    'tamanho_enviado' => $uploadedFile->getSize(),
                    'tamanho_maximo' => $tamanhoMaximo,
                ],
            ];
        }

        $extensoes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $extensoes[$mimeType] ?? 'jpg';

        $uploadDir = public_path('uploads/fotos');
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (! empty($aluno['foto_caminho'])) {
            $caminhoAntigo = $this->resolveFotoAbsolutePath((string) $aluno['foto_caminho']);
            if ($caminhoAntigo && is_file($caminhoAntigo)) {
                @unlink($caminhoAntigo);
            }
        }

        $nomeArquivo = 'aluno_'.$aluno['id'].'_'.time().'.'.$ext;
        $caminhoRelativo = '/uploads/fotos/'.$nomeArquivo;
        $caminhoCompleto = $uploadDir.'/'.$nomeArquivo;

        $uploadedFile->move($uploadDir, $nomeArquivo);
        @chmod($caminhoCompleto, 0644);

        $this->alunos->updateFotoCaminho((int) $aluno['id'], $caminhoRelativo);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Foto de perfil atualizada com sucesso',
                'data' => [
                    'aluno_id' => (int) $aluno['id'],
                    'usuario_id' => $userId,
                    'tamanho_original' => $uploadedFile->getSize(),
                    'tamanho_final' => is_file($caminhoCompleto) ? filesize($caminhoCompleto) : null,
                    'tipo_arquivo' => $mimeType,
                    'nome_original' => $uploadedFile->getClientOriginalName(),
                    'caminho_url' => $caminhoRelativo,
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: string|null, headers?: array<string, string>}
     */
    public function obterFoto(int $userId, ?int $tenantId): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => null];
        }

        $aluno = $this->alunos->findAlunoComFotoNoTenant($userId, $tenantId);
        if (! $aluno || empty($aluno['foto_caminho'])) {
            return ['status' => 404, 'body' => null];
        }

        $caminhoCompleto = $this->resolveFotoAbsolutePath((string) $aluno['foto_caminho']);
        if (! $caminhoCompleto || ! is_file($caminhoCompleto)) {
            return ['status' => 404, 'body' => null];
        }

        $mimeType = mime_content_type($caminhoCompleto) ?: 'application/octet-stream';
        if (! str_starts_with($mimeType, 'image/')) {
            return ['status' => 400, 'body' => null];
        }

        return [
            'status' => 200,
            'body' => file_get_contents($caminhoCompleto) ?: '',
            'headers' => [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400',
            ],
        ];
    }

    private function resolveFotoAbsolutePath(string $caminhoRelativo): ?string
    {
        $caminhoRelativo = ltrim($caminhoRelativo, '/');
        $candidates = [
            public_path($caminhoRelativo),
            base_path('../api/public/'.$caminhoRelativo),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
