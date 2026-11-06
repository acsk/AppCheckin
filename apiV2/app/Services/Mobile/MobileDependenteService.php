<?php

namespace App\Services\Mobile;

use App\Repositories\UsuarioRepository;
use App\Services\JwtService;
use App\Support\Documento;
use App\Support\Pessoa;
use App\Support\ResponsavelLegal;
use App\Support\SexoAluno;
use Illuminate\Support\Facades\DB;

/**
 * Dependentes: menores cadastrados na conta do responsável (usuarios.responsavel_usuario_id).
 * Não precisam de e-mail nem CPF. O responsável troca para o perfil do dependente no app (token do
 * dependente com o claim responsavel_id) e volta para o dele pelo mesmo caminho.
 */
class MobileDependenteService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly JwtService $jwt,
    ) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function listar(int $responsavelId, int $tenantId): array
    {
        $this->usuarios->ligarDependentesPorCpf($responsavelId);
        $linhas = DB::table('usuarios as u')
            ->leftJoin('alunos as a', 'a.usuario_id', '=', 'u.id')
            ->leftJoin('tenant_usuario_papel as tup', function ($j) use ($tenantId) {
                $j->on('tup.usuario_id', '=', 'u.id')->where('tup.tenant_id', $tenantId)->where('tup.papel_id', 1);
            })
            ->where('u.responsavel_usuario_id', $responsavelId)
            ->orderBy('u.data_nascimento', 'desc')
            ->orderBy('u.nome')
            ->get(['u.id', 'u.nome', 'u.email', 'u.cpf', 'u.data_nascimento', 'u.sexo', 'u.responsavel_parentesco', 'a.id as aluno_id', 'tup.ativo as ativo_na_academia', Pessoa::select('foto_caminho', 'u', 'a'),
                Pessoa::existe('usuarios', 'dependente_pin_hash') ? DB::raw('CASE WHEN u.dependente_pin_hash IS NULL THEN 0 ELSE 1 END as tem_pin') : DB::raw('0 as tem_pin')]);

        $dependentes = $linhas->map(fn ($d) => [
            'usuario_id' => (int) $d->id,
            'aluno_id' => $d->aluno_id !== null ? (int) $d->aluno_id : null,
            'nome' => $d->nome,
            'data_nascimento' => $d->data_nascimento ? substr((string) $d->data_nascimento, 0, 10) : null,
            'idade' => ResponsavelLegal::idade($d->data_nascimento ? (string) $d->data_nascimento : null),
            'sexo' => $d->sexo,
            'cpf' => $d->cpf,
            'email' => $d->email,
            'parentesco' => $d->responsavel_parentesco,
            'foto_caminho' => $d->foto_caminho,
            'na_academia' => (int) ($d->ativo_na_academia ?? 0) === 1,
            // Login próprio: e-mail do responsável + este PIN (DependenteLoginService)
            'tem_pin' => (int) $d->tem_pin === 1,
        ])->all();

        return ['status' => 200, 'body' => ['success' => true, 'data' => ['dependentes' => $dependentes]]];
    }

    /**
     * @param  array<string, mixed>  $data  nome, data_nascimento (Y-m-d), sexo, parentesco; cpf e email opcionais
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $responsavelId, int $tenantId, array $data): array
    {
        $resp = DB::table('usuarios')->where('id', $responsavelId)->first(['id', 'nome', 'email', 'cpf', 'telefone', 'whatsapp']);
        if (! $resp) {
            return $this->erro('Responsável não encontrado', 'RESPONSAVEL_NAO_ENCONTRADO', 404);
        }
        $cpfResp = Documento::digitos((string) $resp->cpf);
        if (! Documento::cpfValido($cpfResp)) {
            return $this->erro('Complete o seu CPF em Conta antes de adicionar dependentes. Ele vai nas cobranças da criança.', 'RESPONSAVEL_SEM_CPF', 422);
        }

        [$campos, $erros] = $this->validarDependente($data, $cpfResp, (string) $resp->email);
        if ($erros !== []) {
            return ['status' => 422, 'body' => ['success' => false, 'code' => 'VALIDATION_ERROR', 'error' => $erros[0], 'errors' => $erros]];
        }
        ['nome' => $nome, 'nascimento' => $nascimento, 'sexo' => $sexo, 'parentesco' => $parentesco, 'cpf' => $cpf, 'email' => $email] = $campos;

        $guardiao = [
            'responsavel_nome' => $resp->nome,
            'responsavel_cpf' => $cpfResp,
            'responsavel_email' => $resp->email,
            'responsavel_telefone' => $resp->telefone ?: $resp->whatsapp,
            'responsavel_parentesco' => $parentesco,
        ];

        // CPF que já tem cadastro: só vira dependente se já era desta pessoa (cadastrado pela recepção
        // com este responsável). Senão é a conta de outra pessoa.
        if ($cpf !== '' && ($existente = DB::table('usuarios')->where('cpf', $cpf)->first(['id', 'responsavel_cpf', 'responsavel_usuario_id']))) {
            $deste = (int) ($existente->responsavel_usuario_id ?? 0) === $responsavelId
                || Documento::digitos((string) ($existente->responsavel_cpf ?? '')) === $cpfResp;
            if (! $deste) {
                return $this->erro('Esse CPF já tem cadastro no Check-in com outro responsável. Fale com a recepção.', 'CPF_ALREADY_EXISTS', 409);
            }
            DB::table('usuarios')->where('id', $existente->id)->update(['responsavel_usuario_id' => $responsavelId, 'updated_at' => now()]);
            $this->usuarios->vincularComoAluno((int) $existente->id, $tenantId);

            return $this->criado((int) $existente->id, $tenantId, true);
        }
        if ($email !== '' && $this->usuarios->emailExists($email)) {
            return $this->erro('Esse e-mail já tem cadastro. Deixe em branco ou use outro.', 'EMAIL_ALREADY_EXISTS', 409);
        }

        $usuarioId = DB::transaction(function () use ($nome, $email, $cpf, $nascimento, $sexo, $guardiao, $responsavelId, $tenantId) {
            $id = (int) DB::table('usuarios')->insertGetId([
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'email' => $email !== '' ? $email : null,
                'email_global' => $email !== '' ? $email : null,
                // Sem login próprio: a senha é aleatória e ninguém a conhece
                'senha_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'cpf' => $cpf !== '' ? $cpf : null,
                'responsavel_usuario_id' => $responsavelId,
                'ativo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->usuarios->gravarDadosPessoais($id, ['data_nascimento' => $nascimento, 'sexo' => $sexo] + $guardiao);
            $this->usuarios->vincularComoAluno($id, $tenantId);

            return $id;
        });

        return $this->criado($usuarioId, $tenantId, false);
    }

    /**
     * Edita o dependente (nome, nascimento, sexo, parentesco, CPF e e-mail opcionais).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $responsavelId, int $dependenteId, array $data): array
    {
        $dep = DB::table('usuarios')->where('id', $dependenteId)->where('responsavel_usuario_id', $responsavelId)->first(['id']);
        $resp = DB::table('usuarios')->where('id', $responsavelId)->first(['email', 'cpf']);
        if (! $dep || ! $resp) {
            return $this->erro('Dependente não encontrado', 'DEPENDENTE_NAO_ENCONTRADO', 404);
        }
        [$c, $erros] = $this->validarDependente($data, Documento::digitos((string) $resp->cpf), (string) $resp->email);
        if ($erros !== []) {
            return ['status' => 422, 'body' => ['success' => false, 'code' => 'VALIDATION_ERROR', 'error' => $erros[0], 'errors' => $erros]];
        }
        if ($c['cpf'] !== '' && $this->usuarios->cpfExists($c['cpf'], $dependenteId)) {
            return $this->erro('Esse CPF já tem cadastro no Check-in. Fale com a recepção.', 'CPF_ALREADY_EXISTS', 409);
        }
        if ($c['email'] !== '' && $this->usuarios->emailExists($c['email'], $dependenteId)) {
            return $this->erro('Esse e-mail já tem cadastro. Deixe em branco ou use outro.', 'EMAIL_ALREADY_EXISTS', 409);
        }

        DB::transaction(function () use ($dependenteId, $c) {
            $this->usuarios->updateAuthFields($dependenteId, ['email' => $c['email'], 'cpf' => $c['cpf']]);
            $this->usuarios->gravarDadosPessoais($dependenteId, [
                'nome' => $c['nome'], 'data_nascimento' => $c['nascimento'], 'sexo' => $c['sexo'], 'responsavel_parentesco' => $c['parentesco'],
            ]);
        });

        return ['status' => 200, 'body' => ['success' => true, 'message' => 'Dados atualizados']];
    }

    /**
     * Campos normalizados e erros do formulário do dependente.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array{nome: string, nascimento: string, sexo: ?string, parentesco: ?string, cpf: string, email: string}, 1: list<string>}
     */
    private function validarDependente(array $data, string $cpfResp, string $emailResp): array
    {
        $nome = trim(preg_replace('/\s+/', ' ', (string) ($data['nome'] ?? '')) ?? '');
        $nascimento = trim((string) ($data['data_nascimento'] ?? ''));
        $sexo = SexoAluno::normalizar($data['sexo'] ?? null);
        $parentesco = ResponsavelLegal::normalizarParentesco($data['parentesco'] ?? null);
        $cpf = Documento::digitos((string) ($data['cpf'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');

        $erros = [];
        if (count(array_filter(explode(' ', $nome))) < 2) {
            $erros[] = 'Informe nome e sobrenome do dependente';
        }
        $idade = ResponsavelLegal::idade($nascimento);
        if ($idade === null) {
            $erros[] = 'Data de nascimento inválida';
        } elseif ($idade >= ResponsavelLegal::MAIORIDADE) {
            $erros[] = 'Dependente precisa ter menos de 18 anos. Quem é maior de idade faz o próprio cadastro.';
        }
        if ($sexo === null) {
            $erros[] = SexoAluno::mensagemInvalido();
        }
        if (($data['parentesco'] ?? '') !== '' && $parentesco === null) {
            $erros[] = 'Parentesco inválido';
        }
        if ($cpf !== '' && ! Documento::cpfValido($cpf)) {
            $erros[] = 'CPF do dependente inválido';
        } elseif ($cpf !== '' && $cpf === $cpfResp) {
            $erros[] = 'Esse é o seu CPF. Deixe em branco se a criança não tiver CPF.';
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail do dependente inválido';
        } elseif ($email !== '' && $email === mb_strtolower($emailResp, 'UTF-8')) {
            $erros[] = 'Esse é o seu e-mail. Deixe em branco se a criança não tiver e-mail.';
        }

        return [['nome' => $nome, 'nascimento' => $nascimento, 'sexo' => $sexo, 'parentesco' => $parentesco, 'cpf' => $cpf, 'email' => $email], $erros];
    }

    /**
     * Token do dependente para o responsável usar o app no perfil dele.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function entrar(int $responsavelId, int $dependenteId, int $tenantId): array
    {
        $dep = DB::table('usuarios')->where('id', $dependenteId)->where('responsavel_usuario_id', $responsavelId)->first(['id', 'nome', 'email', 'ativo']);
        if (! $dep) {
            return $this->erro('Dependente não encontrado', 'DEPENDENTE_NAO_ENCONTRADO', 404);
        }
        if ((int) ($dep->ativo ?? 1) !== 1) {
            return $this->erro('O cadastro deste dependente está desativado. Fale com a recepção.', 'DEPENDENTE_INATIVO', 403);
        }

        // Treina na academia em que o responsável está agora
        $this->usuarios->vincularComoAluno($dependenteId, $tenantId);

        return $this->sessao($dependenteId, $tenantId, $responsavelId);
    }

    /**
     * Volta do perfil do dependente para o do responsável (chamado com o token do dependente).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function voltar(int $dependenteId, int $responsavelId, int $tenantId): array
    {
        $vinculado = DB::table('usuarios')->where('id', $dependenteId)->where('responsavel_usuario_id', $responsavelId)->exists();
        if (! $vinculado) {
            return $this->erro('Sessão de dependente inválida. Entre de novo.', 'DEPENDENTE_DESVINCULADO', 401);
        }

        return $this->sessao($responsavelId, $tenantId, null);
    }

    /**
     * Libera (ou troca) o login próprio do dependente: ele entra com o e-mail do responsável e este PIN.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function definirPin(int $responsavelId, int $dependenteId, string $pin): array
    {
        $dep = DB::table('usuarios')->where('id', $dependenteId)->where('responsavel_usuario_id', $responsavelId)->first(['id', 'nome', 'data_nascimento']);
        if (! $dep) {
            return $this->erro('Dependente não encontrado', 'DEPENDENTE_NAO_ENCONTRADO', 404);
        }
        if ($motivo = self::pinFraco($pin, $dep->data_nascimento ? (string) $dep->data_nascimento : null)) {
            return $this->erro($motivo, 'PIN_FRACO', 422);
        }
        DB::table('usuarios')->where('id', $dependenteId)->update([
            'dependente_pin_hash' => password_hash($pin, PASSWORD_BCRYPT),
            'dependente_pin_tentativas' => 0,
            'dependente_pin_bloqueado_ate' => null,
            'updated_at' => now(),
        ]);

        return ['status' => 200, 'body' => ['success' => true, 'message' => 'PIN salvo. '.\App\Services\DependenteLoginService::primeiroNome((string) $dep->nome).' já pode entrar com o seu e-mail e este PIN.']];
    }

    /**
     * Tira o login próprio. As sessões abertas com o PIN caem na próxima requisição (JwtAuthenticate).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function removerPin(int $responsavelId, int $dependenteId): array
    {
        $n = DB::table('usuarios')->where('id', $dependenteId)->where('responsavel_usuario_id', $responsavelId)
            ->update(['dependente_pin_hash' => null, 'dependente_pin_tentativas' => 0, 'dependente_pin_bloqueado_ate' => null, 'updated_at' => now()]);

        return $n ? ['status' => 200, 'body' => ['success' => true, 'message' => 'Acesso próprio removido.']]
            : $this->erro('Dependente não encontrado', 'DEPENDENTE_NAO_ENCONTRADO', 404);
    }

    /** Motivo de recusa do PIN, ou null se serve: 4 a 6 dígitos, sem sequência, repetição ou data de nascimento. */
    public static function pinFraco(string $pin, ?string $nascimento): ?string
    {
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            return 'O PIN tem de 4 a 6 números.';
        }
        if (preg_match('/^(\d)\1+$/', $pin) || str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            return 'PIN fácil demais. Evite números repetidos ou em sequência.';
        }
        if ($nascimento && ($d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($nascimento, 0, 10)))) {
            $datas = [$d->format('dm'), $d->format('md'), $d->format('Y'), $d->format('dmy'), $d->format('ymd'), $d->format('dmY'), $d->format('ymd')];
            foreach ($datas as $x) {
                if ($pin === $x || (strlen($pin) >= 4 && str_starts_with($d->format('dmY'), $pin))) {
                    return 'Não use a data de nascimento no PIN: muita gente sabe.';
                }
            }
        }

        return null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function sessao(int $usuarioId, int $tenantId, ?int $responsavelId): array
    {
        $usuario = DB::table('usuarios')->where('id', $usuarioId)->first(['id', 'nome', 'email', 'email_global']);
        $alunoId = $this->usuarios->findAlunoIdInTenant($usuarioId, $tenantId);
        $claims = [
            'user_id' => $usuarioId,
            'email' => $usuario->email,
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
        ];
        if ($responsavelId !== null) {
            $claims['responsavel_id'] = $responsavelId;
        }

        $papeis = $this->usuarios->getPapeis($usuarioId, $tenantId);
        $tenants = array_values(array_filter(
            $this->usuarios->getTenantsByUsuario($usuarioId),
            fn ($t) => (int) ($t['tenant']['id'] ?? 0) === $tenantId,
        ));

        return ['status' => 200, 'body' => [
            'success' => true,
            'token' => $this->jwt->encode($claims),
            'user' => [
                'id' => $usuarioId,
                'nome' => $usuario->nome,
                'email' => $usuario->email,
                'email_global' => $usuario->email_global ?? $usuario->email,
                'papel_id' => $papeis[0]['id'] ?? 1,
                'papeis' => $papeis,
                'responsavel_id' => $responsavelId,
            ],
            'tenants' => $tenants,
        ]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function criado(int $usuarioId, int $tenantId, bool $vinculadoExistente): array
    {
        $d = DB::table('usuarios')->where('id', $usuarioId)->first(['id', 'nome', 'data_nascimento']);

        return ['status' => $vinculadoExistente ? 200 : 201, 'body' => [
            'success' => true,
            'message' => $vinculadoExistente ? 'Cadastro existente vinculado à sua conta' : 'Dependente cadastrado',
            'data' => ['dependente' => [
                'usuario_id' => (int) $d->id,
                'aluno_id' => $this->usuarios->findAlunoIdInTenant($usuarioId, $tenantId),
                'nome' => $d->nome,
                'data_nascimento' => $d->data_nascimento ? substr((string) $d->data_nascimento, 0, 10) : null,
                'idade' => ResponsavelLegal::idade($d->data_nascimento ? (string) $d->data_nascimento : null),
            ]],
        ]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, string $code, int $status): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'error' => $mensagem, 'message' => $mensagem]];
    }
}
