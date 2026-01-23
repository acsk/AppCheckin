<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\GdDriver;

/**
 * ImageCompressionService
 * Compressor de imagens usando Intervention/Image library
 */
class ImageCompressionService
{
    private ImageManager $manager;

    public function __construct()
    {
        try {
            $this->manager = new ImageManager(new GdDriver());
        } catch (\Exception $e) {
            error_log("[ImageCompressionService] Erro ao inicializar: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Comprimir imagem mantendo qualidade
     *
     * @param string $imagemOrigem Caminho da imagem original
     * @param string $imagemDestino Caminho onde salvar a imagem comprimida
     * @param int $maxWidth Largura máxima (redimensiona se maior)
     * @param int $maxHeight Altura máxima (redimensiona se maior)
     * @param int $quality Qualidade (0-100, default 80)
     * @return array Informações sobre o resultado
     */
    public function comprimirImagem(
        string $imagemOrigem,
        string $imagemDestino,
        int $maxWidth = 1024,
        int $maxHeight = 1024,
        int $quality = 80
    ): array {
        try {
            // Verificar se arquivo existe
            if (!file_exists($imagemOrigem)) {
                error_log("[ImageCompressionService] Arquivo não encontrado: $imagemOrigem");
                return [
                    'sucesso' => false,
                    'erro' => 'Arquivo não encontrado'
                ];
            }

            // Obter tamanho original
            $tamanhoOriginal = filesize($imagemOrigem);
            error_log("[ImageCompressionService] Iniciando compressão: " . basename($imagemOrigem) . " (" . $this->formatarBytes($tamanhoOriginal) . ")");

            // Ler imagem
            $image = $this->manager->read($imagemOrigem);
            error_log("[ImageCompressionService] Imagem lida: {$image->width()}x{$image->height()}");

            // Redimensionar se necessário (mantendo proporção)
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $novaLargura = min($image->width(), $maxWidth);
                $novaAltura = min($image->height(), $maxHeight);
                error_log("[ImageCompressionService] Redimensionando para: {$novaLargura}x{$novaAltura}");
                
                $image->scale(
                    width: $novaLargura,
                    height: $novaAltura
                );
            }

            // Determinar formato baseado na extensão
            $ext = strtolower(pathinfo($imagemDestino, PATHINFO_EXTENSION));
            $formato = match($ext) {
                'png' => 'png',
                'gif' => 'gif',
                'webp' => 'webp',
                default => 'jpeg'
            };

            error_log("[ImageCompressionService] Salvando como: $formato (qualidade: $quality)");

            // Salvar com compressão
            if ($formato === 'png') {
                $image->save($imagemDestino, compression: 9);
            } elseif ($formato === 'webp') {
                $image->toWebp($quality)->save($imagemDestino);
            } else {
                $image->toJpeg($quality)->save($imagemDestino);
            }

            // Obter tamanho após compressão
            $tamanhoComprimido = filesize($imagemDestino);
            $reducao = round((1 - $tamanhoComprimido / $tamanhoOriginal) * 100, 2);

            error_log("[ImageCompressionService] Sucesso: " . $this->formatarBytes($tamanhoComprimido) . " (redução: {$reducao}%)");

            return [
                'sucesso' => true,
                'tamanho_original' => $tamanhoOriginal,
                'tamanho_comprimido' => $tamanhoComprimido,
                'reducao_percentual' => $reducao,
                'dimensoes' => [
                    'largura' => $image->width(),
                    'altura' => $image->height()
                ]
            ];

        } catch (\Exception $e) {
            error_log("[ImageCompressionService] ERRO: " . $e->getMessage());
            error_log("[ImageCompressionService] Stack: " . $e->getTraceAsString());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Converter imagem para WebP (melhor compressão)
     *
     * @param string $imagemOrigem Caminho da imagem original
     * @param string $imagemDestino Caminho onde salvar como WebP
     * @param int $quality Qualidade (0-100)
     * @return array Informações sobre o resultado
     */
    public function converterParaWebP(
        string $imagemOrigem,
        string $imagemDestino,
        int $quality = 80
    ): array {
        try {
            if (!file_exists($imagemOrigem)) {
                return [
                    'sucesso' => false,
                    'erro' => 'Arquivo não encontrado'
                ];
            }

            $tamanhoOriginal = filesize($imagemOrigem);

            $image = $this->manager->read($imagemOrigem);
            $image->toWebp($quality)->save($imagemDestino);

            $tamanhoWebP = filesize($imagemDestino);
            $reducao = round((1 - $tamanhoWebP / $tamanhoOriginal) * 100, 2);

            return [
                'sucesso' => true,
                'formato_original' => pathinfo($imagemOrigem, PATHINFO_EXTENSION),
                'formato_novo' => 'webp',
                'tamanho_original' => $tamanhoOriginal,
                'tamanho_webp' => $tamanhoWebP,
                'reducao_percentual' => $reducao
            ];

        } catch (\Exception $e) {
            error_log("[ImageCompressionService] Erro ao converter WebP: " . $e->getMessage());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Formatar bytes para unidade legível (KB, MB, GB)
     */
    private function formatarBytes(int $bytes, int $casas = 2): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $valor = $bytes;
        $i = 0;

        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return round($valor, $casas) . ' ' . $unidades[$i];
    }
}
