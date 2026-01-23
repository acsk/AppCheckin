<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * ImageController - Gerenciamento e otimização de imagens
 * 
 * Endpoints para converter, comprimir e otimizar imagens
 */
class ImageController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }

    /**
     * POST /images/convert-to-webp
     * Converter imagem para WebP (formato mais otimizado)
     * 
     * Requer:
     * - arquivo de imagem em multipart/form-data
     * 
     * Retorna:
     * - Nova imagem em WebP com redução significativa de tamanho
     */
    public function converterParaWebP(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($uploadedFiles['imagem'])) {
                $response->getBody()->write(json_encode([
                    'sucesso' => false,
                    'erro' => 'Nenhuma imagem foi enviada. Use o campo "imagem"'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $uploadedFile = $uploadedFiles['imagem'];
            $mimeType = $uploadedFile->getClientMediaType();
            $permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($mimeType, $permitidos)) {
                $response->getBody()->write(json_encode([
                    'sucesso' => false,
                    'erro' => 'Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WebP'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Criar pasta temporária
            $uploadDir = __DIR__ . '/../../public/uploads/temp';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Salvar arquivo temporário
            $nomeTemp = 'temp_' . time() . '_' . uniqid() . '.tmp';
            $caminhoTemp = $uploadDir . '/' . $nomeTemp;
            $uploadedFile->moveTo($caminhoTemp);

            // Converter para WebP
            $imageCompression = new \App\Services\ImageCompressionService();
            $caminhoWebP = $uploadDir . '/' . pathinfo($nomeTemp, PATHINFO_FILENAME) . '.webp';
            
            $resultado = $imageCompression->converterParaWebP(
                $caminhoTemp,
                $caminhoWebP,
                quality: 80
            );

            // Remover arquivo temporário
            if (file_exists($caminhoTemp)) {
                unlink($caminhoTemp);
            }

            if (!$resultado['sucesso']) {
                // Remover WebP se houver erro
                if (file_exists($caminhoWebP)) {
                    unlink($caminhoWebP);
                }
                throw new \Exception($resultado['erro']);
            }

            // Ler arquivo WebP e retornar
            $conteudo = file_get_contents($caminhoWebP);

            // Remover arquivo após ler
            unlink($caminhoWebP);

            // Retornar imagem WebP
            return $response
                ->withHeader('Content-Type', 'image/webp')
                ->withHeader('Content-Length', strlen($conteudo))
                ->withHeader('X-Compression-Info', json_encode([
                    'reducao_percentual' => $resultado['reducao_percentual'],
                    'tamanho_original' => $resultado['tamanho_original'],
                    'tamanho_webp' => $resultado['tamanho_webp']
                ]))
                ->withStatus(200)
                ->withBody($this->stringToStream($conteudo));

        } catch (\Exception $e) {
            error_log("Erro ao converter para WebP: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /images/stats
     * Retorna estatísticas de compressão do sistema
     */
    public function obterEstatisticas(Request $request, Response $response): Response
    {
        try {
            $uploadDir = __DIR__ . '/../../public/uploads/fotos';

            if (!is_dir($uploadDir)) {
                $response->getBody()->write(json_encode([
                    'total_arquivos' => 0,
                    'tamanho_total' => 0,
                    'tamanho_total_formatado' => '0 B'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            $files = array_filter(scandir($uploadDir), function($f) {
                return $f !== '.' && $f !== '..';
            });

            $tamanhoTotal = 0;
            foreach ($files as $file) {
                $tamanhoTotal += filesize($uploadDir . '/' . $file);
            }

            $response->getBody()->write(json_encode([
                'total_arquivos' => count($files),
                'tamanho_total' => $tamanhoTotal,
                'tamanho_total_formatado' => $this->formatarBytes($tamanhoTotal)
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'erro' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Converter string para stream
     */
    private function stringToStream(string $string)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);
        return $stream;
    }

    /**
     * Formatar bytes para unidade legível
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
