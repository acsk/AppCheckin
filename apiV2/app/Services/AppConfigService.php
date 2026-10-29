<?php

namespace App\Services;

use App\Repositories\AppConfigRepository;
use App\Support\AppConfigCatalogo;
use App\Support\AppConfigResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Configuração remota do app: o super admin edita um rascunho, revisa e publica; o app lê a
 * última publicação já resolvida para a academia, plataforma e versão dele.
 * Bloqueio emergencial e as telas antigas (obrigatória, limite do ranking) publicam na hora,
 * só a mudança deles, sem levar junto o resto do rascunho.
 */
class AppConfigService
{
    private const CACHE_KEY = 'app_config:publicada';

    public function __construct(
        private readonly AppConfigRepository $repo,
    ) {}

    /* ---------- Leitura (app e backend) ---------- */

    /** @return array{versao: int, global: array<string, mixed>, tenants: array<int, array<string, mixed>>} */
    public function publicada(): array
    {
        try {
            return Cache::rememberForever(self::cacheKey(), fn () => $this->repo->ultimaPublicacao() ?? self::vazia());
        } catch (\Throwable $e) {
            // Sem as tabelas (migration pendente) o app segue com os padrões do catálogo
            error_log('[AppConfigService::publicada] '.$e->getMessage());

            return self::vazia();
        }
    }

    /**
     * @param  array{tenant_id?: int|null, usuario_id?: int|null, plataforma?: string, versao_app?: string|null}  $ctx
     * @return array<string, mixed>
     */
    public function contrato(array $ctx): array
    {
        $ctx = $this->comPapeis($ctx);

        return AppConfigResolver::contrato(self::comApkPublicado(AppConfigResolver::resolver($this->publicada(), $ctx)), $ctx['plataforma'] ?? 'web', $ctx['versao_app'] ?? null);
    }

    /** @param  array{tenant_id?: int|null, usuario_id?: int|null, plataforma?: string, versao_app?: string|null}  $ctx */
    public function funcionalidadeAtiva(string $funcionalidade, array $ctx): bool
    {
        $resolvida = AppConfigResolver::resolver($this->publicada(), $this->comPapeis($ctx));

        return (bool) ($resolvida['funcionalidades'][$funcionalidade]['ativo'] ?? true);
    }

    public function parametro(string $parametro, ?int $tenantId = null): mixed
    {
        return AppConfigResolver::resolver($this->publicada(), ['tenant_id' => $tenantId])['parametros'][$parametro]['valor'] ?? null;
    }

    /** @return array<string, mixed> */
    public function versoes(): array
    {
        return self::comApkPublicado(AppConfigResolver::resolver($this->publicada(), []))['versoes'];
    }

    /**
     * APK publicado pelo build (AppVersionService::apkPublicado) vale como versão publicada e link,
     * sem preencher no painel. A versão mínima (bloqueio) continua no painel.
     *
     * @param  array<string, mixed>  $resolvida  saída de AppConfigResolver::resolver()
     * @return array<string, mixed>
     */
    private static function comApkPublicado(array $resolvida): array
    {
        if ($apk = AppVersionService::apkPublicado()) {
            $resolvida['versoes']['android_versao_recomendada'] = $apk['version'];
            $resolvida['versoes']['android_apk_url'] = $apk['url'];
        }

        return $resolvida;
    }

    /* ---------- Super admin ---------- */

    /** @return array{status: int, body: array<string, mixed>} */
    public function estado(): array
    {
        $rascunho = $this->repo->rascunho();
        $publicada = $this->repo->ultimaPublicacao() ?? self::vazia();
        $tenantIds = array_values(array_unique(array_merge(array_keys($rascunho['tenants']), array_keys($publicada['tenants']))));

        return $this->ok([
            'catalogo' => AppConfigCatalogo::lista(),
            'versao_publicada' => $publicada['versao'],
            'publicada' => $publicada,
            'rascunho' => $rascunho,
            'bloqueios' => array_keys(array_filter($publicada['global'], fn ($v, $k) => str_starts_with($k, 'bloqueio.') && $v, ARRAY_FILTER_USE_BOTH)),
            'pendencias' => $this->pendencias($rascunho, $publicada),
            'academias' => $this->repo->nomesTenants($tenantIds),
        ]);
    }

