<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('application_error_logs')) {
            return;
        }

        Schema::create('application_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->index();
            $table->string('description', 500)->index();
            $table->text('message');
            $table->string('level', 20)->default('error')->index();
            $table->string('exception_class', 255)->nullable();
            $table->string('source_file', 500)->nullable();
            $table->unsignedInteger('source_line')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_error_logs');
    }
};
