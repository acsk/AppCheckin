<?php

namespace Tests;

use App\Services\PermissaoService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\PermissaoServiceSemBanco;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Testes de rota com usuário mockado não têm tabelas de papéis (ver PermissaoServiceSemBanco)
        $this->app->bind(PermissaoService::class, PermissaoServiceSemBanco::class);
    }
}
