<?php

namespace App\Support;

/**
 * Caminhos e resolução de fotos de perfil (apiV2 + fallback Slim legado).
 */
final class FotoStorage
{
    public static function fotosDir(): string
    {
        $configured = config('appcheckin.uploads_fotos_dir');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return public_path('uploads/fotos');
    }

    public static function legacyFotosDir(): string
    {
        $configured = config('appcheckin.uploads_legacy_dir');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return base_path('../api/public/uploads/fotos');
    }

    public static function ensureFotosDir(): void
    {
        $dir = self::fotosDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function caminhoRelativo(string $nomeArquivo): string
    {
        return '/uploads/fotos/'.$nomeArquivo;
    }

    public static function resolveAbsolutePath(string $caminhoRelativo): ?string
    {
        $caminhoRelativo = ltrim($caminhoRelativo, '/');
        if ($caminhoRelativo === '' || str_contains($caminhoRelativo, '..')) {
            return null;
        }

        $candidates = [];

        if (str_starts_with($caminhoRelativo, 'uploads/fotos/')) {
            $filename = substr($caminhoRelativo, strlen('uploads/fotos/'));
            if ($filename !== '' && ! str_contains($filename, '/')) {
                $candidates[] = self::fotosDir().'/'.$filename;
                $candidates[] = self::legacyFotosDir().'/'.$filename;
            }
        }

        $candidates[] = public_path($caminhoRelativo);

        $legacyFile = self::legacyFotosDir();
        if (str_starts_with($caminhoRelativo, 'uploads/fotos/')) {
            $filename = substr($caminhoRelativo, strlen('uploads/fotos/'));
            if ($filename !== '' && ! str_contains($filename, '/')) {
                $legacyPath = $legacyFile.'/'.$filename;
                if (! in_array($legacyPath, $candidates, true)) {
                    $candidates[] = $legacyPath;
                }
            }
        } else {
            $legacyPath = base_path('../api/public/'.$caminhoRelativo);
            if (! in_array($legacyPath, $candidates, true)) {
                $candidates[] = $legacyPath;
            }
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{body: string, mime: string}|null
     */
    public static function readByFilename(string $filename): ?array
    {
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return null;
        }

        $absolute = self::resolveAbsolutePath('uploads/fotos/'.$filename);
        if ($absolute === null) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'application/octet-stream';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $body = file_get_contents($absolute);

        return $body === false ? null : ['body' => $body, 'mime' => $mime];
    }
}
