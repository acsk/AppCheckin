<?php

namespace App\Services;

use App\Repositories\AtualizacaoCadastralRepository;
use App\Repositories\UsuarioRepository;
use App\Support\CamposCadastro;
use App\Support\PoliticaSenha;
use Illuminate\Support\Facades\DB;

/**
 * Atualização cadastral pedida pelo super admin (campanha) e respondida pelo usuário no app.
 */
class AtualizacaoCadastralService
{
    public function __construct(
        private readonly AtualizacaoCadastralRepository $repo,
        private readonly UsuarioRepository $usuarios,
        private readonly VerificacaoEmailService $verificacao,
    ) {}

    /* ---------- Super admin ---------- */

    /** @return array{status: int, body: array<string, mixed>} */
    public function listar(): array
    {
        $campanhas = array_map(fn ($c) => $c + [
            'publico' => $this->repo->totalPublico($c),
            'respostas' => $this->repo->totalRespostas($c['id']),
        ], $this->repo->listar());

        return $this->ok([
            'campanhas' => $campanhas,
            'campos' => CamposCadastro::lista(),
            'niveis_senha' => array_map(fn ($n) => ['nivel' => $n, 'regras' => PoliticaSenha::regras($n, 8)], PoliticaSenha::NIVEIS),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvar(?int $id, array $data, ?int $userId): array
    {
        if ($id !== null) {
            $atual = $this->repo->find($id);
            if (! $atual) {
                return $this->erro('Campanha não encontrada.', 404);
            }
            if (! $atual['ativa']) {
                return $this->erro('Campanha encerrada não pode ser alterada. Crie uma nova.', 422);
            }
        }

        [$dados, $tenantIds, $erros] = $this->validarCampanha($data);
        if ($erros !== []) {
            return ['status' => 422, 'body' => ['errors' => $erros, 'error' => $erros[0]]];
        }

        $novoId = $this->repo->salvar($id, $dados, $tenantIds, $userId);
        $campanha = $this->repo->find($novoId);

        return $this->ok([
            'message' => $id === null ? 'Atualização cadastral criada' : 'Atualização cadastral salva',
            'campanha' => $campanha + ['publico' => $this->repo->totalPublico($campanha), 'respostas' => $this->repo->totalRespostas($novoId)],
        ], $id === null ? 201 : 200);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function encerrar(int $id): array
    {
        if (! $this->repo->find($id)) {
            return $this->erro('Campanha não encontrada.', 404);
        }
        $this->repo->encerrar($id);

        return $this->ok(['message' => 'Atualização cadastral encerrada', 'campanha' => $this->repo->find($id)]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function pendentes(int $id): array
    {
        $campanha = $this->repo->find($id);
        if (! $campanha) {
            return $this->erro('Campanha não encontrada.', 404);
        }

        return $this->ok(['pendentes' => $this->repo->pendentes($campanha)]);
    }

    /* ---------- App ---------- */

    /** @return array{status: int, body: array<string, mixed>} */
    public function pendente(int $usuarioId): array
    {
        $c = $this->repo->pendenteDoUsuario($usuarioId);
        if (! $c) {
            return $this->ok(['pendente' => null]);
        }

        $atual = $this->repo->dadosDoUsuario($usuarioId, $c['campos']);

        return $this->ok(['pendente' => [
            'id' => $c['id'],
            'titulo' => $c['titulo'],
            'mensagem' => $c['mensagem'],
            'bloquear_app' => $c['bloquear_app'],
            'campos' => array_map(fn ($campo) => [
                'campo' => $campo,
                'label' => CamposCadastro::CATALOGO[$campo]['label'],
                'tipo' => CamposCadastro::CATALOGO[$campo]['tipo'],
                'obrigatorio' => in_array($campo, $c['campos_obrigatorios'], true),
                'valor' => $atual[$campo] ?? null,
            ], $c['campos']),
            'email_atual' => $atual['email'] ?? null,
            'validar_email' => $c['validar_email'],
            'email_verificado' => ! empty($atual['email_verificado_em']),
            'trocar_senha' => $c['trocar_senha'],
            'senha' => $c['trocar_senha'] ? [
                'min_caracteres' => $c['senha_min_caracteres'],
                'nivel' => $c['senha_nivel'],
                'regras' => PoliticaSenha::regras($c['senha_nivel'], $c['senha_min_caracteres']),
            ] : null,
        ]]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function enviarCodigoEmail(int $usuarioId, string $email): array
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->erro('E-mail inválido.', 422);
        }
        if ($this->repo->emailEmUsoPorOutro($email, $usuarioId)) {
            return $this->erro('Este e-mail já é usado por outro cadastro.', 422);
        }
        $nome = (string) DB::table('usuarios')->where('id', $usuarioId)->value('nome');
        $erro = $this->verificacao->enviarCodigo($usuarioId, $email, $nome);

        return $erro ? $this->erro($erro, $erro === VerificacaoEmailService::ERRO_ENVIO ? 503 : 429) : $this->ok(['message' => "Enviamos um código para {$email}."]);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function validarCodigoEmail(int $usuarioId, string $email, string $codigo): array
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $erro = $this->verificacao->validarCodigo($usuarioId, $email, $codigo);

        return $erro ? $this->erro($erro, 422) : $this->ok(['message' => 'E-mail confirmado', 'email' => $email]);
    }

    /**
     * @param  array<string, mixed>  $data  dados (campo => valor), senha_atual, nova_senha
     * @return array{status: int, body: array<string, mixed>}
     */
    public function responder(int $usuarioId, int $atualizacaoId, array $data): array
    {
        $c = $this->repo->pendenteDoUsuario($usuarioId);
        if (! $c || $c['id'] !== $atualizacaoId) {
            return $this->erro('Esta atualização cadastral não está mais pendente.', 409);
        }

        $atual = $this->repo->dadosDoUsuario($usuarioId, $c['campos']);
        $enviados = is_array($data['dados'] ?? null) ? $data['dados'] : [];
        $erros = [];
        $novos = [];
        foreach ($c['campos'] as $campo) {
            $valor = trim((string) ($enviados[$campo] ?? ''));
            if ($valor === '') {
                if (in_array($campo, $c['campos_obrigatorios'], true)) {
                    $erros[$campo] = 'Campo obrigatório.';
                } elseif ($campo !== 'email' && $campo !== 'nome') {
                    $novos[$campo] = null;
                }

                continue;
            }
            $erro = CamposCadastro::erro($campo, $valor);
            if ($erro !== null) {
                $erros[$campo] = $erro;
            } else {
                $novos[$campo] = $campo === 'email' ? mb_strtolower($valor, 'UTF-8') : $valor;
            }
        }

        $emailFinal = (string) ($novos['email'] ?? $atual['email'] ?? '');
        $emailMudou = isset($novos['email']) && $novos['email'] !== mb_strtolower((string) ($atual['email'] ?? ''), 'UTF-8');
        if ($emailMudou && $this->repo->emailEmUsoPorOutro($emailFinal, $usuarioId)) {
            $erros['email'] = 'Este e-mail já é usado por outro cadastro.';
        }
        $emailConfirmado = $this->verificacao->confirmado($usuarioId, $emailFinal);
        // Mudar o e-mail de login sempre exige o código, mesmo sem validação pedida na campanha
        $precisaCodigo = $emailMudou || ($c['validar_email'] && empty($atual['email_verificado_em']));
        if ($precisaCodigo && ! $emailConfirmado) {
            $erros['email'] = 'Confirme o e-mail com o código enviado.';
        }

        $novaSenha = (string) ($data['nova_senha'] ?? '');
        if ($c['trocar_senha']) {
            $hash = (string) DB::table('usuarios')->where('id', $usuarioId)->value('senha_hash');
            if (! password_verify((string) ($data['senha_atual'] ?? ''), $hash)) {
                $erros['senha_atual'] = 'Senha atual incorreta.';
            }
            $cpf = $novos['cpf'] ?? $atual['cpf'] ?? DB::table('usuarios')->where('id', $usuarioId)->value('cpf');
            $falhas = PoliticaSenha::validar($novaSenha, $c['senha_nivel'], $c['senha_min_caracteres'], $cpf ? (string) $cpf : null);
            if ($falhas !== []) {
                $erros['nova_senha'] = implode(' ', $falhas);
            } elseif (password_verify($novaSenha, $hash)) {
                $erros['nova_senha'] = 'A nova senha precisa ser diferente da atual.';
            }
        }

        if ($erros !== []) {
            return ['status' => 422, 'body' => ['error' => 'Confira os campos destacados.', 'campos' => $erros]];
        }

        $alterados = array_keys(array_filter($novos, fn ($v, $k) => (string) $v !== (string) ($atual[$k] ?? ''), ARRAY_FILTER_USE_BOTH));
        try {
            DB::transaction(function () use ($usuarioId, $novos, $emailMudou, $emailFinal, $emailConfirmado, $c, $novaSenha, $alterados) {
                $pessoais = array_diff_key($novos, ['email' => true]);
                if ($pessoais !== []) {
                    $this->usuarios->gravarDadosPessoais($usuarioId, $pessoais);
                }
                $update = [];
                if ($emailMudou) {
                    $update['email'] = $emailFinal;
                    $update['email_global'] = $emailFinal;
                }
                if ($emailConfirmado) {
                    $update['email_verificado_em'] = now();
                }
                if ($c['trocar_senha']) {
                    $update['senha_hash'] = password_hash($novaSenha, PASSWORD_BCRYPT);
                }
                if ($update !== []) {
                    DB::table('usuarios')->where('id', $usuarioId)->update($update + ['updated_at' => now()]);
                }
                $this->repo->registrarResposta($c['id'], $usuarioId, $alterados, $c['trocar_senha']);
            });
        } catch (\DomainException $e) {
            return ['status' => 422, 'body' => ['error' => $e->getMessage(), 'campos' => ['cpf' => $e->getMessage()]]];
        }

        return $this->ok(['message' => 'Cadastro atualizado. Obrigado!']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<int>, 2: list<string>}
     */
    private function validarCampanha(array $data): array
    {
        $erros = [];
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '' || mb_strlen($titulo) > 120) {
            $erros[] = 'Informe um título de até 120 caracteres.';
        }
        $campos = CamposCadastro::filtrar((array) ($data['campos'] ?? []));
        $obrigatorios = array_values(array_intersect(CamposCadastro::filtrar((array) ($data['campos_obrigatorios'] ?? [])), $campos));
        $validarEmail = filter_var($data['validar_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $trocarSenha = filter_var($data['trocar_senha'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($campos === [] && ! $validarEmail && ! $trocarSenha) {
            $erros[] = 'Peça ao menos um campo, a validação do e-mail ou a troca de senha.';
        }

        $nivel = (string) ($data['senha_nivel'] ?? 'media');
        $min = (int) ($data['senha_min_caracteres'] ?? 8);
        if ($trocarSenha) {
            if (! in_array($nivel, PoliticaSenha::NIVEIS, true)) {
                $erros[] = 'Escolha o tipo de senha segura.';
            }
            if ($min < PoliticaSenha::MIN || $min > 32) {
                $erros[] = 'A senha deve ter entre '.PoliticaSenha::MIN.' e 32 caracteres.';
            }
        }

        $todas = filter_var($data['todas_academias'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $tenantIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['academias'] ?? [])))));
        if (! $todas) {
            $existentes = DB::table('tenants')->whereIn('id', $tenantIds ?: [0])->pluck('id')->map(fn ($i) => (int) $i)->all();
            $tenantIds = array_values(array_intersect($tenantIds, $existentes));
            if ($tenantIds === []) {
                $erros[] = 'Escolha ao menos uma academia.';
            }
        }

        return [[
            'titulo' => $titulo,
            'mensagem' => trim((string) ($data['mensagem'] ?? '')) ?: null,
            'campos' => $campos,
            'campos_obrigatorios' => $obrigatorios,
            'validar_email' => $validarEmail,
            'trocar_senha' => $trocarSenha,
            'senha_min_caracteres' => in_array($nivel, PoliticaSenha::NIVEIS, true) ? max(PoliticaSenha::MIN, min(32, $min)) : 8,
            'senha_nivel' => in_array($nivel, PoliticaSenha::NIVEIS, true) ? $nivel : 'media',
            'bloquear_app' => filter_var($data['bloquear_app'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'todas_academias' => $todas,
        ], $todas ? [] : $tenantIds, $erros];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function ok(array $body, int $status = 200): array
    {
        return ['status' => $status, 'body' => $body];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem]];
    }
}
