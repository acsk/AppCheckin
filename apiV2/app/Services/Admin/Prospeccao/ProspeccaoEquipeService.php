<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Papel;
use Illuminate\Support\Facades\Log;

/**
 * Equipe comercial do tenant (papel Comercial). Só administradores gerenciam.
 */
class ProspeccaoEquipeService
{
    public function __construct(
        private readonly ProspeccaoRepository $repo,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function listar(int $tenantId): array
    {
        return ['status' => 200, 'body' => [
            'comerciais' => $this->repo->equipe($tenantId, true),
            'responsaveis' => $this->repo->equipe($tenantId, false),
        ]];
    }

    /**
     * Dá o papel Comercial a um usuário existente (pelo e-mail) ou cria um novo usuário comercial.
     *
     * @param  array<string, mixed>  $data  {email, nome?, senha?}
     * @return array{status: int, body: array<string, mixed>}
     */
    public function adicionar(int $tenantId, array $data, array $ctx): array
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->erro('Informe um e-mail válido', 422);
        }

        $usuarioId = $this->repo->usuarioIdPorEmail($email);
        if ($usuarioId) {
            $this->repo->definirPapelComercial($tenantId, $usuarioId, true);
            $criado = false;
        } else {
            $nome = trim((string) ($data['nome'] ?? ''));
            $senha = (string) ($data['senha'] ?? '');
            if (mb_strlen($nome) < 3) {
                return $this->erro('Usuário novo: informe o nome', 422);
            }
            if (mb_strlen($senha) < 6) {
                return $this->erro('Usuário novo: a senha precisa ter ao menos 6 caracteres', 422);
            }
            $usuarioId = $this->usuarios->createUsuario(['email' => $email, 'nome' => $nome, 'senha' => $senha], $tenantId, Papel::COMERCIAL);
            if (! $usuarioId) {
                return $this->erro('Não foi possível criar o usuário', 500);
            }
            $criado = true;
        }
        Log::info('prospeccao.equipe', ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'por' => $ctx['usuario_id'], 'acao' => $criado ? 'criar' : 'adicionar']);

        return ['status' => 201, 'body' => ['message' => $criado ? 'Usuário comercial criado' : 'Papel Comercial atribuído', 'criado' => $criado] + $this->listar($tenantId)['body']];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function remover(int $tenantId, int $usuarioId, array $ctx): array
    {
        $comerciais = array_column($this->repo->equipe($tenantId, true), 'id');
        if (! in_array($usuarioId, $comerciais, true)) {
            return $this->erro('Usuário não faz parte da equipe comercial', 404);
        }
        $this->repo->definirPapelComercial($tenantId, $usuarioId, false);
        Log::info('prospeccao.equipe', ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'por' => $ctx['usuario_id'], 'acao' => 'remover']);

        return ['status' => 200, 'body' => ['message' => 'Papel Comercial removido'] + $this->listar($tenantId)['body']];
    }

    /** @return array{status: int, body: array{error: string}} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem]];
    }
}
