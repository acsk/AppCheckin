<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termos adicionais dos planos com fidelização, um texto por academia. Todo ciclo com fidelização herda
 * este texto, exceto os que têm termos próprios (plano_ciclos.termos preenchido).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('termos_fidelizacao')) {
            Schema::create('termos_fidelizacao', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id')->unique();
                $t->text('texto')->nullable();
                $t->unsignedSmallInteger('versao')->default(1);
                $t->unsignedInteger('atualizado_por')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('termos_fidelizacao');
    }
};
