<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planos_sistema') || Schema::hasColumn('planos_sistema', 'exibir_site')) {
            return;
        }

        Schema::table('planos_sistema', function (Blueprint $table) {
            $table->boolean('exibir_site')->default(false)->after('atual');
        });

        DB::table('planos_sistema')
            ->where('ativo', 1)
            ->where('atual', 1)
            ->where(fn ($q) => $q->whereNull('codigo')->orWhere('codigo', '!=', 'livre'))
            ->update(['exibir_site' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('planos_sistema') && Schema::hasColumn('planos_sistema', 'exibir_site')) {
            Schema::table('planos_sistema', function (Blueprint $table) {
                $table->dropColumn('exibir_site');
            });
        }
    }
};