    /**
     * Altera o rascunho. valor null = volta a herdar (global → padrão; academia → global).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function definir(?int $tenantId, string $chave, mixed $valor, ?int $userId): array
    {
        $d = AppConfigCatalogo::definicao($chave);
        if (! $d || ! empty($d['interno'])) {
            return $this->erro('Configuração desconhecida.', 422);
        }
        if ($tenantId !== null) {
            if ($d['escopo'] !== 'tenant') {
                return $this->erro('Esta configuração vale para todas as academias e não pode ser sobrescrita.', 422);
            }
            if (! $this->repo->tenantExiste($tenantId)) {
                return $this->erro('Academia não encontrada.', 404);
            }
        }
        if ($valor !== null) {
            [$valor, $erro] = AppConfigCatalogo::validar($chave, $valor);
            if ($erro !== null) {
                return ['status' => 422, 'body' => ['error' => $erro, 'campos' => [$chave => $erro]]];
            }
            // Herança: o global igual ao padrão não precisa ser guardado
            if ($tenantId === null && $valor === $d['padrao']) {
                $valor = null;
            }
        }

        $rascunho = $this->repo->rascunho();
        $anterior = $tenantId === null ? ($rascunho['global'][$chave] ?? null) : ($rascunho['tenants'][$tenantId][$chave] ?? null);
        DB::transaction(function () use ($tenantId, $chave, $valor, $anterior, $userId) {
            $this->repo->gravar($tenantId, $chave, $valor, $userId);
            $this->repo->auditar('alterar', $tenantId, $chave, $anterior, $valor, null, null, $userId);
        });

        return $this->estado();
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function publicar(?string $nota, ?int $userId): array
    {
        $rascunho = $this->repo->rascunho();
        $pendencias = $this->pendencias($rascunho, $this->repo->ultimaPublicacao() ?? self::vazia());
        if ($pendencias === []) {
            return $this->erro('Não há alterações para publicar.', 422);
        }

        $nota = $this->nota($nota);
        DB::transaction(function () use ($rascunho, $nota, $userId, $pendencias) {
            $versao = $this->repo->publicar($rascunho, $nota, 'publicacao', null, $userId);
            $this->repo->auditar('publicar', null, null, null, count($pendencias), $versao, $nota, $userId);
        });
        $this->limparCache();

        return $this->estado();
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function descartar(?int $userId): array
    {
        $publicada = $this->repo->ultimaPublicacao() ?? self::vazia();
        DB::transaction(function () use ($publicada, $userId) {
            $this->repo->substituirRascunho($publicada, $userId);
            $this->repo->auditar('descartar', null, null, null, null, $publicada['versao'], null, $userId);
        });

        return $this->estado();
    }

    /**
     * Kill switch: desliga (ou religa) a funcionalidade para todos, publicando na hora.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function bloquear(string $funcionalidade, bool $bloquear, ?string $nota, ?int $userId): array
    {
        if (! isset(AppConfigCatalogo::FUNCIONALIDADES[$funcionalidade])) {
            return $this->erro('Funcionalidade desconhecida.', 404);
        }
        $nome = AppConfigCatalogo::FUNCIONALIDADES[$funcionalidade]['nome'];
        $this->aplicarAgora(null, "bloqueio.{$funcionalidade}", $bloquear ?: null, $this->nota($nota) ?? ($bloquear ? "Bloqueio emergencial: {$nome}" : "Fim do bloqueio: {$nome}"), $userId, $bloquear ? 'bloquear' : 'desbloquear');

        return $this->estado();
    }

    /**
     * Publica só esta mudança sobre a última publicação (o rascunho recebe a mesma mudança).
     */
    public function aplicarAgora(?int $tenantId, string $chave, mixed $valor, ?string $nota, ?int $userId, string $acao = 'imediata'): int
    {
        $d = AppConfigCatalogo::definicao($chave);
        if ($valor !== null && $tenantId === null && $d && $valor === $d['padrao']) {
            $valor = null;
        }

        $versao = DB::transaction(function () use ($tenantId, $chave, $valor, $nota, $userId, $acao) {
            $pub = $this->repo->ultimaPublicacao() ?? self::vazia();
            $anterior = $tenantId === null ? ($pub['global'][$chave] ?? null) : ($pub['tenants'][$tenantId][$chave] ?? null);
            if ($tenantId === null) {
                $pub['global'] = self::com($pub['global'], $chave, $valor);
            } else {
                $pub['tenants'][$tenantId] = self::com($pub['tenants'][$tenantId] ?? [], $chave, $valor);
                if ($pub['tenants'][$tenantId] === []) {
                    unset($pub['tenants'][$tenantId]);
                }
            }
            $this->repo->gravar($tenantId, $chave, $valor, $userId);
            $versao = $this->repo->publicar($pub, $nota, 'imediata', null, $userId);
            $this->repo->auditar($acao, $tenantId, $chave, $anterior, $valor, $versao, $nota, $userId);

            return $versao;
        });
        $this->limparCache();

        return $versao;
    }

