<?php

use Illuminate\Database\Migrations\Migration;

class AllowNullAlunoIdAssinaturas extends Migration
{
    public function up()
    {
        $this->db->statement("
            ALTER TABLE assinaturas 
            MODIFY COLUMN aluno_id INT NULL
        ");
        
        echo "✅ Coluna aluno_id em assinaturas agora permite NULL\n";
    }

    public function down()
    {
        // Revert se necessário
        $this->db->statement("
            ALTER TABLE assinaturas 
            MODIFY COLUMN aluno_id INT NOT NULL
        ");
    }
}
