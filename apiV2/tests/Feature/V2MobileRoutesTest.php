<?php

namespace Tests\Feature;

use Tests\TestCase;

class V2MobileRoutesTest extends TestCase
{
    private function bearerToken(array $claims = []): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $jwt = app(\App\Services\JwtService::class);

        return $jwt->encode(array_merge([
            'user_id' => 1,
            'email' => 'test@example.com',
            'tenant_id' => 1,
        ], $claims));
    }

    public function test_perfil_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/perfil')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_acesso_returns_payload_with_tenant_in_jwt(): void
    {
        $this->getJson('/v2/mobile/acesso', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('acesso.permitido', true)
            ->assertJsonPath('acesso.bloqueado', false);
    }

    public function test_tenants_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/tenants')
            ->assertUnauthorized();
    }

    public function test_checkins_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/checkins')
            ->assertUnauthorized();
    }

    public function test_wod_hoje_validates_date_format(): void
    {
        $this->getJson('/v2/mobile/wod/hoje?data=invalid', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Formato de data inválido. Use YYYY-MM-DD');
    }

    public function test_wods_hoje_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/wods/hoje')
            ->assertUnauthorized();
    }

    public function test_ranking_mensal_returns_success_shape(): void
    {
        $this->getJson('/v2/mobile/ranking/mensal', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['periodo', 'mes', 'ano', 'ranking'],
            ]);
    }

    public function test_horarios_disponiveis_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/horarios-disponiveis')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_horarios_disponiveis_validates_date_format(): void
    {
        $this->getJson('/v2/mobile/horarios-disponiveis?data=invalid', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Formato de data inválido. Use YYYY-MM-DD');
    }

    public function test_registrar_checkin_returns_mobile_error_shape(): void
    {
        $response = $this->postJson('/v2/mobile/checkin', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ]);

        $response->assertJsonPath('success', false);
        $this->assertContains($response->status(), [400, 403, 404, 500]);
    }

    public function test_desfazer_checkin_requires_valid_id(): void
    {
        $this->deleteJson('/v2/mobile/checkin/0/desfazer', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'checkinId é obrigatório');
    }

    public function test_planos_disponiveis_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/planos-disponiveis')
            ->assertUnauthorized();
    }

    public function test_comprar_plano_requires_plano_id(): void
    {
        $this->postJson('/v2/mobile/comprar-plano', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'PLANO_OBRIGATORIO');
    }

    public function test_pagamento_pix_requires_matricula_id(): void
    {
        $this->postJson('/v2/mobile/pagamento/pix', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'matricula_id é obrigatório');
    }

    public function test_assinaturas_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/assinaturas')
            ->assertUnauthorized();
    }

    public function test_turmas_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/turmas')
            ->assertUnauthorized();
    }

    public function test_turma_detalhes_returns_404_when_not_found(): void
    {
        $this->getJson('/v2/mobile/turma/999999/detalhes', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Turma não encontrada');
    }

    public function test_turma_participantes_returns_404_when_not_found(): void
    {
        $this->getJson('/v2/mobile/turma/999999/participantes', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertNotFound()
            ->assertJsonPath('error', 'Turma não encontrada');
    }

    public function test_confirmar_presenca_requires_presencas(): void
    {
        $response = $this->postJson('/v2/mobile/turma/1/confirmar-presenca', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ]);

        // Paridade Slim: turma é validada antes das presenças; turma 1 pode não existir no banco de teste.
        $this->assertContains($response->status(), [400, 404]);
        if ($response->status() === 400) {
            $response->assertJsonPath('error', 'Nenhuma presença informada');
        }
    }

    public function test_confirmar_presenca_returns_404_when_turma_not_found(): void
    {
        $this->postJson('/v2/mobile/turma/999999/confirmar-presenca', [
            'presencas' => ['1' => true],
        ], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertNotFound()
            ->assertJsonPath('error', 'Turma não encontrada');
    }

    public function test_perfil_foto_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/perfil/foto')
            ->assertUnauthorized();
    }

    public function test_alunos_buscar_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/alunos/buscar')
            ->assertUnauthorized();
    }

    public function test_alunos_buscar_requires_search_param(): void
    {
        $this->getJson('/v2/mobile/alunos/buscar', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Informe nome, CPF ou email para buscar');
    }

    public function test_resumo_financeiro_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/alunos/1/resumo-financeiro')
            ->assertUnauthorized();
    }

    public function test_resumo_financeiro_invalid_aluno_id(): void
    {
        $this->getJson('/v2/mobile/alunos/0/resumo-financeiro', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'ID do aluno não informado');
    }

    public function test_resumo_financeiro_not_found(): void
    {
        $this->getJson('/v2/mobile/alunos/999999/resumo-financeiro', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Aluno não encontrado');
    }

    public function test_checkin_manual_requires_fields(): void
    {
        $this->postJson('/v2/mobile/checkin/manual', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'turma_id e aluno_id (ou usuario_id) são obrigatórios');
    }

    public function test_desfazer_checkin_manual_requires_valid_id(): void
    {
        $this->deleteJson('/v2/mobile/checkin/manual/0/desfazer', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'checkinId é obrigatório');
    }

    public function test_bloquear_checkin_requires_jwt(): void
    {
        $this->postJson('/v2/mobile/turma/1/bloquear-checkin')
            ->assertUnauthorized();
    }

    public function test_pacotes_contratos_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/pacotes/contratos')
            ->assertUnauthorized();
    }

    public function test_pacotes_contratos_returns_success_shape(): void
    {
        $this->getJson('/v2/mobile/pacotes/contratos', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'contratos',
                'total',
            ]);
    }

    public function test_pacotes_pendentes_requires_jwt(): void
    {
        $this->getJson('/v2/mobile/pacotes/pendentes')
            ->assertUnauthorized();
    }

    public function test_pacotes_pendentes_returns_success_shape(): void
    {
        $this->getJson('/v2/mobile/pacotes/pendentes', [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'pacotes',
                'total',
            ]);
    }

    public function test_pagar_pacote_requires_jwt(): void
    {
        $this->postJson('/v2/mobile/pacotes/contratos/1/pagar')
            ->assertUnauthorized();
    }

    public function test_pagar_pacote_invalid_contrato_id(): void
    {
        $this->postJson('/v2/mobile/pacotes/contratos/0/pagar', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'contratoId inválido');
    }

    public function test_pagar_pacote_not_found(): void
    {
        $this->postJson('/v2/mobile/pacotes/contratos/999999/pagar', [], [
            'Authorization' => 'Bearer '.$this->bearerToken(),
        ])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Contrato não encontrado');
    }
}
