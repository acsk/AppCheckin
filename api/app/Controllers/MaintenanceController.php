<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MaintenanceController
{
    /**
     * Limpeza de Banco de Dados
     * POST /maintenance/cleanup-database
     * Apenas em desenvolvimento e apenas para SuperAdmin
     * 
     * ⚠️ DADOS SERÃO APAGADOS!
     */
    public function cleanupDatabase(Request $request, Response $response): Response
    {
        // ⚠️ Proteger: apenas em desenvolvimento
        if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
            $response->getBody()->write(json_encode([
                'error' => 'Esta operação não é permitida em produção',
                'status' => 'blocked'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Requer SuperAdmin
        $userId = $request->getAttribute('userId');
        if (!$userId) {
            $response->getBody()->write(json_encode([
                'error' => 'Não autenticado',
                'status' => 'unauthorized'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $db = require __DIR__ . '/../../config/database.php';
            
            // Obter papel do usuário
            $stmt = $db->prepare("
                SELECT tup.papel_id 
                FROM tenant_usuario_papel tup 
                WHERE tup.usuario_id = ? AND tup.ativo = 1
                ORDER BY tup.papel_id DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user || $user['papel_id'] != 4) { // papel_id 4 = SuperAdmin
                $response->getBody()->write(json_encode([
                    'error' => 'Apenas SuperAdmin pode executar esta ação',
                    'status' => 'forbidden'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Executar limpeza
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $tables = [
                'sessions',
                'checkins',
                'presenqas',
                'historico_planos',
                'matriculas',
                'contas_receber',
                'pagamentos',
                'planejamento_horarios',
                'planejamento_semanal',
                'horarios',
                'turmas',
                'professores',
                'modalidades',
                'dias',
                'feature_flags',
                'wod_resultados',
                'wod_variacoes',
                'wod_blocos',
                'wods',
                'auxiliar',
            ];
            
            $cleaned = [];
            foreach ($tables as $table) {
                try {
                    $db->exec("DELETE FROM $table");
                    $cleaned[] = $table;
                } catch (\Exception $e) {
                    // Ignorar se tabela não existir
                }
            }
            
            // Limpar dados (manter apenas super_admin = papel_id 4)
            $db->exec("DELETE FROM usuarios WHERE id NOT IN (SELECT usuario_id FROM tenant_usuario_papel WHERE papel_id = 4 AND ativo = 1)");
            $db->exec("DELETE FROM usuario_tenant WHERE usuario_id NOT IN (SELECT usuario_id FROM tenant_usuario_papel WHERE papel_id = 4 AND ativo = 1)");
            $db->exec("DELETE FROM tenant_planos WHERE tenant_id > 1");
            $db->exec("DELETE FROM tenant_formas_pagamento WHERE tenant_id > 1");
            $db->exec("DELETE FROM tenant_planos_sistema WHERE tenant_id > 1");
            $db->exec("DELETE FROM tenants WHERE id > 1");
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Banco de dados limpo com sucesso',
                'tables_cleaned' => count($cleaned),
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'timestamp' => date('Y-m-d H:i:s'),
                'warning' => 'Dados foram permanentemente apagados! Backup recomendado.',
                'maintained' => [
                    'SuperAdmin',
                    'Planos do Sistema',
                    'Formas de Pagamento',
                    'Tenant padrão'
                ]
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao limpar banco: ' . $e->getMessage(),
                'status' => 'error'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
