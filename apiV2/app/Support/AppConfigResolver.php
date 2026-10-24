<?php

namespace App\Support;

/**
 * Resolve a configuração efetiva do app para um contexto (academia, papéis, plataforma, versão, usuário).
 *
 * Precedência: padrão do catálogo → valor global publicado → sobrescrita da academia (só itens de
 * escopo tenant) → papel → versão mínima da plataforma → rollout → bloqueio emergencial.
 * As restrições (papel, versão, rollout, bloqueio) só desligam; nenhuma sobrescrita as contorna.
 */
final class AppConfigResolver
{
    /**
     * @param  array{versao: int, global: array<string, mixed>, tenants: array<int|string, array<string, mixed>>}  $publicada
     * @param  array{tenant_id?: int|null, papeis?: list<int>, plataforma?: string, versao_app?: string|null, usuario_id?: int|null}  $ctx
     * @param  array<string, array<string, mixed>>|null  $definicoes  catálogo (testes podem trocar)
     * @return array{versao: int, funcionalidades: array<string, array{ativo: bool, origem: string}>, parametros: array<string, array{valor: mixed, origem: string}>, versoes: array<string, mixed>}
     */
    public static function resolver(array $publicada, array $ctx, ?array $definicoes = null): array
    {
        $defs = $definicoes ?? AppConfigCatalogo::definicoes();
        $global = $publicada['global'] ?? [];
        $tenantId = $ctx['tenant_id'] ?? null;
        $doTenant = $tenantId ? ($publicada['tenants'][$tenantId] ?? $publicada['tenants'][(string) $tenantId] ?? []) : [];
        $plataforma = $ctx['plataforma'] ?? 'web';
        $versaoApp = $ctx['versao_app'] ?? null;
        $papeis = $ctx['papeis'] ?? [];
        $usuarioId = $ctx['usuario_id'] ?? null;

        $valor = function (string $chave) use ($defs, $global, $doTenant): array {
            $d = $defs[$chave];
            if ($d['escopo'] === 'tenant' && array_key_exists($chave, $doTenant)) {
                return [$doTenant[$chave], 'academia'];
            }
            if (array_key_exists($chave, $global)) {
                return [$global[$chave], 'global'];
            }

            return [$d['padrao'], 'padrao'];
        };

        $out = ['versao' => (int) ($publicada['versao'] ?? 0), 'funcionalidades' => [], 'parametros' => [], 'versoes' => []];
        foreach ($defs as $chave => $d) {
            if ($d['grupo'] === 'funcionalidade' && str_starts_with($chave, 'funcionalidade.')) {
                $out['funcionalidades'][$d['item']] = self::funcionalidade($d, $valor, $plataforma, $versaoApp, $papeis, $usuarioId);
            } elseif ($d['grupo'] === 'parametro') {
                [$v, $origem] = $valor($chave);
                $out['parametros'][$d['item']] = ['valor' => $v, 'origem' => $origem];
            } elseif ($d['grupo'] === 'versao') {
                $out['versoes'][$d['item']] = $valor($chave)[0];
            }
        }

        return $out;
    }

    /**
     * Contrato enviado ao app (versão 1): só valores, sem origem nem itens internos.
     *
     * @param  array<string, mixed>  $resolvida  saída de resolver()
     * @return array<string, mixed>
     */
    public static function contrato(array $resolvida, string $plataforma, ?string $versaoApp): array
    {
        return [
            'versao_contrato' => AppConfigCatalogo::VERSAO_CONTRATO,
            'versao_config' => $resolvida['versao'],
            'cache_ttl_segundos' => (int) ($resolvida['parametros']['cache_ttl_segundos']['valor'] ?? 300),
            'funcionalidades' => array_map(fn ($f) => $f['ativo'], $resolvida['funcionalidades']),
            'parametros' => array_map(fn ($p) => $p['valor'], array_diff_key($resolvida['parametros'], ['cache_ttl_segundos' => true])),
            'atualizacao' => self::atualizacao($resolvida['versoes'], $plataforma, $versaoApp),
        ];
    }

    /**
     * Situação de atualização para a plataforma. Web: decide o super admin (obrigatoria).
     * Nativo: abaixo da mínima bloqueia; abaixo da publicada só avisa.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    public static function atualizacao(array $v, string $plataforma, ?string $versaoApp): array
    {
        $mensagem = (string) ($v['mensagem_atualizacao'] ?? '');
        if ($plataforma === 'web') {
            return ['obrigatoria' => (bool) ($v['web_obrigatoria'] ?? true), 'mensagem' => $mensagem];
        }

        $minima = (string) ($v["{$plataforma}_versao_minima"] ?? '');
        $recomendada = (string) ($v["{$plataforma}_versao_recomendada"] ?? '');
        $url = (string) ($plataforma === 'android' ? ($v['android_apk_url'] ?? '') : ($v['ios_loja_url'] ?? ''));
        $abaixo = fn (string $alvo) => $alvo !== '' && $versaoApp !== null && $versaoApp !== '' && AppConfigCatalogo::compararVersoes($versaoApp, $alvo) < 0;

        return [
            'versao_minima' => $minima ?: null,
            'versao_recomendada' => $recomendada ?: null,
            'url' => $url ?: null,
            'obrigatoria' => $abaixo($minima),
            'disponivel' => $abaixo($recomendada) || $abaixo($minima),
            'mensagem' => $mensagem,
        ];
    }

    /** Balde estável 0..99 do usuário para a funcionalidade (mesma resposta a cada consulta). */
    public static function balde(string $funcionalidade, int $usuarioId): int
    {
        return crc32("{$funcionalidade}:{$usuarioId}") % 100;
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  list<int>  $papeis
     * @return array{ativo: bool, origem: string}
     */
    private static function funcionalidade(array $d, callable $valor, string $plataforma, ?string $versaoApp, array $papeis, ?int $usuarioId): array
    {
        $k = $d['item'];
        [$ligada, $origem] = $valor("funcionalidade.{$k}");
        if (! $ligada) {
            return ['ativo' => false, 'origem' => $origem];
        }
        if ($valor("bloqueio.{$k}")[0]) {
            return ['ativo' => false, 'origem' => 'bloqueio'];
        }
        if (! empty($d['papeis']) && ! array_intersect($d['papeis'], $papeis)) {
            return ['ativo' => false, 'origem' => 'papel'];
        }
        $minima = $d['versao_minima'][$plataforma] ?? null;
        if ($plataforma !== 'web' && $minima && (! $versaoApp || AppConfigCatalogo::compararVersoes($versaoApp, $minima) < 0)) {
            return ['ativo' => false, 'origem' => 'versao'];
        }
        $pct = (int) $valor("rollout.{$k}")[0];
        if ($pct < 100 && ($usuarioId === null || self::balde($k, $usuarioId) >= $pct)) {
            return ['ativo' => false, 'origem' => 'rollout'];
        }

        return ['ativo' => true, 'origem' => $origem];
    }
}
