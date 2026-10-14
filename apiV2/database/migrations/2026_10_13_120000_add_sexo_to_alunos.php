<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sexo do aluno: feminino | masculino | outro | prefiro_nao_informar (App\Support\SexoAluno).
 * NULL = ainda não informado (cadastros antigos). Obrigatório no cadastro pelo app (register-mobile).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('alunos', 'sexo')) {
            return;
        }

        Schema::table('alunos', function (Blueprint $table) {
            $table->string('sexo', 20)
                ->nullable()
                ->after('data_nascimento')
                ->comment('feminino | masculino | outro | prefiro_nao_informar; NULL = não informado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('alunos', 'sexo')) {
            return;
        }

        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn('sexo');
        });
    }
};
