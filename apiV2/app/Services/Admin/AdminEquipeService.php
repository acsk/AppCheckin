<?php

namespace App\Services\Admin;

use App\Repositories\AdminProfessorRepository;
use App\Services\PermissaoService;
use App\Support\Documento;
use App\Support\Papel;
use App\Support\Permissao;
use Illuminate\Support\Facades\DB;

/**
 * Equipe da academia (tela "Equipe e permissões"): Professor, Admin, Financeiro e Comercial.
 * Professor é um papel como os outros: ao ganhá-lo, o usuário recebe o perfil em `professores`
 * (AdminProfessorRepository::garantirPerfil), que é o que as turmas referenciam.
 *
 * Regras:
 * - Uma pessoa = um usuário: busca por CPF e depois e-mail; existindo, só ganha o papel (sem cadastro novo).
 * - Limite do plano (planos_sistema.max_admins do contrato vigente) conta toda a equipe; NULL = sem limite.
 * - O admin principal só é alterado pelo suporte (super admin); ninguém altera o próprio acesso.
 * - Só se concede/retira permissão não sensível que quem está editando também tem.
 */
class AdminEquipeService
{
    /** Papéis geridos nesta tela */
    public const PAPEIS = [Papel::PROFESSOR, Papel::ADMIN, Papel::FINANCEIRO, Papel::COMERCIAL];

    public function __construct(
        private readonly PermissaoService $permissoes,
        private readonly AdminProfessorRepository $professores,
    ) {}

