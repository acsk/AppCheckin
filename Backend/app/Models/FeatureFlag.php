<?php
namespace App\Models;

class FeatureFlag
{
    private \PDO $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getFlag(string $key, ?int $tenantId = null): ?array
    {
        // 1) Escopo tenant (prioridade)
        if ($tenantId !== null) {
            $stmt = $this->db->prepare("SELECT * FROM feature_flags WHERE `key` = :key AND scope = 'tenant' AND tenant_id = :tenant_id LIMIT 1");
            $stmt->execute(['key' => $key, 'tenant_id' => $tenantId]);
            $row = $stmt->fetch();
            if ($row) return $row;
        }

        // 2) Escopo global (fallback)
        $stmt = $this->db->prepare("SELECT * FROM feature_flags WHERE `key` = :key AND scope = 'global' LIMIT 1");
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isEnabled(string $key, ?int $tenantId = null): bool
    {
        $flag = $this->getFlag($key, $tenantId);
        if (!$flag) return false;
        if ($flag['value_bool'] !== null) return (bool)$flag['value_bool'];
        if ($flag['value_text'] !== null) return strtolower(trim($flag['value_text'])) === 'true';
        return false;
    }
}
