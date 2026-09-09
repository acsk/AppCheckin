<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpsErrorLogViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['appcheckin.ops_view_token' => 'test-ops-token']);
    }

    public function test_ops_errors_requires_token(): void
    {
        $this->get('/ops/errors')
            ->assertForbidden();
    }

    public function test_ops_errors_renders_with_valid_token(): void
    {
        $this->get('/ops/errors?token=test-ops-token')
            ->assertOk()
            ->assertSee('Erros da API v2');
    }

    public function test_ops_errors_groups_rows_when_table_exists(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('application_error_logs')) {
            $this->markTestSkipped('Tabela application_error_logs ausente.');
        }

        DB::table('application_error_logs')->insert([
            'fingerprint' => hash('sha256', 'Erro de teste agrupado'),
            'description' => 'Erro de teste agrupado',
            'message' => 'stack trace fake',
            'level' => 'error',
            'created_at' => now(),
        ]);

        $this->get('/ops/errors?token=test-ops-token')
            ->assertOk()
            ->assertSee('Erro de teste agrupado');
    }
}