    /**
     * @param  array{usuario_id: int, suporte: bool}  $ctx
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, array $ctx): array
    {
        $principal = $this->permissoes->adminPrincipalId($tenantId);
        $ids = DB::table('tenant_usuario_papel')->where('tenant_id', $tenantId)->where('ativo', 1)
            ->whereIn('papel_id', Papel::EQUIPE)->whereNotIn('usuario_id', $this->superAdmins())
            ->distinct()->pluck('usuario_id')->map(fn ($i) => (int) $i)->all();
        $usuarios = DB::table('usuarios')->whereIn('id', $ids ?: [0])->orderBy('nome')->get(['id', 'nome', 'email', 'telefone', 'cpf']);

        $membros = $usuarios->map(function ($u) use ($tenantId, $principal, $ctx) {
            $id = (int) $u->id;
            $papeis = array_values(array_intersect($this->permissoes->papeisAtivos($tenantId, $id), Papel::EQUIPE));
            sort($papeis);

            return [
                'id' => $id,
                'nome' => $u->nome,
                'email' => $u->email,
                'telefone' => $u->telefone,
                'cpf' => $u->cpf,
                'papeis' => $papeis,
                'admin_principal' => $id === $principal,
                'voce' => $id === $ctx['usuario_id'],
                'gerenciavel' => $this->bloqueio($tenantId, $id, $ctx) === null,
                'permissoes' => $this->permissoes->efetivas($tenantId, $id),
                'ajustes' => $this->permissoes->ajustes($tenantId, $id),
            ];
        })->all();

        return ['status' => 200, 'body' => [
            'membros' => $membros,
            'limite' => $this->limite($tenantId),
            'papeis_gerenciaveis' => self::PAPEIS,
            'catalogo' => $this->permissoes->catalogo(),
            'padrao' => Permissao::PADRAO,
        ]];
    }

    /**
     * Pessoa já cadastrada no Check-in, por CPF ou e-mail (para adicionar sem duplicar).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(int $tenantId, ?string $cpf, ?string $email): array
    {
        $r = $this->identificar($cpf, $email);
        if (isset($r['erro'])) {
            return $this->erro($r['erro'], 409, 'CADASTROS_DIFERENTES');
        }
        if (! $r['usuario']) {
            return ['status' => 200, 'body' => ['encontrado' => false]];
        }
        $u = $r['usuario'];

        return ['status' => 200, 'body' => [
            'encontrado' => true,
            'usuario' => ['id' => (int) $u->id, 'nome' => $u->nome, 'email' => $this->mascararEmail((string) $u->email)],
            'papeis_na_academia' => $this->permissoes->papeisAtivos($tenantId, (int) $u->id),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data  {cpf?, email?, nome?, telefone?, senha?, papeis: list<int>}
     * @param  array{usuario_id: int, suporte: bool}  $ctx
     * @return array{status: int, body: array<string, mixed>}
     */
    public function adicionar(int $tenantId, array $data, array $ctx): array
    {
        $papeis = $this->papeisValidos($data['papeis'] ?? []);
        if ($papeis === []) {
            return $this->erro('Escolha ao menos um papel: Professor, Admin, Financeiro ou Comercial.', 422, 'PAPEL_OBRIGATORIO');
        }

        $cpf = Documento::digitos((string) ($data['cpf'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        if ($cpf !== '' && ! Documento::cpfValido($cpf)) {
            return $this->erro('CPF inválido.', 422, 'CPF_INVALIDO');
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->erro('E-mail inválido.', 422, 'EMAIL_INVALIDO');
        }
        if ($cpf === '' && $email === '') {
            return $this->erro('Informe o CPF ou o e-mail.', 422, 'IDENTIFICACAO_OBRIGATORIA');
        }

        $r = $this->identificar($cpf, $email);
        if (isset($r['erro'])) {
            return $this->erro($r['erro'], 409, 'CADASTROS_DIFERENTES');
        }
        $usuario = $r['usuario'];

        $jaNaEquipe = $usuario && array_intersect($this->permissoes->papeisAtivos($tenantId, (int) $usuario->id), Papel::EQUIPE) !== [];
        if (! $jaNaEquipe && ($bloqueio = $this->excedeLimite($tenantId)) !== null) {
            return $bloqueio;
        }

        if (! $usuario) {
            $nome = trim((string) ($data['nome'] ?? ''));
            $senha = (string) ($data['senha'] ?? '');
            if ($nome === '' || $email === '') {
                return $this->erro('Pessoa nova: informe nome e e-mail.', 422, 'DADOS_OBRIGATORIOS');
            }
            if (strlen($senha) < 6) {
                return $this->erro('Pessoa nova: crie uma senha com ao menos 6 caracteres.', 422, 'SENHA_OBRIGATORIA');
            }
        } elseif ($usuario && $this->superAdmin((int) $usuario->id)) {
            return $this->erro('Este usuário é do suporte do Check-in.', 422, 'SUPER_ADMIN');
        }

        $usuarioId = DB::transaction(function () use ($usuario, $data, $cpf, $email, $tenantId, $papeis) {
            $id = $usuario ? (int) $usuario->id : (int) DB::table('usuarios')->insertGetId([
                'nome' => mb_strtoupper(trim((string) $data['nome']), 'UTF-8'),
                'email' => $email,
                'email_global' => $email,
                'telefone' => Documento::digitos((string) ($data['telefone'] ?? '')) ?: null,
                'cpf' => $cpf ?: null,
                'senha_hash' => password_hash((string) $data['senha'], PASSWORD_BCRYPT),
                'ativo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->ativarPapeis($tenantId, $id, $papeis);

            return $id;
        });

        return ['status' => 201, 'body' => [
            'message' => $usuario ? 'Pessoa já cadastrada no Check-in: papel adicionado nesta academia.' : 'Pessoa adicionada à equipe.',
            'usuario_id' => $usuarioId,
            'reaproveitado' => (bool) $usuario,
        ] + $this->listar($tenantId, $ctx)['body']];
    }

    /**
     * @param  array<string, mixed>  $data  {papeis?: list<int>, conceder?: list<string>, retirar?: list<string>}
     * @param  array{usuario_id: int, suporte: bool}  $ctx
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $tenantId, int $usuarioId, array $data, array $ctx): array
    {
        if (($b = $this->bloqueio($tenantId, $usuarioId, $ctx)) !== null) {
            return $b;
        }
        if (array_intersect($this->permissoes->papeisAtivos($tenantId, $usuarioId), Papel::EQUIPE) === []) {
            return $this->erro('Pessoa não faz parte da equipe.', 404, 'NAO_ENCONTRADO');
        }

        if (array_key_exists('papeis', $data) && $this->papeisValidos($data['papeis'] ?? []) === []) {
            return $this->erro('Mantenha ao menos um papel ou remova a pessoa da equipe.', 422, 'PAPEL_OBRIGATORIO');
        }

        $conceder = array_values(array_filter((array) ($data['conceder'] ?? []), 'is_string'));
        $retirar = array_values(array_filter((array) ($data['retirar'] ?? []), 'is_string'));
        foreach (array_merge($conceder, $retirar) as $codigo) {
            if (! Permissao::valida($codigo) || Permissao::sensivel($codigo)) {
                return $this->erro("Permissão {$codigo} não pode ser alterada aqui.", 422, 'PERMISSAO_INVALIDA');
            }
            if (! $ctx['suporte'] && ! $this->permissoes->tem($tenantId, $ctx['usuario_id'], $codigo)) {
                return $this->erro('Você só pode conceder ou retirar permissões que também tem.', 403, 'PERMISSAO_NEGADA');
            }
        }
        if (array_intersect($conceder, $retirar) !== []) {
            return $this->erro('A mesma permissão não pode ser concedida e retirada.', 422, 'PERMISSAO_INVALIDA');
        }

        DB::transaction(function () use ($tenantId, $usuarioId, $data, $conceder, $retirar, $ctx) {
            if (array_key_exists('papeis', $data)) {
                $papeis = $this->papeisValidos($data['papeis'] ?? []);
                DB::table('tenant_usuario_papel')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)
                    ->whereIn('papel_id', array_diff(self::PAPEIS, $papeis) ?: [0])->update(['ativo' => 0, 'updated_at' => now()]);
                $this->ativarPapeis($tenantId, $usuarioId, $papeis);
            }
            if (array_key_exists('conceder', $data) || array_key_exists('retirar', $data)) {
                // Ajustes não sensíveis são substituídos; concessões sensíveis (super admin) ficam
                DB::table('tenant_usuario_permissoes')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)
                    ->whereIn('permissao', Permissao::naoSensiveis())->delete();
                foreach ([PermissaoService::CONCEDER => $conceder, PermissaoService::RETIRAR => $retirar] as $efeito => $codigos) {
                    foreach (array_unique($codigos) as $codigo) {
                        DB::table('tenant_usuario_permissoes')->insert([
                            'tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'permissao' => $codigo,
                            'efeito' => $efeito, 'concedido_por' => $ctx['usuario_id'] ?: null, 'created_at' => now(),
                        ]);
                    }
                }
            }
        });

        return ['status' => 200, 'body' => ['message' => 'Equipe atualizada'] + $this->listar($tenantId, $ctx)['body']];
    }

    /**
     * Tira a pessoa da equipe (todos os papéis desta tela). Aluno segue como está; as turmas
     * do professor continuam com ele (o perfil em `professores` não é apagado).
     *
     * @param  array{usuario_id: int, suporte: bool}  $ctx
     * @return array{status: int, body: array<string, mixed>}
     */
    public function remover(int $tenantId, int $usuarioId, array $ctx): array
    {
        if (($b = $this->bloqueio($tenantId, $usuarioId, $ctx)) !== null) {
            return $b;
        }
        DB::transaction(function () use ($tenantId, $usuarioId) {
            DB::table('tenant_usuario_papel')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)
                ->whereIn('papel_id', self::PAPEIS)->update(['ativo' => 0, 'updated_at' => now()]);
            DB::table('tenant_usuario_permissoes')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)->delete();
        });

        return ['status' => 200, 'body' => ['message' => 'Pessoa removida da equipe'] + $this->listar($tenantId, $ctx)['body']];
    }

    /** @return array{max: ?int, usados: int, disponiveis: ?int} */
    public function limite(int $tenantId): array
    {
        $usados = DB::table('tenant_usuario_papel')->where('tenant_id', $tenantId)->where('ativo', 1)
            ->whereIn('papel_id', Papel::EQUIPE)->whereNotIn('usuario_id', $this->superAdmins())
            ->distinct()->count('usuario_id');

        $max = DB::table('tenant_planos_sistema as tp')
            ->join('planos_sistema as ps', 'ps.id', '=', 'tp.plano_sistema_id')
            ->where('tp.tenant_id', $tenantId)
            ->whereIn('tp.status_id', [1, 2])
            ->orderBy('tp.status_id')->orderByDesc('tp.id')
            ->value('ps.max_admins');
        $max = $max === null ? null : (int) $max;

        return ['max' => $max, 'usados' => $usados, 'disponiveis' => $max === null ? null : max(0, $max - $usados)];
    }

    /**
     * Pode entrar na equipe? Quem já é da equipe (ex.: admin que vira professor) não ocupa vaga nova.
     *
     * @return array{status: int, body: array<string, mixed>}|null null = pode
     */
    public function verificarVaga(int $tenantId, ?int $usuarioId): ?array
    {
        if ($usuarioId && array_intersect($this->permissoes->papeisAtivos($tenantId, $usuarioId), Papel::EQUIPE) !== []) {
            return null;
        }

        return $this->excedeLimite($tenantId);
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    public function excedeLimite(int $tenantId): ?array
    {
        $l = $this->limite($tenantId);
        if ($l['max'] !== null && $l['usados'] >= $l['max']) {
            return $this->erro(
                "O plano da academia permite {$l['max']} ".($l['max'] === 1 ? 'pessoa' : 'pessoas').' na equipe. Para adicionar mais, troque de plano.',
                422, 'LIMITE_EQUIPE',
            );
        }

        return null;
    }

    /**
     * Por que esta pessoa não pode ser alterada por quem está editando (null = pode).
     *
     * @param  array{usuario_id: int, suporte: bool}  $ctx
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function bloqueio(int $tenantId, int $usuarioId, array $ctx): ?array
    {
        if ($ctx['suporte']) {
            return null;
        }
        if ($usuarioId === $this->permissoes->adminPrincipalId($tenantId)) {
            return $this->erro('O admin principal só pode ser alterado pelo suporte do Check-in.', 403, 'ADMIN_PRINCIPAL');
        }
        if ($usuarioId === $ctx['usuario_id']) {
            return $this->erro('Você não pode alterar o próprio acesso.', 403, 'PROPRIO_ACESSO');
        }

        return null;
    }

    /**
     * Uma pessoa = um usuário: CPF e e-mail apontando para cadastros diferentes é conflito (unificar pelo suporte).
     *
     * @return array{usuario: ?object}|array{erro: string}
     */
    private function identificar(?string $cpf, ?string $email): array
    {
        $cpf = Documento::digitos((string) $cpf);
        $email = mb_strtolower(trim((string) $email), 'UTF-8');
        $porCpf = $cpf !== '' ? DB::table('usuarios')->where('cpf', $cpf)->first() : null;
        $porEmail = $email !== '' ? DB::table('usuarios')->where('email_global', $email)->orWhere('email', $email)->first() : null;

        if ($porCpf && $porEmail && (int) $porCpf->id !== (int) $porEmail->id) {
            return ['erro' => 'Este CPF e este e-mail pertencem a cadastros diferentes no Check-in. Peça ao suporte para unificá-los.'];
        }

        return ['usuario' => $porCpf ?? $porEmail];
    }

    /** @return list<int> */
    private function papeisValidos(mixed $papeis): array
    {
        return array_values(array_unique(array_intersect(array_map('intval', (array) $papeis), self::PAPEIS)));
    }

    /** @param  list<int>  $papeis */
    private function ativarPapeis(int $tenantId, int $usuarioId, array $papeis): void
    {
        foreach ($papeis as $papel) {
            DB::table('tenant_usuario_papel')->updateOrInsert(
                ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'papel_id' => $papel],
                ['ativo' => 1],
            );
        }
        if (in_array(Papel::PROFESSOR, $papeis, true)) {
            $this->professores->garantirPerfil($usuarioId);
        }
    }

    /** @return list<int> */
    private function superAdmins(): array
    {
        return DB::table('tenant_usuario_papel')->where('papel_id', Papel::SUPER_ADMIN)->pluck('usuario_id')->map(fn ($i) => (int) $i)->all() ?: [0];
    }

    private function superAdmin(int $usuarioId): bool
    {
        return in_array($usuarioId, $this->superAdmins(), true);
    }

    private function mascararEmail(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($u, 0, 2).str_repeat('•', max(1, mb_strlen($u) - 2)).'@'.$d;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function erro(string $mensagem, int $status, string $codigo): array
    {
        return ['status' => $status, 'body' => ['error' => $mensagem, 'message' => $mensagem, 'code' => $codigo]];
    }
}
