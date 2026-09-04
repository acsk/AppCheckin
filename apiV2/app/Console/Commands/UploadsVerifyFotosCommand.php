<?php

namespace App\Console\Commands;

use App\Support\FotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UploadsVerifyFotosCommand extends Command
{
    protected $signature = 'uploads:verify-fotos
                            {--copy-missing : Copia arquivos da pasta Slim para o destino ativo quando ausentes}
                            {--dry-run : Apenas simula a cópia}';

    protected $description = 'Verifica fotos de perfil (DB vs disco) e opcionalmente importa da pasta Slim';

    public function handle(): int
    {
        $fotosDir = FotoStorage::fotosDir();
        $legacyDir = FotoStorage::legacyFotosDir();
        $publicUploads = public_path('uploads');
        $publicLink = is_link($publicUploads);

        $this->info('Destino ativo (uploads): '.$fotosDir);
        $this->info('Legado Slim: '.$legacyDir);
        $this->line('public/uploads é symlink: '.($publicLink ? 'sim → '.readlink($publicUploads) : 'não'));

        if (! is_dir($fotosDir)) {
            $this->warn('Pasta de destino não existe. Rode scripts/hostinger-link-uploads.sh ou crie manualmente.');

            return self::FAILURE;
        }

        $legacyFiles = is_dir($legacyDir)
            ? count(array_filter(scandir($legacyDir) ?: [], static fn (string $f): bool => ! in_array($f, ['.', '..'], true)))
            : 0;
        $destFiles = count(array_filter(scandir($fotosDir) ?: [], static fn (string $f): bool => ! in_array($f, ['.', '..'], true)));

        $this->line("Arquivos no legado: {$legacyFiles}");
        $this->line("Arquivos no destino: {$destFiles}");

        $rows = DB::table('alunos')
            ->whereNotNull('foto_caminho')
            ->where('foto_caminho', '!=', '')
            ->get(['id', 'nome', 'foto_caminho']);

        $ok = 0;
        $missing = 0;
        $copied = 0;
        $copyMissing = (bool) $this->option('copy-missing');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($rows as $row) {
            $caminho = (string) $row->foto_caminho;
            $resolved = FotoStorage::resolveAbsolutePath($caminho);

            if ($resolved !== null) {
                $ok++;

                continue;
            }

            $missing++;
            $filename = basename($caminho);
            $legacyFile = $legacyDir.'/'.$filename;

            if ($copyMissing && is_file($legacyFile)) {
                $target = $fotosDir.'/'.$filename;
                if ($dryRun) {
                    $this->line("[dry-run] Copiaria {$legacyFile} → {$target}");
                } else {
                    if (@copy($legacyFile, $target)) {
                        @chmod($target, 0644);
                        $copied++;
                        $this->line("Copiado: {$filename}");
                    } else {
                        $this->error("Falha ao copiar {$filename}");
                    }
                }

                continue;
            }

            $this->warn("Aluno #{$row->id} ({$row->nome}): arquivo ausente — {$caminho}");
        }

        $this->newLine();
        $this->info("Resumo: {$ok} OK, {$missing} ausentes".($copied > 0 ? ", {$copied} copiados" : ''));

        if ($missing > 0 && ! $copyMissing) {
            $this->comment('Dica: use --copy-missing para importar da pasta Slim, ou rode scripts/hostinger-link-uploads.sh');
        }

        return $missing > 0 && ! $copyMissing ? self::FAILURE : self::SUCCESS;
    }
}
