<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthenticate::class,
            'admin.auth' => \App\Http\Middleware\AdminAuthenticate::class,
            'superadmin.auth' => \App\Http\Middleware\SuperAdminAuthenticate::class,
            'prospeccao.auth' => \App\Http\Middleware\ProspeccaoAuthenticate::class,
            'permissao' => \App\Http\Middleware\PermissaoRequerida::class,
            'permissao.rota' => \App\Http\Middleware\PermissaoPorRota::class,
            'ops.token' => \App\Http\Middleware\OpsViewTokenAuth::class,
            'funcionalidade' => \App\Http\Middleware\FuncionalidadeAtiva::class,
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Rotas de pré-matrícula respondem sempre em JSON, mesmo sem o cabeçalho Accept.
        $preMatricula = static fn (Request $request): bool => $request->is(
            'v2/mobile/pre-matricula*',
            'v2/admin/pre-matriculas*',
            'v2/superadmin/pre-matriculas*',
        );
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request, Throwable $e): bool => $preMatricula($request) || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $e, Request $request) use ($preMatricula) {
            if (! $preMatricula($request) || $e instanceof ValidationException) {
                return null;
            }
            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Sessão inválida ou expirada. Entre novamente.'], 401, [], JSON_UNESCAPED_UNICODE);
            }
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $mensagem = $e->getMessage();
                // Mensagens do próprio framework (inglês/técnicas) viram texto legível; as do serviço seguem como estão
                $doFramework = $mensagem === ''
                    || str_starts_with($mensagem, 'The route ')
                    || $mensagem === 'This action is unauthorized.'
                    || $e->getPrevious() instanceof ModelNotFoundException
                    || $e instanceof MethodNotAllowedHttpException
                    || $e instanceof TooManyRequestsHttpException;
                if ($doFramework) {
                    $mensagem = match ($status) {
                        403 => 'Você não tem permissão para esta ação.',
                        404 => 'Recurso não encontrado.',
                        405 => 'Método não permitido.',
                        419 => 'Sessão expirada. Atualize a página e tente novamente.',
                        429 => 'Muitas tentativas. Aguarde um instante e tente novamente.',
                        default => 'Não foi possível concluir a solicitação.',
                    };
                }

                return response()->json(['message' => $mensagem], $status, $e->getHeaders(), JSON_UNESCAPED_UNICODE);
            }

            // O relatório de erros continua ativo; o cliente recebe apenas uma mensagem segura.
            report($e);

            return response()->json([
                'message' => 'Não foi possível processar a pré-matrícula agora. Tente novamente em instantes.',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        });
    })->create();
