<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppVersionService
{
    /**
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string}
     */
    public function show(string $app): array
    {
        $row = $this->find($app);
        if ($row) {
            return $this->format($app, (int) $row->version, (int) $row->release, (string) $row->release_date);
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
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string}
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

        return $this->format($app, $version, $release, $releaseDate);
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
     * @return array{app: string, version: string, version_number: int, release: int, release_date: string}
     */
    private function format(string $app, int $version, int $release, string $releaseDate): array
    {
        return [
            'app' => $app,
            'version' => sprintf('v%d.%d', $version, $release),
            'version_number' => $version,
            'release' => $release,
            'release_date' => substr($releaseDate, 0, 10),
        ];
    }
}
