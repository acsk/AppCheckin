<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 da consolidação: `usuarios` passa a ser a fonte dos dados pessoais.
 * Cria só as colunas que existiam apenas em alunos/professores (nascimento, sexo, WhatsApp).
 * Os dados são copiados pelo comando `php artisan pessoas:consolidar` (relatório antes, --aplicar depois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            if (! Schema::hasColumn('usuarios', 'data_nascimento')) {
                $t->date('data_nascimento')->nullable()->after('cpf');
            }
            if (! Schema::hasColumn('usuarios', 'sexo')) {
                $t->string('sexo', 20)->nullable()->after('data_nascimento');
            }
            if (! Schema::hasColumn('usuarios', 'whatsapp')) {
                $t->string('whatsapp', 20)->nullable()->after('telefone')->comment('Só dígitos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            $t->dropColumn(['data_nascimento', 'sexo', 'whatsapp']);
        });
    }
};
