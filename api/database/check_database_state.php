<?php
/**
 * Script para verificar estado do banco após limpeza
 * 
 * Uso:
 * php database/check_database_state.php
 * 
 * Exibe:
 * - Total de usuários e roles
 * - Tenants existentes
 * - Planos do sistema
 * - Formas de pagamento
 * - Integridade de dados
 */

require __DIR__ . '/../config/database.php';

class DatabaseStateChecker
{
    private $db;
    private $colors = [
        'reset' => "\033[0m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'bold' => "\033[1m"
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function color($text, $color = 'reset')
    {
        return $this->colors[$color] . $text . $this->colors['reset'];
    }

    public function run()
    {
        echo $this->color("\n╔════════════════════════════════════════════════════╗\n", 'cyan');
        echo $this->color("║  VERIFICAÇÃO DE ESTADO DO BANCO DE DADOS           ║\n", 'cyan');
        echo $this->color("╚════════════════════════════════════════════════════╝\n\n", 'cyan');

        $this->checkTableCounts();
        echo "\n";
        $this->checkUsers();
        echo "\n";
        $this->checkTenants();
        echo "\n";
        $this->checkPlanos();
        echo "\n";
        $this->checkFormasPagamento();
        echo "\n";
        $this->checkIntegrity();
        echo "\n";
        $this->checkSummary();
    }

    private function checkTableCounts()
    {
        echo $this->color("📊 CONTAGEM DE TABELAS\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        $tables = [
            'usuarios' => 'Usuários',
            'tenants' => 'Tenants/Academias',
            'turmas' => 'Turmas',
            'matriculas' => 'Matrículas',
            'checkins' => 'Check-ins',
            'presenqas' => 'Presenças',
            'planos_sistema' => 'Planos do Sistema',
            'forma_pagamento' => 'Formas de Pagamento',
            'tenant_planos_sistema' => 'Associações Plano-Tenant',
            'tenant_formas_pagamento' => 'Associações Forma-Tenant',
        ];

        foreach ($tables as $table => $label) {
            try {
                $result = $this->db->query("SELECT COUNT(*) as count FROM $table");
                $row = $result->fetch(\PDO::FETCH_ASSOC);
                $count = $row['count'];
                
                $status = $count > 0 ? $this->color("✓", 'green') : '○';
                printf("  %s %-35s %5d registros\n", $status, $label, $count);
            } catch (\Exception $e) {
                printf("  ✗ %-35s ERRO\n", $label);
            }
        }
    }

    private function checkUsers()
    {
        echo $this->color("👤 USUÁRIOS\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        // Contar por role
        $roleNames = [
            1 => 'Professor',
            2 => 'Admin',
            3 => 'SuperAdmin',
            4 => 'Cliente/Aluno',
            5 => 'Staff'
        ];

        foreach ($roleNames as $roleId => $roleName) {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM usuarios WHERE role_id = ?");
            $stmt->execute([$roleId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            printf("  • %s: %d\n", $roleName, $row['count']);
        }

        // Listar SuperAdmins
        echo "\n  " . $this->color("SuperAdmins:", 'yellow') . "\n";
        $stmt = $this->db->query("SELECT id, email, nome FROM usuarios WHERE role_id = 3 ORDER BY id");
        $superadmins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($superadmins)) {
            echo "    " . $this->color("⚠️  NENHUM SuperAdmin encontrado!", 'red') . "\n";
            echo "    Execute: php database/create_superadmin.php\n";
        } else {
            foreach ($superadmins as $admin) {
                printf("    ✓ ID:%d | %s | %s\n", $admin['id'], $admin['email'], $admin['nome']);
            }
        }
    }

    private function checkTenants()
    {
        echo $this->color("🏢 TENANTS/ACADEMIAS\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        $stmt = $this->db->query("SELECT id, nome, razao_social, status FROM tenants ORDER BY id");
        $tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($tenants)) {
            echo "  " . $this->color("⚠️  Nenhum tenant encontrado!", 'yellow') . "\n";
        } else {
            foreach ($tenants as $tenant) {
                $statusColor = $tenant['status'] == 'ativo' ? 'green' : 'yellow';
                printf("  • ID:%d | %s | %s | %s\n", 
                    $tenant['id'], 
                    $tenant['nome'],
                    $tenant['razao_social'] ?? 'N/A',
                    $this->color($tenant['status'], $statusColor)
                );
            }
        }

        // Verificar tenant padrão
        echo "\n  " . $this->color("Verificação do Tenant Padrão (id=1):", 'blue') . "\n";
        $stmt = $this->db->prepare("SELECT id FROM tenants WHERE id = 1");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo "    " . $this->color("✓", 'green') . " Tenant id=1 existe\n";
        } else {
            echo "    " . $this->color("✗", 'red') . " Tenant id=1 está faltando!\n";
        }
    }

    private function checkPlanos()
    {
        echo $this->color("📋 PLANOS DO SISTEMA\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        $stmt = $this->db->query("SELECT id, nome, descricao, status FROM planos_sistema ORDER BY id");
        $planos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($planos)) {
            echo "  " . $this->color("⚠️  Nenhum plano encontrado - dados podem estar corrompidos", 'yellow') . "\n";
        } else {
            echo "  Planos disponíveis:\n";
            foreach ($planos as $plano) {
                printf("  • ID:%d | %s | %s\n", $plano['id'], $plano['nome'], $plano['status']);
            }
        }
    }

    private function checkFormasPagamento()
    {
        echo $this->color("💳 FORMAS DE PAGAMENTO\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        $stmt = $this->db->query("SELECT id, nome, status FROM forma_pagamento ORDER BY id");
        $formas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($formas)) {
            echo "  " . $this->color("⚠️  Nenhuma forma de pagamento encontrada", 'yellow') . "\n";
        } else {
            echo "  Formas disponíveis:\n";
            foreach ($formas as $forma) {
                printf("  • ID:%d | %s | %s\n", $forma['id'], $forma['nome'], $forma['status']);
            }
        }
    }

    private function checkIntegrity()
    {
        echo $this->color("🔍 VERIFICAÇÃO DE INTEGRIDADE\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        $checks = [
            [
                'name' => 'SuperAdmin existe',
                'query' => "SELECT COUNT(*) as count FROM usuarios WHERE role_id = 3",
                'minValue' => 1
            ],
            [
                'name' => 'Tenant padrão existe',
                'query' => "SELECT COUNT(*) as count FROM tenants WHERE id = 1",
                'minValue' => 1
            ],
            [
                'name' => 'Planos do sistema existem',
                'query' => "SELECT COUNT(*) as count FROM planos_sistema",
                'minValue' => 1
            ],
            [
                'name' => 'Formas de pagamento existem',
                'query' => "SELECT COUNT(*) as count FROM forma_pagamento",
                'minValue' => 1
            ],
            [
                'name' => 'Associações usuario_tenant válidas',
                'query' => "SELECT COUNT(*) as count FROM usuario_tenant WHERE usuario_id IN (SELECT id FROM usuarios)",
                'minValue' => 1
            ]
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $check) {
            try {
                $result = $this->db->query($check['query']);
                $row = $result->fetch(\PDO::FETCH_ASSOC);
                
                if ($row['count'] >= $check['minValue']) {
                    echo "  " . $this->color("✓", 'green') . " {$check['name']}\n";
                    $passed++;
                } else {
                    echo "  " . $this->color("✗", 'red') . " {$check['name']} (esperado >= {$check['minValue']}, encontrado: {$row['count']})\n";
                    $failed++;
                }
            } catch (\Exception $e) {
                echo "  " . $this->color("✗", 'red') . " {$check['name']} (ERRO)\n";
                $failed++;
            }
        }

        echo "\n  Resultado: $passed/5 verificações passaram\n";
    }

    private function checkSummary()
    {
        echo $this->color("📈 RESUMO DO ESTADO\n", 'bold');
        echo str_repeat("-", 50) . "\n";

        $totalUsers = $this->db->query("SELECT COUNT(*) as count FROM usuarios")->fetch(\PDO::FETCH_ASSOC)['count'];
        $superAdmins = $this->db->query("SELECT COUNT(*) as count FROM usuarios WHERE role_id = 3")->fetch(\PDO::FETCH_ASSOC)['count'];
        $totalCheckins = $this->db->query("SELECT COUNT(*) as count FROM checkins")->fetch(\PDO::FETCH_ASSOC)['count'];
        $totalTurmas = $this->db->query("SELECT COUNT(*) as count FROM turmas")->fetch(\PDO::FETCH_ASSOC)['count'];
        $totalMatriculas = $this->db->query("SELECT COUNT(*) as count FROM matriculas")->fetch(\PDO::FETCH_ASSOC)['count'];

        echo "  Total de usuários:       " . $this->color($totalUsers, 'cyan') . "\n";
        echo "  SuperAdmins:             " . $this->color($superAdmins, $superAdmins > 0 ? 'green' : 'red') . "\n";
        echo "  Check-ins registrados:   " . $this->color($totalCheckins, 'cyan') . "\n";
        echo "  Turmas:                  " . $this->color($totalTurmas, 'cyan') . "\n";
        echo "  Matrículas:              " . $this->color($totalMatriculas, 'cyan') . "\n";

        echo "\n" . str_repeat("=", 50) . "\n";
        if ($totalUsers <= 5 && $superAdmins > 0 && $totalCheckins == 0) {
            echo $this->color("✅ Banco de dados está limpo e pronto para uso!\n", 'green');
        } elseif ($superAdmins == 0) {
            echo $this->color("⚠️  AVISO: Nenhum SuperAdmin encontrado!\n", 'red');
            echo "   Execute: php database/create_superadmin.php\n";
        } else {
            echo $this->color("ℹ️  Banco contém dados - verifique se é esperado\n", 'yellow');
        }
        echo str_repeat("=", 50) . "\n";
    }
}

try {
    $checker = new DatabaseStateChecker($db);
    $checker->run();
} catch (\Exception $e) {
    echo $this->color("❌ Erro: " . $e->getMessage() . "\n", 'red');
    exit(1);
}
