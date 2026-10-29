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
     * APK publicado em /version/mobile. O app compara a própria versão com version (aviso) e
     * versao_minima (bloqueio). obrigatoria serve aos APKs antigos, que não comparam a mínima:
     * só ficam bloqueados quando a mínima alcança a publicada.
     *
     * @return array{version: string, apk_url: string, obrigatoria: bool, versao_minima: ?string, version_code: ?int, release_date: ?string, tamanho: ?int}|null
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
        $apk = self::apkPublicado();
        $doBuild = $apk && $apk['version'] === $versao;

        return [
            'version' => $versao,
            'apk_url' => $url,
            'obrigatoria' => $minima !== '' && AppConfigCatalogo::compararVersoes($minima, $versao) >= 0,
            'versao_minima' => $minima ?: null,
            'version_code' => $doBuild ? $apk['version_code'] : null,
            'release_date' => $doBuild ? $apk['release_date'] : null,
            'tamanho' => $doBuild ? $apk['tamanho'] : null,
        ];
    }

    /**
     * Registra o APK que o build acabou de enviar (POST /version/android com o token de publicação).
     *
     * @param  array{version: string, version_code: int, apk_url: string, sha256?: ?string, tamanho?: ?int}  $apk
     * @return array<string, mixed>
     */
    public function storeAndroid(array $apk): array
    {
        $dados = [
            'version' => $apk['version_code'], 'release' => 0, 'release_date' => date('Y-m-d'),
            'versao_nome' => $apk['version'], 'url' => $apk['apk_url'],
            'sha256' => $apk['sha256'] ?? null, 'tamanho' => $apk['tamanho'] ?? null, 'updated_at' => now(),
        ];
        if ($this->find('android')) {
            DB::table('app_versions')->where('app', 'android')->update($dados);
        } else {
            DB::table('app_versions')->insert($dados + ['app' => 'android', 'created_at' => now()]);
        }

        return self::apkPublicado() ?? [];
    }

    /**
     * APK publicado pelo build: linha android de app_versions; sem ela, o android.json que o build
     * deixa em public/downloads. Vale como versão publicada e link; a mínima continua no painel.
     *
     * @return array{version: string, version_code: ?int, url: string, release_date: ?string, tamanho: ?int}|null
     */
    public static function apkPublicado(): ?array
    {
        try {
            $row = Schema::hasColumn('app_versions', 'versao_nome')
                ? DB::table('app_versions')->where('app', 'android')->whereNotNull('versao_nome')->whereNotNull('url')->first()
                : null;
        } catch (\Throwable) {
            $row = null;
        }
        if ($row) {
            return ['version' => (string) $row->versao_nome, 'version_code' => (int) $row->version, 'url' => (string) $row->url,
                'release_date' => (string) $row->release_date, 'tamanho' => $row->tamanho !== null ? (int) $row->tamanho : null];
        }

        $manifest = (string) config('appcheckin.android_release.manifest', '');
        $m = $manifest !== '' && is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
        $arquivo = basename((string) ($m['arquivo'] ?? ''));
        if (! preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? '')) || $arquivo === '' || ! is_file(dirname($manifest).'/'.$arquivo)) {
            return null;
        }

        return ['version' => (string) $m['version'], 'version_code' => isset($m['version_code']) ? (int) $m['version_code'] : null,
            'url' => rtrim((string) config('app.url'), '/').'/downloads/'.rawurlencode($arquivo),
            'release_date' => isset($m['gerado_em']) ? substr((string) $m['gerado_em'], 0, 10) : null,
            'tamanho' => isset($m['tamanho']) ? (int) $m['tamanho'] : null];
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
