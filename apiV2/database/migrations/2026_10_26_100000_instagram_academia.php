<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->string('instagram_url', 2048)->nullable());

        DB::table('tenants')->whereRaw('LOWER(slug) = ?', ['cia-da-natacao'])
            ->update(['instagram_url' => 'https://www.instagram.com/ciadanatacao?utm_source=qr&cplk=bzBkampodHQxdXZ1']);
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('instagram_url'));
    }
};
