<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('app_versions', 'ranking_checkins_limite')) {
            Schema::table('app_versions', function (Blueprint $table) {
                $table->unsignedSmallInteger('ranking_checkins_limite')->default(15);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_versions', 'ranking_checkins_limite')) {
            Schema::table('app_versions', fn (Blueprint $table) => $table->dropColumn('ranking_checkins_limite'));
        }
    }
};
