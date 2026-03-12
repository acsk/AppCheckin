<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Notificacao;

class NotificationController
{
    // Listar notificações do usuário atual (tenant + usuário)
    public function index(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            if (empty($tenantId)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'tenantId ausente no contexto']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $usuarioId = $request->getAttribute('userId', null);
            $db = require __DIR__ . '/../../config/database.php';

            $model = new Notificacao($db);
            $rows = $model->listForUser($tenantId, $usuarioId, 200);

            $payload = ['success' => true, 'data' => $rows, 'total' => count($rows)];
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            error_log('[NotificationController@index] ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao listar notificações']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Listar apenas notificações não lidas
    public function unread(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            if (empty($tenantId)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'tenantId ausente no contexto']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $usuarioId = $request->getAttribute('userId', null);
            $db = require __DIR__ . '/../../config/database.php';

            $model = new Notificacao($db);
            $rows = $model->listUnread($tenantId, $usuarioId, 200);

            $payload = ['success' => true, 'data' => $rows, 'total' => count($rows)];
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            error_log('[NotificationController@unread] ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao listar notificações não lidas']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Buscar notificação por id (apenas do usuário atual)
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
            $tenantId = $request->getAttribute('tenantId');
            if (empty($tenantId)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'tenantId ausente no contexto']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $usuarioId = $request->getAttribute('userId', null);
            $db = require __DIR__ . '/../../config/database.php';

            $model = new Notificacao($db);
            $row = $model->find($id, $tenantId, $usuarioId);

            if (!$row) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Notificação não encontrada']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            error_log('[NotificationController@show] ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao buscar notificação']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Criar notificação (normalmente usada pelo backend/cron/admin)
    public function store(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            if (empty($tenantId)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'tenantId ausente no contexto']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $actorId = $request->getAttribute('userId', null);
            $data = $request->getParsedBody() ?: [];
            $db = require __DIR__ . '/../../config/database.php';

            // Campos esperados: usuario_id (opcional), tipo, titulo, mensagem, dados (json)
            $usuarioId = isset($data['usuario_id']) ? (int)$data['usuario_id'] : $actorId;
            $titulo = $data['titulo'] ?? null;

            if (!$usuarioId || !$titulo) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'usuario_id e titulo são obrigatórios']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            $model = new Notificacao($db);
            $insertId = $model->create([
                'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId,
                'tipo' => $data['tipo'] ?? 'info',
                'titulo' => $titulo,
                'mensagem' => $data['mensagem'] ?? null,
                'dados' => $data['dados'] ?? null
            ]);

            if ($insertId === null) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao criar notificação']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $response->getBody()->write(json_encode(['success' => true, 'id' => $insertId]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Throwable $e) {
            error_log('[NotificationController@store] ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao criar notificação']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Marcar uma notificação como lida
    public function markAsRead(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
            $tenantId = $request->getAttribute('tenantId');
            if (empty($tenantId)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'tenantId ausente no contexto']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $usuarioId = $request->getAttribute('userId', null);
            $db = require __DIR__ . '/../../config/database.php';

            $model = new Notificacao($db);
            $ok = $model->markAsRead($id, $tenantId, $usuarioId);

            if (!$ok) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Notificação não encontrada ou sem permissão']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            error_log('[NotificationController@markAsRead] ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao marcar notificação como lida']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Marcar todas como lidas
    public function markAllRead(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId', 1);
            $usuarioId = $request->getAttribute('userId', null);
            $db = require __DIR__ . '/../../config/database.php';

            $model = new Notificacao($db);
            $updated = $model->markAllRead($tenantId, $usuarioId);

            $response->getBody()->write(json_encode(['success' => true, 'updated' => $updated]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            error_log('[NotificationController@markAllRead] ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Erro ao marcar notificações como lidas']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
