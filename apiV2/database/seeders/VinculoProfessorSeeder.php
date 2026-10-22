<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de vínculo do professor com a academia (vinculos_professor).
 *
 * Idempotente pelo código: rodar de novo atualiza nome/ordem e não duplica.
 * A migration de criação da tabela já roda este seeder.
 *
 * php artisan db:seed --class=VinculoProfessorSeeder
 */
class VinculoProfessorSeeder extends Seeder
{
    /** codigo => nome */
    private const VINCULOS = [
        'clt' => 'CLT',
        'pj' => 'Prestador (PJ)',
        'autonomo' => 'Autônomo',
        'estagio' => 'Estágio',
        'voluntario' => 'Voluntário',
    ];

    public function run(): void
    {
        $agora = now();
        $ordem = 0;
        foreach (self::VINCULOS as $codigo => $nome) {
            $ordem += 10;
            DB::table('vinculos_professor')->updateOrInsert(
                ['codigo' => $codigo],
                ['nome' => $nome, 'ordem' => $ordem, 'updated_at' => $agora],
            );
            DB::table('vinculos_professor')->where('codigo', $codigo)->whereNull('created_at')->update(['created_at' => $agora]);
        }
    }
}
