<?php

namespace App\Services\Admin;

use App\Repositories\UsuarioRepository;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Minha Conta" do painel: perfil e senha do usuário logado e dados operacionais da própria escola.
 * Tudo é escrito pelo id vindo do token (usuário / tenant), nunca pelo payload.
 * Dados do CNPJ ficam no EntidadeJuridicaService; plano e assinatura no AdminMeuContratoService.
 */
class AdminMinhaContaService
{
    /** Erros de senha atual antes de travar a troca de senha/e-mail por 15 minutos */
    private const MAX_TENTATIVAS_SENHA = 5;

    private const CAMPOS_ESCOLA = ['nome', 'email', 'telefone', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];

    public function __construct(
        private readonly UsuarioRepository $usuarios,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function perfil(int $usuarioId): array
    {
        $u = DB::table('usuarios')->where('id', $usuarioId)
            ->first(['id', 'nome', 'email', 'telefone', 'cpf', 'created_at']);
        if (! $u) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }

        return ['status' => 200, 'body' => ['perfil' => [
            'id' => (int) $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
            'telefone' => $u->telefone,
            'cpf' => $u->cpf,
            'criado_em' => $u->created_at,
        ]]];
    }

    /**
     * Trocar o e-mail (que é o login) exige a senha atual.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarPerfil(int $usuarioId, array $data): array
    {
        $atual = DB::table('usuarios')->where('id', $usuarioId)->first(['id', 'email', 'senha_hash']);
        if (! $atual) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        $telefone = Documento::digitos($data['telefone'] ?? '');
        $email = mb_strtolower(trim((string) ($data['email'] ?? $atual->email)), 'UTF-8');
        $trocaEmail = $email !== mb_strtolower(trim((string) $atual->email), 'UTF-8');

        $errors = [];
        if ($nome === '' || mb_strlen($nome) > 255) {
            $errors[] = 'Informe o nome (até 255 caracteres)';
        }
        if ($telefone !== '' && ! in_array(strlen($telefone), [10, 11], true)) {
            $errors[] = 'Telefone deve ter DDD + número (10 ou 11 dígitos)';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido';
        } elseif ($trocaEmail && $this->usuarios->emailExists($email, $usuarioId)) {
            $errors[] = 'Este e-mail já está em uso por outro usuário';
        }
        if ($errors !== []) {
            return $this->validacao($errors);
        }

        if ($trocaEmail && ($erro = $this->conferirSenhaAtual($usuarioId, (string) $atual->senha_hash, (string) ($data['senha_atual'] ?? ''))) !== null) {
            return $erro;
        }

        DB::table('usuarios')->where('id', $usuarioId)->update([
            'nome' => $nome,
            'telefone' => $telefone !== '' ? $telefone : null,
            'email' => $email,
            'email_global' => $email,
            'updated_at' => now(),
        ]);

        return ['status' => 200, 'body' => ['type' => 'success', 'message' => 'Perfil atualizado'] + $this->perfil($usuarioId)['body']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alterarSenha(int $usuarioId, array $data): array
    {
        $hash = DB::table('usuarios')->where('id', $usuarioId)->value('senha_hash');
        if ($hash === null) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }

        $nova = (string) ($data['nova_senha'] ?? '');
        $confirmacao = (string) ($data['confirmacao_senha'] ?? '');
        $errors = [];
        if (strlen($nova) < 6) {
            $errors[] = 'A nova senha deve ter no mínimo 6 caracteres';
        }
        if ($nova !== $confirmacao) {
            $errors[] = 'A confirmação não confere com a nova senha';
        }
        if ($errors !== []) {
            return $this->validacao($errors);
        }

        if (($erro = $this->conferirSenhaAtual($usuarioId, (string) $hash, (string) ($data['senha_atual'] ?? ''))) !== null) {
            return $erro;
        }
        if (password_verify($nova, (string) $hash)) {
            return $this->validacao(['A nova senha deve ser diferente da atual']);
        }

        $this->usuarios->resetPassword($usuarioId, $nova);

        return ['status' => 200, 'body' => ['type' => 'success', 'message' => 'Senha alterada com sucesso']];
    }

    /** @return array{status: int, body: array<string, mixed>}|null  null quando a senha confere */
    private function conferirSenhaAtual(int $usuarioId, string $hash, string $senha): ?array
    {
        $chave = 'minha-conta-senha:'.$usuarioId;
        if (RateLimiter::tooManyAttempts($chave, self::MAX_TENTATIVAS_SENHA)) {
            $min = max(1, (int) ceil(RateLimiter::availableIn($chave) / 60));

            return ['status' => 429, 'body' => ['type' => 'error', 'code' => 'RATE_LIMIT_EXCEEDED', 'message' => "Muitas tentativas. Tente novamente em {$min} minutos"]];
        }
        if ($senha === '' || ! password_verify($senha, $hash)) {
            RateLimiter::hit($chave, 15 * 60);

            return ['status' => 422, 'body' => ['type' => 'error', 'code' => 'SENHA_ATUAL_INVALIDA', 'message' => 'Senha atual incorreta', 'errors' => ['Senha atual incorreta']]];
        }
        RateLimiter::clear($chave);

        return null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function escola(int $tenantId): array
    {
        $t = DB::table('tenants')->where('id', $tenantId)->first(array_merge(['id', 'slug'], self::CAMPOS_ESCOLA));
        if (! $t) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Academia não encontrada']];
        }

        return ['status' => 200, 'body' => ['escola' => ['id' => (int) $t->id] + (array) $t]];
    }

