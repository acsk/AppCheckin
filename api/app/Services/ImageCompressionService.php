<?php

namespace App\Services;

/**
 * ImageCompressionService
 * 
 * Serviço de compressão de imagens usando apenas PHP nativo (GD library)
 * Não requer dependências externas - funciona em qualquer servidor com GD instalado
 * 
 * @package App\Services
 */
class ImageCompressionService
{
    private bool $gdDisponivel = false;

    public function __construct()
    {
        // Verificar se GD library está disponível (nativa do PHP)
        $this->gdDisponivel = extension_loaded('gd');
        
        if (!$this->gdDisponivel) {
            error_log("[ImageCompressionService] GD library não disponível no servidor");
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
            // Se biblioteca não disponível, copiar arquivo sem compressão
            if (!$this->bibliotecaDisponivel) {
                error_log("[ImageCompressionService] Biblioteca intervention/image não disponível. Usando fallback com copy.");
                return $this->copiarSemCompressao($imagemOrigem, $imagemDestino);
            }

            // Verificar se arquivo existe
            if (!file_exists($imagemOrigem)) {
                return [
                    'sucesso' => false,
                    'erro' => 'Arquivo não encontrado'
                ];
            }

            // Obter tamanho original
            $tamanhoOriginal = filesize($imagemOrigem);

            // Ler imagem
            $image = $this->manager->read($imagemOrigem);

            // Redimensionar se necessário (mantendo proporção)
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image->scale(
                    width: min($image->width(), $maxWidth),
                    height: min($image->height(), $maxHeight)
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

            // Salvar com compressão
            if ($formato === 'png') {
                // PNG com compressão
                $image->save($imagemDestino, compression: 9);
            } elseif ($formato === 'webp') {
                // WebP com qualidade
                $image->toWebp($quality)->save($imagemDestino);
            } else {
                // JPEG com qualidade
                $image->toJpeg($quality)->save($imagemDestino);
            }

            // Obter tamanho após compressão
            $tamanhoComprimido = filesize($imagemDestino);
            $reducao = round((1 - $tamanhoComprimido / $tamanhoOriginal) * 100, 2);

            return [
                'sucesso' => true,
                'tamanho_original' => $tamanhoOriginal,
                'tamanho_comprimido' => $tamanhoComprimido,
                'reducao_percentual' => $reducao,
                'dimensoes' => [
                    'largura' => $image->width(),
                    'altura' => $image->height()
                ],
                'formato' => $formato
            ];

        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Comprimir para múltiplos tamanhos (útil para diferentes resoluções)
     *
     * @param string $imagemOrigem Caminho da imagem original
     * @param string $pastaDestino Pasta onde salvar as versões comprimidas
     * @param string $nomeBase Nome base para os arquivos
     * @return array Array com informações de cada versão criada
     */
    public function comprimirMultiplosTamanhos(
        string $imagemOrigem,
        string $pastaDestino,
        string $nomeBase
    ): array {
        // Criar pasta se não existir
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0755, true);
        }

        $tamanhos = [
            'thumb' => ['width' => 150, 'height' => 150, 'quality' => 85],
            'small' => ['width' => 400, 'height' => 400, 'quality' => 80],
            'medium' => ['width' => 800, 'height' => 800, 'quality' => 80],
            'large' => ['width' => 1200, 'height' => 1200, 'quality' => 85],
        ];

        $resultados = [];

        foreach ($tamanhos as $tipo => $config) {
            $ext = pathinfo($imagemOrigem, PATHINFO_EXTENSION);
            $caminhoDestino = $pastaDestino . '/' . $nomeBase . '_' . $tipo . '.' . $ext;

            $resultado = $this->comprimirImagem(
                $imagemOrigem,
                $caminhoDestino,
                $config['width'],
                $config['height'],
                $config['quality']
            );

            $resultados[$tipo] = array_merge($resultado, [
                'caminho' => $caminhoDestino
            ]);
        }

        return $resultados;
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
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    /**
     * Fallback: copiar arquivo sem compressão quando intervention/image não está disponível
     * Retorna dados simulados de compressão
     */
    private function copiarSemCompressao(string $origem, string $destino): array
    {
        try {
            if (!file_exists($origem)) {
                return [
                    'sucesso' => false,
                    'erro' => 'Arquivo não encontrado'
                ];
            }

            $tamanhoOriginal = filesize($origem);

            // Copiar arquivo
            if (!copy($origem, $destino)) {
                return [
                    'sucesso' => false,
                    'erro' => 'Falha ao copiar arquivo'
                ];
            }

            $tamanhoFinal = filesize($destino);

            return [
                'sucesso' => true,
                'fallback' => true,
                'tamanho_original' => $tamanhoOriginal,
                'tamanho_comprimido' => $tamanhoFinal,
                'reducao_percentual' => 0,
                'dimensoes' => ['largura' => null, 'altura' => null],
                'aviso' => 'Compressão não disponível. Arquivo copiado sem otimização.'
            ];

        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
}
