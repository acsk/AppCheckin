<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matricula_grade', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
            $t->unsignedBigInteger('turma_fixa_id');
            $t->unsignedTinyInteger('dia_semana');
            $t->timestamps();
            $t->unique(['matricula_id', 'turma_fixa_id', 'dia_semana'], 'matricula_grade_unica');
            $t->index(['tenant_id', 'turma_fixa_id', 'dia_semana']);
        });
        Schema::table('matriculas', fn (Blueprint $t) => $t->boolean('grade_revisao')->default(false));
        Schema::create('aula_reposicoes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('turma_id');
            $t->unsignedInteger('aluno_id');
            $t->unsignedInteger('matricula_id');
            $t->unsignedInteger('criado_por');
            $t->timestamps();
            $t->unique(['tenant_id', 'turma_id', 'aluno_id'], 'aula_reposicao_unica');
        });
        // Preserva a grade inteira dos vínculos antigos, mesmo quando excede o plano.
        DB::table('matriculas as m')->join('turmas_fixas as t', 't.id', '=', 'm.turma_fixa_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')->where('m.tipo_acesso', 'turma_fixa')
            ->whereColumn('m.tenant_id', 't.tenant_id')->orderBy('m.id')
            ->select(['m.id', 'm.tenant_id', 'm.turma_fixa_id', 't.dias_semana',
                Schema::hasColumn('planos', 'checkins_semanais') ? 'p.checkins_semanais' : DB::raw('NULL as checkins_semanais')])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $m) {
                    $dias = json_decode($m->dias_semana, true, 512, JSON_THROW_ON_ERROR);
                    foreach ($dias as $dia) {
                        DB::table('matricula_grade')->insert([
                            'tenant_id' => $m->tenant_id, 'matricula_id' => $m->id,
                            'turma_fixa_id' => $m->turma_fixa_id, 'dia_semana' => $dia,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    DB::table('matriculas')->where('id', $m->id)->update([
                        'grade_revisao' => $m->checkins_semanais === null
                            || ((int) $m->checkins_semanais > 0 && count($dias) > (int) $m->checkins_semanais),
                    ]);
                }
            }, 'm.id', 'id');
        // Pivot antiga e referência singular são mantidas apenas para auditoria/compatibilidade.
    }

    public function down(): void
    {
        throw new RuntimeException('Migração irreversível: preserve grades, reposições e vínculos antigos. Restaure somente por backup validado.');
    }
};
