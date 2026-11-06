<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dependentes: criança cadastrada na conta do responsável (usuarios.responsavel_usuario_id), sem e-mail
 * nem CPF próprios. O responsável troca de perfil no app; o dependente não faz login sozinho.
 * Por isso usuarios.email passa a aceitar NULL (o único índice é o de email_global, que já aceita).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuarios', 'responsavel_usuario_id')) {
            Schema::table('usuarios', function (Blueprint $t) {
                $t->integer('responsavel_usuario_id')->nullable()->index('idx_usuarios_responsavel_usuario');
            });
        }

        Schema::table('usuarios', function (Blueprint $t) {
            $t->string('email', 255)->nullable()->change();
        });

        // Quem já foi cadastrado como menor com o CPF de um responsável que tem conta passa a ser dependente dele
        if (Schema::hasColumn('usuarios', 'responsavel_cpf')) {
            DB::statement(
                'UPDATE usuarios d JOIN usuarios r ON r.cpf = d.responsavel_cpf AND r.id <> d.id
                 SET d.responsavel_usuario_id = r.id
                 WHERE d.responsavel_usuario_id IS NULL AND d.responsavel_cpf IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'responsavel_usuario_id')) {
            Schema::table('usuarios', function (Blueprint $t) {
                $t->dropIndex('idx_usuarios_responsavel_usuario');
                $t->dropColumn('responsavel_usuario_id');
            });
        }
        // email continua aceitando NULL: dependentes sem e-mail já podem existir
    }
};
