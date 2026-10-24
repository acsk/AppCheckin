<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppVersionService
{
    /**
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string, obrigatoria: bool}
     */
    public function show(string $app): array
    {
        $row = $this->find($app);
        if ($row) {
            return $this->format($app, (int) $row->version, (int) $row->release, (string) $row->release_date, $this->obrigatoria($row));
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

        return $this->format($app, $version, $release, $releaseDate, $existing ? $this->obrigatoria($existing) : true);
    }

    /**
     * Super admin decide se a versão nova bloqueia o app (true) ou só avisa (false).
     *
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string, obrigatoria: bool}
     */
    public function definirObrigatoria(string $app, bool $obrigatoria): array
    {
        if (! $this->find($app)) {
            $cfg = config("appcheckin.app_versions.{$app}", []);
            $this->store($app, (int) ($cfg['version'] ?? 1), (int) ($cfg['release'] ?? 0), (string) ($cfg['release_date'] ?? date('Y-m-d')));
        }

        if (Schema::hasColumn('app_versions', 'obrigatoria')) {
            DB::table('app_versions')->where('app', $app)->update(['obrigatoria' => $obrigatoria, 'updated_at' => now()]);
        }

        return $this->show($app);
    }

    /** Limite global do ranking na Home, definido pelo Super Admin. */
    public function limiteRankingCheckins(): int
    {
        $row = $this->find('mobile');

        return min(100, max(1, (int) ($row->ranking_checkins_limite ?? 15)));
    }

    public function definirLimiteRankingCheckins(int $limite): int
    {
        if ($limite < 1 || $limite > 100) {
            throw new \InvalidArgumentException('O limite deve estar entre 1 e 100.');
        }
        if (! $this->find('mobile')) {
            $current = $this->show('mobile');
            $this->store('mobile', $current['version_number'], $current['release'], $current['release_date']);
        }
        DB::table('app_versions')->where('app', 'mobile')->update([
            'ranking_checkins_limite' => $limite,
            'updated_at' => now(),
        ]);

        return $this->limiteRankingCheckins();
    }

    /** Sem a coluna (migration pendente) vale o comportamento antigo: obrigatória. */
    private function obrigatoria(object $row): bool
    {
        return ! property_exists($row, 'obrigatoria') || (bool) $row->obrigatoria;
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
