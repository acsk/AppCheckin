<?php

namespace App\Services\Admin;

use App\Repositories\UsuarioRepository;
use App\Support\Documento;
use App\Support\Pessoa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

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

    /** Dados que o dono/admin precisa ter para o cadastro ficar completo (rótulo => campos). */
    private const PERFIL_OBRIGATORIO = [
        'CPF' => ['cpf'], 'telefone' => ['telefone'], 'data de nascimento' => ['data_nascimento'],
        'endereço' => ['cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado'],
    ];

    /** @return array{status: int, body: array<string, mixed>} */
    public function perfil(int $usuarioId): array
    {
        $u = DB::table('usuarios')->where('id', $usuarioId)
            ->first(['id', 'nome', 'email', 'created_at', ...array_filter(Pessoa::CAMPOS, fn ($c) => Pessoa::existe('usuarios', $c))]);
        if (! $u) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }
        $u = (array) $u;

        $perfil = ['id' => (int) $u['id'], 'nome' => $u['nome'], 'email' => $u['email'], 'criado_em' => $u['created_at']];
        foreach (['cpf', 'telefone', 'whatsapp', ...Pessoa::ENDERECO] as $c) {
            $perfil[$c] = $u[$c] ?? null;
        }
        $perfil['data_nascimento'] = isset($u['data_nascimento']) ? substr((string) $u['data_nascimento'], 0, 10) : null;
        $perfil['pendencias'] = $this->pendencias($u, self::PERFIL_OBRIGATORIO);

        return ['status' => 200, 'body' => ['perfil' => $perfil]];
    }

    /**
     * Trocar o e-mail (que é o login) exige a senha atual. O CPF pode ser informado se ainda não existe;
     * para trocar um CPF já cadastrado, só pelo suporte. Os dados pessoais vão para usuarios (Pessoa).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarPerfil(int $usuarioId, array $data): array
    {
        $atual = DB::table('usuarios')->where('id', $usuarioId)->first(['id', 'email', 'senha_hash', 'cpf']);
        if (! $atual) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? $atual->email)), 'UTF-8');
        $trocaEmail = $email !== mb_strtolower(trim((string) $atual->email), 'UTF-8');
        $pessoais = Pessoa::normalizar(array_intersect_key($data, array_flip(['telefone', 'whatsapp', 'cpf', 'data_nascimento', ...Pessoa::ENDERECO])));

        $errors = [];
        if ($nome === '' || mb_strlen($nome) > 255) {
            $errors[] = 'Informe o nome (até 255 caracteres)';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido';
        } elseif ($trocaEmail && $this->usuarios->emailExists($email, $usuarioId)) {
            $errors[] = 'Este e-mail já está em uso por outro usuário';
        }
        $errors = array_merge($errors, $this->validarPessoais($pessoais, $usuarioId, $atual->cpf ? Documento::digitos($atual->cpf) : null));
        if ($errors !== []) {
            return $this->validacao($errors);
        }

        if ($trocaEmail && ($erro = $this->conferirSenhaAtual($usuarioId, (string) $atual->senha_hash, (string) ($data['senha_atual'] ?? ''))) !== null) {
            return $erro;
        }

        DB::transaction(function () use ($usuarioId, $email, $nome, $pessoais) {
            DB::table('usuarios')->where('id', $usuarioId)->update(['email' => $email, 'email_global' => $email, 'updated_at' => now()]);
            $this->usuarios->gravarDadosPessoais($usuarioId, ['nome' => $nome] + $pessoais);
        });

        return ['status' => 200, 'body' => ['type' => 'success', 'message' => 'Perfil atualizado'] + $this->perfil($usuarioId)['body']];
    }

    /**
     * @param  array<string, mixed>  $p  já normalizado (Pessoa::normalizar)
     * @return list<string>
     */
    private function validarPessoais(array $p, int $usuarioId, ?string $cpfAtual): array
    {
        $errors = [];
        if (! empty($p['telefone']) && ! in_array(strlen($p['telefone']), [10, 11], true)) {
            $errors[] = 'Telefone deve ter DDD + número (10 ou 11 dígitos)';
        }
        if (! empty($p['whatsapp']) && ! preg_match('/^\d{2}9\d{8}$/', $p['whatsapp'])) {
            $errors[] = 'WhatsApp deve ser um celular com DDD (11 dígitos)';
        }
        if (array_key_exists('cpf', $p)) {
            if ($cpfAtual !== null && $p['cpf'] !== $cpfAtual) {
                $errors[] = 'Para trocar o CPF, fale com o suporte do Check-in';
            } elseif ($p['cpf'] !== null && ! Documento::cpfValido($p['cpf'])) {
                $errors[] = 'CPF inválido';
            } elseif ($p['cpf'] !== null && $cpfAtual === null && $this->usuarios->cpfExists($p['cpf'], $usuarioId)) {
                $errors[] = 'Este CPF já pertence a outro cadastro no Check-in. Fale com o suporte para unificar.';
            }
        }
        if (! empty($p['data_nascimento'])) {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $p['data_nascimento']);
            $idade = $d ? $d->diff(new \DateTimeImmutable('today'))->y : null;
            if (! $d || $d->format('Y-m-d') !== $p['data_nascimento'] || $d > new \DateTimeImmutable('today') || $idade < 16 || $idade > 110) {
                $errors[] = 'Data de nascimento inválida';
            }
        }
        if (! empty($p['cep']) && ! Documento::cepValido($p['cep'])) {
            $errors[] = 'CEP deve ter 8 dígitos';
        }
        if (! empty($p['estado']) && ! Documento::ufValida($p['estado'])) {
            $errors[] = 'UF inválida';
        }
        foreach (['logradouro' => 255, 'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100] as $campo => $max) {
            if (! empty($p[$campo]) && mb_strlen($p[$campo]) > $max) {
                $errors[] = "O campo {$campo} aceita até {$max} caracteres";
            }
        }

        return $errors;
    }

    /**
     * Rótulos do que falta: um grupo conta como faltando se qualquer campo dele está vazio.
     *
     * @param  array<string, mixed>  $dados
     * @param  array<string, list<string>>  $grupos
     * @return list<string>
     */
    private function pendencias(array $dados, array $grupos): array
    {
        return array_keys(array_filter($grupos, fn ($campos) => array_filter($campos, fn ($c) => trim((string) ($dados[$c] ?? '')) === '') !== []));
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
        $extras = array_values(array_filter(['cnpj', 'responsavel_nome', 'responsavel_cpf', 'responsavel_telefone', 'responsavel_email'], fn ($c) => Schema::hasColumn('tenants', $c)));
        $t = DB::table('tenants')->where('id', $tenantId)->first(array_merge(['id', 'slug'], self::CAMPOS_ESCOLA, $extras));
        if (! $t) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Academia não encontrada']];
        }
        $t = (array) $t;
        $escola = ['id' => (int) $t['id']] + array_intersect_key($t, array_flip(['slug', ...self::CAMPOS_ESCOLA]));

        // Cadastro feito no site: o documento pode ser CNPJ (empresa) ou CPF do responsável (pessoa física)
        $cnpj = Documento::digitos($t['cnpj'] ?? '');
        $cpfResp = Documento::digitos($t['responsavel_cpf'] ?? '');
        $escola['documento'] = $cnpj !== '' ? ['tipo' => 'cnpj', 'numero' => $cnpj] : ($cpfResp !== '' ? ['tipo' => 'cpf', 'numero' => $cpfResp] : null);
        $escola['responsavel'] = [
            'nome' => $t['responsavel_nome'] ?? null, 'cpf' => $cpfResp ?: null,
            'telefone' => $t['responsavel_telefone'] ?? null, 'email' => $t['responsavel_email'] ?? null,
        ];
        $escola['pendencias'] = $this->pendencias($t, ['telefone' => ['telefone'], 'endereço da unidade' => ['cep', 'logradouro', 'numero', 'bairro', 'cidade', 'estado']]);

        return ['status' => 200, 'body' => ['escola' => $escola]];
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
