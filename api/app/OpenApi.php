<?php

namespace App;

use OpenApi\Attributes as OA;

/**
 * Configuração principal do OpenAPI/Swagger
 */
#[OA\Info(
    version: "1.0.0",
    title: "App Checkin API",
    description: "API para sistema de check-in de academias e boxes de CrossFit",
    contact: new OA\Contact(
        name: "Suporte App Checkin",
        email: "suporte@appcheckin.com.br"
    )
)]
#[OA\Server(
    url: "http://localhost:8080",
    description: "Servidor de Desenvolvimento"
)]
#[OA\Server(
    url: "https://api.appcheckin.com.br",
    description: "Servidor de Produção"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Token JWT obtido no login"
)]
#[OA\Tag(name: "Auth", description: "Autenticação e registro")]
#[OA\Tag(name: "Dashboard", description: "Estatísticas e cards do painel administrativo")]
#[OA\Tag(name: "Alunos", description: "Gestão de alunos")]
#[OA\Tag(name: "Matrículas", description: "Gestão de matrículas")]
#[OA\Tag(name: "Check-ins", description: "Check-ins em turmas")]
#[OA\Tag(name: "Turmas", description: "Gestão de turmas")]
#[OA\Tag(name: "Planos", description: "Gestão de planos")]
#[OA\Tag(name: "Pagamentos", description: "Gestão de pagamentos")]
#[OA\Tag(name: "Mobile", description: "Endpoints otimizados para o App Mobile")]
class OpenApi
{
}
