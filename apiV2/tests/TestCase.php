<?php

namespace Tests;

use App\Services\PermissaoService;
use App\Support\Pessoa;
use App\Support\SexoAluno;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\PermissaoServiceSemBanco;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Pessoa::limparCache();
        SexoAluno::resetCache();
        // Testes de rota com usuário mockado não têm tabelas de papéis (ver PermissaoServiceSemBanco)
        $this->app->bind(PermissaoService::class, PermissaoServiceSemBanco::class);
    }
}
