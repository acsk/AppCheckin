<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login próprio do dependente: e-mail do responsável + escolha do perfil + PIN definido pelo responsável.
 * Após 5 PINs errados o perfil trava por 15 minutos (App\Services\DependenteLoginService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            if (! Schema::hasColumn('usuarios', 'dependente_pin_hash')) {
                $t->string('dependente_pin_hash', 255)->nullable();
            }
            if (! Schema::hasColumn('usuarios', 'dependente_pin_tentativas')) {
                $t->unsignedTinyInteger('dependente_pin_tentativas')->default(0);
            }
            if (! Schema::hasColumn('usuarios', 'dependente_pin_bloqueado_ate')) {
                $t->dateTime('dependente_pin_bloqueado_ate')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            $t->dropColumn(['dependente_pin_hash', 'dependente_pin_tentativas', 'dependente_pin_bloqueado_ate']);
        });
    }
};
