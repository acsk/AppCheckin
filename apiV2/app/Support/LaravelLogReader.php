<?php

namespace App\Support;

/**
 * Leitura segura dos arquivos em storage/logs (somente *.log).
 */
final class LaravelLogReader
{
    public const MAX_LINHAS = 1000;

    public const DEFAULT_LINHAS = 200;

    private const TAIL_BYTES = 524288;

    private string $logsPath;

    public function __construct(?string $logsPath = null)
    {
        $this->logsPath = $logsPath ?? storage_path('logs');
    }

    /**
     * @return list<array{nome: string, tamanho_bytes: int, modificado_em: string|null}>
     */
    public function listarArquivos(): array
    {
        if (! is_dir($this->logsPath)) {
            return [];
        }

        $arquivos = [];
        foreach (scandir($this->logsPath, SCANDIR_SORT_DESCENDING) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (! $this->nomeArquivoPermitido($entry)) {
                continue;
            }
            $path = $this->logsPath.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($path)) {
                continue;
            }
            $mtime = filemtime($path);
            $arquivos[] = [
                'nome' => $entry,
                'tamanho_bytes' => (int) filesize($path),
                'modificado_em' => $mtime ? date('c', $mtime) : null,
            ];
        }

        usort($arquivos, fn ($a, $b) => strcmp($b['modificado_em'] ?? '', $a['modificado_em'] ?? ''));

        return $arquivos;
    }

    /**
     * @return array{
     *     arquivo: string,
     *     tamanho_bytes: int,
     *     modificado_em: string|null,
     *     total_linhas_retornadas: int,
     *     linhas: list<array{numero: int|null, texto: string, nivel: string|null, timestamp: string|null}>,
     *     truncado: bool
     * }
     */
    public function lerFinal(
        string $arquivo,
        int $linhas = self::DEFAULT_LINHAS,
        ?string $busca = null,
        ?string $nivel = null
    ): array {
        if (! $this->nomeArquivoPermitido($arquivo)) {
            throw new \InvalidArgumentException('Arquivo de log inválido');
        }

        $path = $this->logsPath.DIRECTORY_SEPARATOR.$arquivo;
        if (! is_file($path)) {
            throw new \RuntimeException('Arquivo de log não encontrado');
        }

        $linhas = max(1, min(self::MAX_LINHAS, $linhas));
        $busca = $this->normalizarFiltro($busca);
        $nivel = $this->normalizarNivel($nivel);

        $conteudo = $this->lerFinalArquivo($path);
        $partes = preg_split("/\r\n|\n|\r/", $conteudo) ?: [];
        $partes = array_values(array_filter($partes, static fn ($l) => $l !== ''));

        if ($busca !== null || $nivel !== null) {
            $partes = array_values(array_filter(
                $partes,
                fn ($linha) => $this->linhaCombinaFiltro($linha, $busca, $nivel)
            ));
        }

        $truncado = count($partes) > $linhas;
        $selecionadas = array_slice($partes, -$linhas);

        $resultado = [];
        foreach ($selecionadas as $texto) {
            $resultado[] = [
                'numero' => null,
                'texto' => $texto,
                'nivel' => $this->detectarNivel($texto),
                'timestamp' => $this->detectarTimestamp($texto),
            ];
        }

        $mtime = filemtime($path);

        return [
            'arquivo' => $arquivo,
            'tamanho_bytes' => (int) filesize($path),
            'modificado_em' => $mtime ? date('c', $mtime) : null,
            'total_linhas_retornadas' => count($resultado),
            'linhas' => $resultado,
            'truncado' => $truncado,
        ];
    }

    private function nomeArquivoPermitido(string $nome): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]+\.log$/', $nome);
    }

    private function lerFinalArquivo(string $path): string
    {
        $tamanho = filesize($path);
        if ($tamanho === false || $tamanho === 0) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo de log');
        }

        try {
            $ler = min($tamanho, self::TAIL_BYTES);
            if ($tamanho > $ler) {
                fseek($handle, -$ler, SEEK_END);
            }
            $conteudo = stream_get_contents($handle);

            return is_string($conteudo) ? $conteudo : '';
        } finally {
            fclose($handle);
        }
    }

    private function normalizarFiltro(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        return mb_substr($valor, 0, 200);
    }

    private function normalizarNivel(?string $nivel): ?string
    {
        if ($nivel === null) {
            return null;
        }
        $nivel = strtolower(trim($nivel));
        $permitidos = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        return in_array($nivel, $permitidos, true) ? $nivel : null;
    }

    private function linhaCombinaFiltro(string $linha, ?string $busca, ?string $nivel): bool
    {
        if ($busca !== null && stripos($linha, $busca) === false) {
            return false;
        }
        if ($nivel !== null && $this->detectarNivel($linha) !== $nivel) {
            return false;
        }

        return true;
    }

    private function detectarNivel(string $linha): ?string
    {
        if (preg_match('/\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/i', $linha, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function detectarTimestamp(string $linha): ?string
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*)\]/', $linha, $m)) {
            return $m[1];
        }

        return null;
    }
}
