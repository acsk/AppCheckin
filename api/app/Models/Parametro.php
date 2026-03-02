<?php

namespace App\Models;

use PDO;

/**
 * Model para gerenciar parâmetros do sistema
 * 
 * Permite obter e definir configurações por tenant, com cache e fallback para valores padrão
 */
class Parametro
{
    private PDO $db;
    private static array $cache = [];
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Obtém o valor de um parâmetro para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @param string $codigo Código do parâmetro (ex: 'habilitar_pix', 'modo_cobranca')
     * @param mixed $default Valor default se não encontrar
     * @return mixed Valor do parâmetro (já convertido para o tipo correto)
     */
    public function get(int $tenantId, string $codigo, $default = null)
    {
        $cacheKey = "{$tenantId}:{$codigo}";
        
        // Verificar cache
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        // Buscar valor configurado pelo tenant
        $stmt = $this->db->prepare("
            SELECT pt.valor, p.valor_padrao, p.tipo_valor
            FROM parametros p
            LEFT JOIN parametros_tenant pt ON pt.parametro_id = p.id AND pt.tenant_id = ? AND pt.ativo = 1
            WHERE p.codigo = ? AND p.ativo = 1
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return $default;
        }
        
        // Usar valor do tenant se existir, senão usar padrão
        $valor = $row['valor'] ?? $row['valor_padrao'] ?? $default;
        
        // Converter para o tipo correto
        $valorConvertido = $this->converterTipo($valor, $row['tipo_valor']);
        
        // Cachear
        self::$cache[$cacheKey] = $valorConvertido;
        
        return $valorConvertido;
    }
    
    /**
     * Alias para get() - mais semântico para booleanos
     */
    public function isEnabled(int $tenantId, string $codigo): bool
    {
        return (bool) $this->get($tenantId, $codigo, false);
    }
    
    /**
     * Obtém valor inteiro de um parâmetro
     */
    public function getInt(int $tenantId, string $codigo, int $default = 0): int
    {
        return (int) $this->get($tenantId, $codigo, $default);
    }
    
    /**
     * Define o valor de um parâmetro para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @param string $codigo Código do parâmetro
     * @param mixed $valor Novo valor
     * @param int|null $usuarioId ID do usuário que está alterando
     * @return bool Sucesso
     */
    public function set(int $tenantId, string $codigo, $valor, ?int $usuarioId = null): bool
    {
        // Buscar ID do parâmetro
        $stmt = $this->db->prepare("SELECT id FROM parametros WHERE codigo = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$codigo]);
        $parametroId = $stmt->fetchColumn();
        
        if (!$parametroId) {
            return false;
        }
        
        // Converter valor para string
        $valorStr = is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor;
        
        // Inserir ou atualizar
        $stmt = $this->db->prepare("
            INSERT INTO parametros_tenant (tenant_id, parametro_id, valor, atualizado_por, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por), updated_at = NOW()
        ");
        $result = $stmt->execute([$tenantId, $parametroId, $valorStr, $usuarioId]);
        
        // Limpar cache
        $cacheKey = "{$tenantId}:{$codigo}";
        unset(self::$cache[$cacheKey]);
        
        return $result;
    }
    
    /**
     * Define múltiplos parâmetros de uma vez
     * 
     * @param int $tenantId ID do tenant
     * @param array $parametros Array associativo [codigo => valor, ...]
     * @param int|null $usuarioId ID do usuário que está alterando
     * @return bool Sucesso
     */
    public function setMultiple(int $tenantId, array $parametros, ?int $usuarioId = null): bool
    {
        $this->db->beginTransaction();
        
        try {
            foreach ($parametros as $codigo => $valor) {
                $this->set($tenantId, $codigo, $valor, $usuarioId);
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Obtém todos os parâmetros de uma categoria para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @param string $categoria Código da categoria (ex: 'pagamentos', 'checkin')
     * @return array Lista de parâmetros com valores
     */
    public function getByCategoria(int $tenantId, string $categoria): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.codigo,
                p.nome,
                p.descricao,
                p.tipo_valor,
                p.valor_padrao,
                p.opcoes_select,
                COALESCE(pt.valor, p.valor_padrao) as valor,
                tp.codigo as categoria_codigo,
                tp.nome as categoria_nome
            FROM parametros p
            INNER JOIN tipos_parametro tp ON tp.id = p.tipo_parametro_id
            LEFT JOIN parametros_tenant pt ON pt.parametro_id = p.id AND pt.tenant_id = ? AND pt.ativo = 1
            WHERE tp.codigo = ? AND p.ativo = 1 AND tp.ativo = 1 AND p.visivel_tenant = 1
            ORDER BY p.ordem
        ");
        $stmt->execute([$tenantId, $categoria]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter tipos e decodificar JSON
        foreach ($rows as &$row) {
            $row['valor'] = $this->converterTipo($row['valor'], $row['tipo_valor']);
            if ($row['opcoes_select']) {
                $row['opcoes_select'] = json_decode($row['opcoes_select'], true);
            }
        }
        
        return $rows;
    }
    
    /**
     * Obtém todas as categorias disponíveis
     * 
     * @return array Lista de categorias
     */
    public function getCategorias(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, codigo, nome, descricao, icone, ordem
            FROM tipos_parametro
            WHERE ativo = 1
            ORDER BY ordem
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém todos os parâmetros organizados por categoria
     * 
     * @param int $tenantId ID do tenant
     * @return array Parâmetros agrupados por categoria
     */
    public function getAllGrouped(int $tenantId): array
    {
        $categorias = $this->getCategorias();
        $result = [];
        
        foreach ($categorias as $categoria) {
            $result[$categoria['codigo']] = [
                'categoria' => $categoria,
                'parametros' => $this->getByCategoria($tenantId, $categoria['codigo'])
            ];
        }
        
        return $result;
    }
    
    /**
     * Limpa o cache de parâmetros
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
    
    /**
     * Limpa cache de um tenant específico
     */
    public static function clearCacheTenant(int $tenantId): void
    {
        foreach (self::$cache as $key => $value) {
            if (strpos($key, "{$tenantId}:") === 0) {
                unset(self::$cache[$key]);
            }
        }
    }
    
    /**
     * Converte valor string para o tipo correto
     */
    private function converterTipo($valor, string $tipo)
    {
        if ($valor === null) {
            return null;
        }
        
        switch ($tipo) {
            case 'boolean':
                return in_array(strtolower((string) $valor), ['true', '1', 'yes', 'sim'], true);
            case 'integer':
                return (int) $valor;
            case 'decimal':
                return (float) $valor;
            case 'json':
                return json_decode($valor, true);
            default:
                return $valor;
        }
    }
    
    /**
     * Versão pública do converterTipo para uso externo
     * 
     * @param mixed $valor Valor a converter
     * @param string $tipo Tipo do valor (boolean, integer, decimal, json, string, select)
     * @return mixed Valor convertido
     */
    public function convertValue($valor, string $tipo)
    {
        return $this->converterTipo($valor, $tipo);
    }
}
