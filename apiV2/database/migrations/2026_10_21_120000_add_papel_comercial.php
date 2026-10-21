<?php

use App\Support\Papel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Papel Comercial (id 5): acesso ao módulo Prospecção. Funcional, fora da hierarquia 1–4
 * (ver App\Support\Papel); o nível fica entre professor (50) e admin (100) só para exibição.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('papeis')->where('id', Papel::COMERCIAL)->exists()) {
            return;
        }

        DB::table('papeis')->insert([
            'id' => Papel::COMERCIAL,
            'nome' => 'comercial',
            'descricao' => 'Equipe comercial: acesso ao módulo Prospecção',
            'nivel' => 60,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('tenant_usuario_papel')->where('papel_id', Papel::COMERCIAL)->exists()) {
            return;
        }

        DB::table('papeis')->where('id', Papel::COMERCIAL)->delete();
    }
};
