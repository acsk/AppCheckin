# Solução do Erro: Column not found 'mat.turma_id'

## 🔴 Problema

O Model `Turma.php` está tentando buscar matrículas usando a coluna `turma_id`, mas essa coluna **não existe** na tabela `matriculas`.

---

## ✅ Solução: Executar Migration no Backend

### **Criar arquivo de migration**

Crie um novo arquivo em `database/migrations/` com o nome:

```
2026_01_09_000000_add_turma_id_to_matriculas.php
```

### **Conteúdo da Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTurmaIdToMatriculas extends Migration
{
    public function up()
    {
        Schema::table('matriculas', function (Blueprint $table) {
            // Adicionar coluna turma_id se não existir
            if (!Schema::hasColumn('matriculas', 'turma_id')) {
                $table->unsignedBigInteger('turma_id')->nullable()->after('aluno_id');
                
                // Criar índice para melhor performance
                $table->index('turma_id');
                
                // Criar foreign key
                $table->foreign('turma_id')
                    ->references('id')
                    ->on('turmas')
                    ->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('matriculas', function (Blueprint $table) {
            // Remover foreign key e coluna
            if (Schema::hasColumn('matriculas', 'turma_id')) {
                $table->dropForeign(['turma_id']);
                $table->dropColumn('turma_id');
            }
        });
    }
}
```

---

## 🔧 Executar a Migration

No terminal do backend, execute:

```bash
# Se usar Laravel
php artisan migrate

# Se usar raw PHP/Slim, execute o SQL diretamente:
```

### **SQL Direto (Se não usar Laravel)**

```sql
-- Adicionar coluna turma_id à tabela matriculas
ALTER TABLE matriculas ADD COLUMN turma_id INT UNSIGNED NULL AFTER aluno_id;

-- Criar índice
CREATE INDEX idx_turma_id ON matriculas(turma_id);

-- Criar foreign key
ALTER TABLE matriculas 
ADD CONSTRAINT fk_matriculas_turmas 
FOREIGN KEY (turma_id) 
REFERENCES turmas(id) 
ON DELETE CASCADE;
```

---

## 📋 Estrutura Final Esperada da Tabela `matriculas`

```sql
CREATE TABLE matriculas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  aluno_id INT NOT NULL,
  turma_id INT UNSIGNED NULL,  -- ✅ NOVA COLUNA
  contrato_id INT,
  data_inicio DATE,
  data_fim DATE,
  ativo BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE RESTRICT,
  FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
  FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE SET NULL,
  INDEX idx_tenant(tenant_id),
  INDEX idx_aluno(aluno_id),
  INDEX idx_turma(turma_id),
  INDEX idx_ativo(ativo)
);
```

---

## 🔍 Verificar se a Coluna Existe

Execute no seu banco de dados:

```sql
-- Verificar estrutura da tabela matriculas
DESCRIBE matriculas;

-- Ou
SHOW COLUMNS FROM matriculas;
```

Se `turma_id` não aparecer na lista, execute o SQL acima para adicionar.

---

## ✨ Após a Migration

Depois de executar a migration, você poderá:

1. ✅ Listar turmas sem erro
2. ✅ Criar novas turmas
3. ✅ Inscrever alunos em turmas
4. ✅ Vincular matrículas a turmas específicas

O frontend já está 100% pronto para isso!