    /**
     * Rollback: a versão escolhida vira a publicação mais nova (o rascunho é substituído por ela).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function restaurar(int $versao, ?int $userId): array
    {
        $alvo = $this->repo->publicacao($versao);
        if (! $alvo) {
            return $this->erro('Versão não encontrada.', 404);
        }
        DB::transaction(function () use ($alvo, $versao, $userId) {
            $this->repo->substituirRascunho($alvo, $userId);
            $nota = "Restaurada a versão {$versao}";
            $nova = $this->repo->publicar($alvo, $nota, 'rollback', $versao, $userId);
            $this->repo->auditar('rollback', null, null, null, null, $nova, $nota, $userId);
        });
        $this->limparCache();

        return $this->estado();
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function historico(): array
    {
        return $this->ok(['publicacoes' => $this->repo->publicacoes(), 'auditoria' => $this->repo->auditoria()]);
    }

    /**
     * O que o app recebe numa academia, plataforma e versão (com a origem de cada valor).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function efetiva(?int $tenantId, string $plataforma, ?string $versaoApp): array
    {
        if ($tenantId !== null && ! $this->repo->tenantExiste($tenantId)) {
            return $this->erro('Academia não encontrada.', 404);
        }
        $plataforma = self::plataforma($plataforma);
        // Sem usuário: o rollout parcial aparece como desligado; os papéis do app são aluno e professor
        $resolvida = self::comApkPublicado(AppConfigResolver::resolver($this->publicada(), ['tenant_id' => $tenantId, 'papeis' => [1, 2], 'plataforma' => $plataforma, 'versao_app' => $versaoApp]));

        return $this->ok(['efetiva' => $resolvida, 'contrato' => AppConfigResolver::contrato($resolvida, $plataforma, $versaoApp)]);
    }

    public static function plataforma(?string $p): string
    {
        $p = strtolower(trim((string) $p));

        return in_array($p, ['web', 'android', 'ios'], true) ? $p : 'web';
    }

    /* ---------- Internos ---------- */

    /**
     * @param  array{global: array<string, mixed>, tenants: array<int, array<string, mixed>>}  $rascunho
     * @param  array{global: array<string, mixed>, tenants: array<int, array<string, mixed>>}  $publicada
     * @return list<array{tenant_id: int|null, chave: string, de: mixed, para: mixed}>
     */
    private function pendencias(array $rascunho, array $publicada): array
    {
        $out = [];
        $comparar = function (?int $tenantId, array $novo, array $antigo) use (&$out) {
            foreach (array_unique(array_merge(array_keys($novo), array_keys($antigo))) as $chave) {
                $para = $novo[$chave] ?? null;
                $de = $antigo[$chave] ?? null;
                if (json_encode($para) !== json_encode($de)) {
                    $out[] = ['tenant_id' => $tenantId, 'chave' => $chave, 'de' => $de, 'para' => $para];
                }
            }
        };
        $comparar(null, $rascunho['global'], $publicada['global']);
        foreach (array_unique(array_merge(array_keys($rascunho['tenants']), array_keys($publicada['tenants']))) as $tenantId) {
            $comparar((int) $tenantId, $rascunho['tenants'][$tenantId] ?? [], $publicada['tenants'][$tenantId] ?? []);
        }

        return $out;
    }

    /** Papéis do usuário na academia (para funcionalidades restritas por papel). */
    private function comPapeis(array $ctx): array
    {
        // Só consulta quando alguma funcionalidade do catálogo é restrita por papel
        $restritas = array_filter(AppConfigCatalogo::definicoes(), fn ($d) => ! empty($d['papeis']));
        if ($restritas !== [] && ! isset($ctx['papeis']) && ! empty($ctx['usuario_id']) && ! empty($ctx['tenant_id'])) {
            $ctx['papeis'] = $this->repo->papeisDoUsuario((int) $ctx['usuario_id'], (int) $ctx['tenant_id']);
        }

        return $ctx;
    }

    private function limparCache(): void
    {
        Cache::forget(self::cacheKey());
    }

    /** Por banco: o cache (arquivo) é compartilhado entre ambientes que usam bancos diferentes (ex.: testes). */
    private static function cacheKey(): string
    {
        $conexao = (string) config('database.default');

        return self::CACHE_KEY.':'.$conexao.':'.md5((string) config("database.connections.{$conexao}.database"));
    }

    private function nota(?string $nota): ?string
    {
        $nota = trim((string) $nota);

        return $nota === '' ? null : mb_substr($nota, 0, 255);
    }

    private static function com(array $valores, string $chave, mixed $valor): array
    {
        if ($valor === null) {
            unset($valores[$chave]);
        } else {
            $valores[$chave] = $valor;
        }

        return $valores;
    }

    /** @return array{versao: int, global: array<string, mixed>, tenants: array<int, array<string, mixed>>} */
    private static function vazia(): array
    {
        return ['versao' => 0, 'global' => [], 'tenants' => []];
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
