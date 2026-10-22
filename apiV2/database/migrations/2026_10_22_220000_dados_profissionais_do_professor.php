<?php

use Database\Seeders\VinculoProfessorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro completo do professor.
 * - professores (pessoa, vale em qualquer academia): nascimento, WhatsApp, CREF e validade, contato de emergência;
 * - professor_tenant (por academia): tipo de vínculo, desde quando está na academia e observações internas;
 * - professor_modalidades: modalidades que ele dá (as modalidades já são da academia);
 * - vinculos_professor: tipos de vínculo (CLT, PJ…), com seed.
 * Endereço continua em usuarios (cep, logradouro…), como no aluno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professores', function (Blueprint $t) {
            if (! Schema::hasColumn('professores', 'data_nascimento')) {
                $t->date('data_nascimento')->nullable()->after('cpf');
            }
            if (! Schema::hasColumn('professores', 'whatsapp')) {
                $t->string('whatsapp', 11)->nullable()->after('telefone')->comment('Só dígitos, DDD + 9 dígitos');
            }
            if (! Schema::hasColumn('professores', 'cref')) {
                $t->string('cref', 12)->nullable()->after('whatsapp')->comment('Formato 000000-G/UF');
                $t->date('cref_validade')->nullable()->after('cref');
            }
            if (! Schema::hasColumn('professores', 'emergencia_nome')) {
                $t->string('emergencia_nome', 120)->nullable()->after('cref_validade');
                $t->string('emergencia_telefone', 11)->nullable()->after('emergencia_nome');
                $t->string('emergencia_parentesco', 60)->nullable()->after('emergencia_telefone');
            }
        });

        if (! Schema::hasTable('vinculos_professor')) {
            Schema::create('vinculos_professor', function (Blueprint $t) {
                $t->increments('id');
                $t->string('codigo', 20)->unique();
                $t->string('nome', 60);
                $t->unsignedSmallInteger('ordem')->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }
        (new VinculoProfessorSeeder)->run();

        if (! Schema::hasTable('professor_tenant')) {
            Schema::create('professor_tenant', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->integer('professor_id');
                $t->unsignedInteger('vinculo_id')->nullable();
                $t->date('admitido_em')->nullable()->comment('Na academia desde');
                $t->string('observacoes', 500)->nullable()->comment('Só a equipe vê');
                $t->timestamps();
                $t->unique(['tenant_id', 'professor_id'], 'uk_professor_tenant');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('professor_id')->references('id')->on('professores')->cascadeOnDelete();
                $t->foreign('vinculo_id')->references('id')->on('vinculos_professor')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('professor_modalidades')) {
            Schema::create('professor_modalidades', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('professor_id');
                $t->integer('modalidade_id');
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->unique(['professor_id', 'modalidade_id'], 'uk_professor_modalidade');
                $t->index('modalidade_id');
                $t->foreign('professor_id')->references('id')->on('professores')->cascadeOnDelete();
                $t->foreign('modalidade_id')->references('id')->on('modalidades')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_modalidades');
        Schema::dropIfExists('professor_tenant');
        Schema::dropIfExists('vinculos_professor');
        Schema::table('professores', function (Blueprint $t) {
            $t->dropColumn(['data_nascimento', 'whatsapp', 'cref', 'cref_validade', 'emergencia_nome', 'emergencia_telefone', 'emergencia_parentesco']);
        });
    }
};
