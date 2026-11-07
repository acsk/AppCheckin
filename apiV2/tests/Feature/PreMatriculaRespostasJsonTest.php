<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PreMatriculaRespostasJsonTest extends TestCase
{
    public function test_rota_inexistente_responde_json_legivel_sem_cabecalho_accept(): void
    {
        $resposta = $this->call('GET', '/v2/admin/pre-matriculas/abc');

        $resposta->assertStatus(404)->assertJson(['message' => 'Recurso não encontrado.']);
        $this->assertStringContainsString('application/json', (string) $resposta->headers->get('Content-Type'));
    }

    public function test_metodo_nao_permitido_responde_json_em_portugues(): void
    {
        $resposta = $this->call('PATCH', '/v2/mobile/pre-matricula');

        $resposta->assertStatus(405)->assertJson(['message' => 'Método não permitido.']);
    }

    public function test_erro_inesperado_responde_500_json_generico_sem_vazar_detalhes(): void
    {
        Route::get('/v2/mobile/pre-matricula-erro-teste', fn () => throw new \RuntimeException('detalhe interno secreto'));

        $resposta = $this->call('GET', '/v2/mobile/pre-matricula-erro-teste');

        $resposta->assertStatus(500)->assertJson([
            'message' => 'Não foi possível processar a pré-matrícula agora. Tente novamente em instantes.',
        ]);
        $this->assertStringNotContainsString('detalhe interno secreto', $resposta->getContent());
    }

    public function test_requisicao_sem_token_responde_json(): void
    {
        $resposta = $this->call('GET', '/v2/mobile/pre-matricula');

        $this->assertContains($resposta->getStatusCode(), [401, 403]);
        $this->assertStringContainsString('application/json', (string) $resposta->headers->get('Content-Type'));
    }
}
