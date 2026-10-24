<?php

namespace App\Services;

use App\Support\AppConfigCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versão do app web publicada pelo build (app_versions). A obrigatoriedade e o limite do ranking
 * moram na configuração remota (AppConfigService); os métodos daqui seguem para as telas e APKs antigos.
 */
class AppVersionService
{
    public function __construct(
        private ?AppConfigService $config = null,
    ) {}

    private function config(): AppConfigService
    {
        return $this->config ??= app(AppConfigService::class);
    }

    /**
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string, obrigatoria: bool}
     */
    public function show(string $app): array
    {
        $row = $this->find($app);
        if ($row) {
            return $this->format($app, (int) $row->version, (int) $row->release, (string) $row->release_date, $this->obrigatoria());
        }

        $cfg = config("appcheckin.app_versions.{$app}", [
            'version' => 1,
            'release' => 0,
            'release_date' => date('Y-m-d'),
        ]);

        return $this->format(
            $app,
            (int) ($cfg['version'] ?? 1),
            (int) ($cfg['release'] ?? 0),
            (string) ($cfg['release_date'] ?? date('Y-m-d')),
            $this->obrigatoria(),
        );
    }

    /**
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string, obrigatoria: bool}
     */
    public function store(string $app, int $version, int $release, string $releaseDate): array
    {
        $now = now();
        $existing = $this->find($app);

        if ($existing) {
            DB::table('app_versions')->where('app', $app)->update([
                'version' => $version,
                'release' => $release,
                'release_date' => $releaseDate,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('app_versions')->insert([
                'app' => $app,
                'version' => $version,
                'release' => $release,
                'release_date' => $releaseDate,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->format($app, $version, $release, $releaseDate, $this->obrigatoria());
    }

    /**
     * Super admin decide se a versão nova do app web bloqueia (true) ou só avisa (false). Publica na hora.
     *
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string, obrigatoria: bool}
     */
    public function definirObrigatoria(string $app, bool $obrigatoria, ?int $userId = null): array
    {
        $this->config()->aplicarAgora(null, 'versao.web_obrigatoria', $obrigatoria, $obrigatoria ? 'Atualização obrigatória ligada' : 'Atualização passou a ser opcional', $userId);

        return $this->show($app);
    }

    /** Limite do ranking na Home (global, com sobrescrita por academia). */
    public function limiteRankingCheckins(?int $tenantId = null): int
    {
        return min(100, max(1, (int) ($this->config()->parametro('ranking_limite_home', $tenantId) ?? 15)));
    }

    public function definirLimiteRankingCheckins(int $limite, ?int $userId = null): int
    {
        if ($limite < 1 || $limite > 100) {
            throw new \InvalidArgumentException('O limite deve estar entre 1 e 100.');
        }
        $this->config()->aplicarAgora(null, 'parametro.ranking_limite_home', $limite, "Limite do ranking: {$limite}", $userId);

        return $this->limiteRankingCheckins();
    }

    /**
     * APK publicado no formato antigo de /version/mobile. APKs anteriores à configuração remota não mandam
     * a versão, então só ficam bloqueados quando a mínima alcança a publicada.
     *
     * @return array{version: string, apk_url: string, obrigatoria: bool}|null
     */
    public function androidRelease(): ?array
    {
        $v = $this->config()->versoes();
        $versao = (string) ($v['android_versao_recomendada'] ?? '');
        $url = (string) ($v['android_apk_url'] ?? '');
        if ($versao === '' || $url === '') {
            return null;
        }
        $minima = (string) ($v['android_versao_minima'] ?? '');

        return [
            'version' => $versao,
            'apk_url' => $url,
            'obrigatoria' => $minima !== '' && AppConfigCatalogo::compararVersoes($minima, $versao) >= 0,
        ];
    }

    private function obrigatoria(): bool
    {
        return (bool) ($this->config()->versoes()['web_obrigatoria'] ?? true);
    }

    private function find(string $app): ?object
    {
        if (! Schema::hasTable('app_versions')) {
            return null;
        }

        $row = DB::table('app_versions')->where('app', $app)->first();

        return $row ?: null;
    }

    /**
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string, obrigatoria: bool}
     */
    private function format(string $app, int $version, int $release, string $releaseDate, bool $obrigatoria = true): array
    {
        return [
            'app' => $app,
            'version' => sprintf('v%d.%d', $version, $release),
            'version_number' => $version,
            'release' => $release,
            'release_date' => substr($releaseDate, 0, 10),
            'obrigatoria' => $obrigatoria,
        ];
    }
}
