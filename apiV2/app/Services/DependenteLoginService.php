<?php

namespace App\Services;

use App\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\DB;

/**
 * Login próprio do dependente (menor sem e-mail): e-mail do responsável → escolhe o perfil → PIN.
 * O PIN é definido pelo responsável em Conta › Dependentes (MobileDependenteService::definirPin).
 *
 * Proteções: limite por IP nas duas etapas; 5 PINs errados travam o perfil por 15 minutos; o
 * responsável recebe uma notificação a cada acesso; a sessão leva o claim `dependente_login` e o
 * JwtAuthenticate bloqueia compras e pagamentos (quem paga é o responsável, pelo perfil dele).
 */
class DependenteLoginService
{
    public const MAX_TENTATIVAS = 5;

    public const BLOQUEIO_MINUTOS = 15;

    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly JwtService $jwt,
    ) {}

    /**
     * Primeiros nomes dos dependentes com acesso liberado (PIN definido) do responsável deste e-mail.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function perfis(string $email, string $ip): array
    {
        if ($bloqueio = $this->limitarIp($ip, 'dependente-perfis', 15)) {
            return $bloqueio;
        }
        $responsavel = $this->responsavelPorEmail($email);
        $perfis = $responsavel ? DB::table('usuarios')
            ->where('responsavel_usuario_id', $responsavel->id)
            ->whereNotNull('dependente_pin_hash')
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'nome' => self::primeiroNome((string) $d->nome)])
            ->all() : [];

        // Mesma resposta para e-mail inexistente e sem dependentes liberados: não confirma se a conta existe
        return ['status' => 200, 'body' => [
            'success' => true,
            'perfis' => $perfis,
            'message' => $perfis === []
                ? 'Nenhum perfil liberado para este e-mail. O responsável libera o acesso em Conta › Dependentes, criando um PIN.'
                : null,
        ]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function entrar(string $email, int $dependenteId, string $pin, ?int $tenantId, string $ip): array
    {
        if ($bloqueio = $this->limitarIp($ip, 'dependente-login', 20)) {
            return $bloqueio;
        }
        $responsavel = $this->responsavelPorEmail($email);
        $dep = $responsavel ? DB::table('usuarios')
            ->where('id', $dependenteId)
            ->where('responsavel_usuario_id', $responsavel->id)
            ->first(['id', 'nome', 'email', 'ativo', 'dependente_pin_hash', 'dependente_pin_tentativas', 'dependente_pin_bloqueado_ate']) : null;

        if (! $dep || ! $dep->dependente_pin_hash || (int) $dep->ativo !== 1) {
            return $this->erro('Perfil não encontrado ou sem acesso liberado.', 'DEPENDENTE_SEM_ACESSO', 404);
        }

        if ($dep->dependente_pin_bloqueado_ate && strtotime((string) $dep->dependente_pin_bloqueado_ate) > time()) {
            $min = (int) ceil((strtotime((string) $dep->dependente_pin_bloqueado_ate) - time()) / 60);

            return $this->erro("Muitas tentativas erradas. Tente de novo em {$min} min ou peça ao responsável para trocar o PIN.", 'PIN_BLOQUEADO', 429, ['retryAfter' => $min * 60]);
        }

        if (! password_verify($pin, (string) $dep->dependente_pin_hash)) {
            $tentativas = (int) $dep->dependente_pin_tentativas + 1;
            $trava = $tentativas >= self::MAX_TENTATIVAS;
            DB::table('usuarios')->where('id', $dep->id)->update([
                'dependente_pin_tentativas' => $trava ? 0 : $tentativas,
                'dependente_pin_bloqueado_ate' => $trava ? now()->addMinutes(self::BLOQUEIO_MINUTOS) : null,
            ]);
            if ($trava) {
                $this->notificar((int) $responsavel->id, (int) $dep->id, 'PIN errado várias vezes', 'Erraram o PIN de '.self::primeiroNome((string) $dep->nome).' '.self::MAX_TENTATIVAS.' vezes. O acesso ficou travado por '.self::BLOQUEIO_MINUTOS.' minutos.');

                return $this->erro('PIN errado. O acesso ficou travado por '.self::BLOQUEIO_MINUTOS.' minutos.', 'PIN_BLOQUEADO', 429);
            }
            $restam = self::MAX_TENTATIVAS - $tentativas;

            return $this->erro("PIN errado. Restam {$restam} ".($restam === 1 ? 'tentativa' : 'tentativas').'.', 'PIN_INVALIDO', 401);
        }

        DB::table('usuarios')->where('id', $dep->id)->update(['dependente_pin_tentativas' => 0, 'dependente_pin_bloqueado_ate' => null]);

        $tenants = $this->usuarios->getTenantsByUsuario((int) $dep->id);
        if ($tenantId !== null) {
            $tenants = array_values(array_filter($tenants, fn ($t) => (int) ($t['tenant']['id'] ?? 0) === $tenantId));
        }
        if ($tenants === []) {
            return $this->erro('Este perfil não está ligado a nenhuma academia ativa.', 'NO_TENANT_ACCESS', 403);
        }
        if (count($tenants) > 1) {
            // Escolhe a academia e repete o login com tenant_id (o PIN já conferido não conta tentativa)
            return ['status' => 200, 'body' => ['success' => true, 'requires_tenant_selection' => true, 'tenants' => $tenants]];
        }

        $tenant = (int) $tenants[0]['tenant']['id'];
        $papeis = $this->usuarios->getPapeis((int) $dep->id, $tenant);
        $token = $this->jwt->encode([
            'user_id' => (int) $dep->id,
            'email' => $dep->email,
            'tenant_id' => $tenant,
            'aluno_id' => $this->usuarios->findAlunoIdInTenant((int) $dep->id, $tenant),
            'dependente_login' => (int) $responsavel->id,
        ]);

        $this->notificar((int) $responsavel->id, (int) $dep->id, 'Novo acesso de '.self::primeiroNome((string) $dep->nome),
            self::primeiroNome((string) $dep->nome).' entrou no app com o PIN. Se não foi ele, troque o PIN em Conta › Dependentes.', $tenant);

        return ['status' => 200, 'body' => [
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'user' => [
                'id' => (int) $dep->id,
                'nome' => $dep->nome,
                'email' => $dep->email,
                'email_global' => $dep->email,
                'papel_id' => $papeis[0]['id'] ?? 1,
                'papeis' => $papeis,
                'dependente' => true,
                'responsavel_nome' => $responsavel->nome,
            ],
            'tenants' => $tenants,
            'requires_tenant_selection' => false,
        ]];
    }

    public static function primeiroNome(string $nome): string
    {
        $p = explode(' ', trim($nome))[0] ?? '';

        return mb_convert_case(mb_strtolower($p, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function responsavelPorEmail(string $email): ?object
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $u = $this->usuarios->findByEmailGlobal($email);

        return $u ? (object) ['id' => (int) $u->id, 'nome' => (string) $u->nome] : null;
    }

    /** Aviso ao responsável na central de notificações do app, em cada academia dele (ou só nesta). */
    private function notificar(int $responsavelId, int $dependenteId, string $titulo, string $mensagem, ?int $tenantId = null): void
    {
        try {
            $tenants = $tenantId !== null ? [$tenantId] : DB::table('tenant_usuario_papel')
                ->where('usuario_id', $dependenteId)->where('ativo', 1)->distinct()->pluck('tenant_id')->map(fn ($t) => (int) $t)->all();
            foreach ($tenants as $t) {
                DB::table('notificacoes')->insert([
                    'tenant_id' => $t,
                    'usuario_id' => $responsavelId,
                    'tipo' => 'acesso_dependente',
                    'titulo' => $titulo,
                    'mensagem' => $mensagem,
                    'dados' => json_encode(['dependente_id' => $dependenteId]),
                    'lida' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e); // aviso não pode impedir o login
        }
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function limitarIp(string $ip, string $acao, int $max): ?array
    {
        $r = (new RateLimiter($max, 15))->attempt($ip, $acao);
        if ($r['allowed']) {
            return null;
        }
        $retry = (int) ($r['retryAfter'] ?? 0);

        return $this->erro('Muitas tentativas. Tente de novo em '.max(1, (int) ceil($retry / 60)).' minutos.', 'RATE_LIMIT_EXCEEDED', 429, ['retryAfter' => $retry]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $mensagem, string $code, int $status, array $extra = []): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'type' => 'error', 'code' => $code, 'message' => $mensagem, 'error' => $mensagem] + $extra];
    }
}