    /**
     * Só os dados operacionais desta escola (UPDATE por id do token). Slug, CNPJ, status e plano ficam fora.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarEscola(int $tenantId, array $data): array
    {
        if (! DB::table('tenants')->where('id', $tenantId)->exists()) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Academia não encontrada']];
        }

        $v = [];
        foreach (self::CAMPOS_ESCOLA as $campo) {
            $v[$campo] = trim((string) ($data[$campo] ?? ''));
        }
        $v['email'] = mb_strtolower($v['email'], 'UTF-8');
        $v['telefone'] = Documento::digitos($v['telefone']);
        $v['cep'] = Documento::digitos($v['cep']);
        $v['estado'] = mb_strtoupper($v['estado'], 'UTF-8');

        $errors = [];
        if ($v['nome'] === '' || mb_strlen($v['nome']) > 255) {
            $errors[] = 'Informe o nome da escola (até 255 caracteres)';
        } elseif (DB::table('tenants')->where('nome', $v['nome'])->where('id', '<>', $tenantId)->exists()) {
            $errors[] = 'Já existe outra academia com este nome';
        }
        if (! filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail de contato inválido';
        }
        if ($v['telefone'] !== '' && ! in_array(strlen($v['telefone']), [10, 11], true)) {
            $errors[] = 'Telefone deve ter DDD + número (10 ou 11 dígitos)';
        }
        if ($v['cep'] !== '' && strlen($v['cep']) !== 8) {
            $errors[] = 'CEP deve ter 8 dígitos';
        }
        if ($v['estado'] !== '' && ! preg_match('/^[A-Z]{2}$/', $v['estado'])) {
            $errors[] = 'UF deve ter 2 letras';
        }
        foreach (['logradouro' => 255, 'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100] as $campo => $max) {
            if (mb_strlen($v[$campo]) > $max) {
                $errors[] = "O campo {$campo} aceita até {$max} caracteres";
            }
        }
        if ($errors !== []) {
            return $this->validacao($errors);
        }

        DB::table('tenants')->where('id', $tenantId)->update(
            array_map(fn ($x) => $x === '' ? null : $x, $v) + ['updated_at' => now()],
        );

        return ['status' => 200, 'body' => ['type' => 'success', 'message' => 'Dados da escola atualizados'] + $this->escola($tenantId)['body']];
    }

    /**
     * @param  list<string>  $errors
     * @return array{status: int, body: array<string, mixed>}
     */
    private function validacao(array $errors): array
    {
        return ['status' => 422, 'body' => ['type' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => implode(', ', $errors), 'errors' => $errors]];
    }
}
