<?php

use App\Repositories\AdminProfessorRepository;
use App\Support\Papel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Professor passa a ser gerido como papel da Equipe (o usuário com papel 2 é o professor).
 * - quem tem o papel Professor e ainda não tem perfil em `professores` ganha um (senão não aparece nas aulas);
 * - um perfil por usuário: UNIQUE em professores.usuario_id, se não houver duplicados (esses ficam para a unificação).
 */
return new class extends Migration
{
    public function up(): void
    {
        $semPerfil = DB::table('tenant_usuario_papel as tup')
            ->where('tup.papel_id', Papel::PROFESSOR)
            ->whereNotExists(fn ($q) => $q->from('professores as p')->whereColumn('p.usuario_id', 'tup.usuario_id'))
            ->distinct()->pluck('tup.usuario_id');

        $professores = app(AdminProfessorRepository::class);
        foreach ($semPerfil as $usuarioId) {
            $professores->garantirPerfil((int) $usuarioId);
        }

        $duplicado = DB::table('professores')->whereNotNull('usuario_id')
            ->groupBy('usuario_id')->havingRaw('COUNT(*) > 1')->exists();
        if (! $duplicado && ! Schema::hasIndex('professores', 'uk_professores_usuario_id')) {
            Schema::table('professores', function (Blueprint $t) {
                $t->unique('usuario_id', 'uk_professores_usuario_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('professores', 'uk_professores_usuario_id')) {
            Schema::table('professores', function (Blueprint $t) {
                $t->dropUnique('uk_professores_usuario_id');
            });
        }
    }
};
