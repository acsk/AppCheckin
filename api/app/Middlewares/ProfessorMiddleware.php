<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware para validar acesso às rotas de controle de presença
 * Verifica o papel do usuário NO TENANT atual (via usuario_tenant.papel_id)
 * 
 * Papéis (tabela papeis):
 * - 1: aluno (não tem acesso)
 * - 2: professor (tem acesso)
 * - 3: admin (tem acesso)
 * 
 * Hierarquia: admin > professor > aluno
 * Professor pode fazer tudo que aluno faz + confirmar presença
 * Admin pode fazer tudo
 */
class ProfessorMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $usuario = $request->getAttribute('usuario');
        $tenantId = $request->getAttribute('tenant_id');

        // Verificar se o usuário está autenticado
        if (!$usuario) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'NOT_AUTHENTICATED',
                'message' => 'Não autenticado'
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        // Super admin (papel_id = 4) tem acesso total em qualquer tenant
        $papelId = (int) ($usuario['papel_id'] ?? 0);
        if ($papelId === 4) {
            $request = $request->withAttribute('papel', [
                'id' => 4,
                'nome' => 'super_admin',
                'nivel' => 200,
                'descricao' => 'Super admin - acesso total'
            ]);
            return $handler->handle($request);
        }

        // Verificar papel do usuário no tenant atual
        $usuarioId = (int) $usuario['id'];
        $papel = $this->getPapelNoTenant($usuarioId, $tenantId);
        
        // papel_id >= 2 significa professor ou admin
        if (!$papel || $papel['id'] < 2) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'ACCESS_DENIED',
                'message' => 'Acesso negado. Apenas professores ou administradores podem acessar este recurso.',
                'papel_necessario' => 'professor ou admin',
                'papel_atual' => $papel['nome'] ?? 'nenhum'
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        // Adicionar papel ao request para uso nos controllers
        $request = $request->withAttribute('papel', $papel);
        
        return $handler->handle($request);
    }
    
    /**
     * Busca os papéis do usuário em um tenant específico
     * Retorna o papel de maior nível (hierarquia)
     */
    private function getPapelNoTenant(int $usuarioId, ?int $tenantId): ?array
    {
        if (!$tenantId) {
            return null;
        }
        
        $db = require __DIR__ . '/../../config/database.php';
        
        // Buscar papel de maior nível que o usuário tem no tenant
        $stmt = $db->prepare("
            SELECT p.id, p.nome, p.nivel, p.descricao
            FROM tenant_usuario_papel tup
            INNER JOIN papeis p ON p.id = tup.papel_id
            WHERE tup.usuario_id = :usuario_id 
            AND tup.tenant_id = :tenant_id
            AND tup.ativo = 1
            ORDER BY p.nivel DESC
            LIMIT 1
        ");
        
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Verifica se usuário tem um papel específico no tenant
     */
    private function temPapel(int $usuarioId, int $tenantId, string $papelNome): bool
    {
        $db = require __DIR__ . '/../../config/database.php';
        
        $stmt = $db->prepare("
            SELECT 1
            FROM tenant_usuario_papel tup
            INNER JOIN papeis p ON p.id = tup.papel_id
            WHERE tup.usuario_id = :usuario_id 
            AND tup.tenant_id = :tenant_id
            AND p.nome = :papel_nome
            AND tup.ativo = 1
            LIMIT 1
        ");
        
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'papel_nome' => $papelNome
        ]);
        
        return $stmt->fetch() !== false;
    }
}
