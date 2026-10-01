<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Para testes que criam schema próprio em SQLite (phpunit.xml usa sqlite :memory:).
 * Em outro driver o teste é pulado em vez de quebrar com erro fatal.
 */
trait RequiresSqlite
{
    protected function requireSqlite(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            $this->markTestSkipped("Requer conexão SQLite (atual: {$driver}).");
        }
    }

    /**
     * Registra funções MySQL usadas pelo código (NOW, CURDATE, CONCAT) no SQLite.
     *
     * @param  array<string, array{0: callable, 1: int}>  $funcoes  nome => [callback, aridade] (-1 = variádica)
     */
    protected function registrarFuncoesSqlite(array $funcoes): void
    {
        foreach ($funcoes as $nome => $definicao) {
            if (! is_string($nome) || $nome === ''
                || ! is_array($definicao) || ! array_is_list($definicao) || count($definicao) !== 2
                || ! is_callable($definicao[0]) || ! is_int($definicao[1])
            ) {
                throw new \InvalidArgumentException(
                    "registrarFuncoesSqlite: '{$nome}' deve ser [callable, int aridade]."
                );
            }
        }

        // Seguro mesmo se o teste não chamou requireSqlite() antes: pula em vez de erro fatal.
        $this->requireSqlite();

        $pdo = DB::connection()->getPdo();

        // Feature-detection: usa createFunction() quando o objeto PDO o oferece (subclasse
        // Pdo\Sqlite do PHP 8.4+, que o Laravel usa aqui); senão, o método legado sqliteCreateFunction().
        // Ver https://www.php.net/manual/pdo-sqlite.createfunction.php
        $metodo = match (true) {
            method_exists($pdo, 'createFunction') => 'createFunction',
            method_exists($pdo, 'sqliteCreateFunction') => 'sqliteCreateFunction',
            default => null,
        };
        if ($metodo === null) {
            $this->markTestSkipped('PDO SQLite sem suporte a funções definidas pelo usuário ('.$pdo::class.').');
        }

        foreach ($funcoes as $nome => [$callback, $args]) {
            $pdo->{$metodo}($nome, $callback, $args);
        }
    }
}
