<?php

namespace Tests\Unit;

use App\Support\LaravelLogReader;
use Tests\TestCase;

class LaravelLogReaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/laravel-log-reader-'.uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    public function test_lista_apenas_arquivos_log(): void
    {
        file_put_contents($this->tmpDir.'/laravel.log', "line\n");
        file_put_contents($this->tmpDir.'/ignore.txt', 'x');
        file_put_contents($this->tmpDir.'/../hack.log', "bad\n");

        $reader = new LaravelLogReader($this->tmpDir);
        $arquivos = $reader->listarArquivos();

        $this->assertCount(1, $arquivos);
        $this->assertSame('laravel.log', $arquivos[0]['nome']);
    }

    public function test_ler_final_com_filtro_de_nivel(): void
    {
        $conteudo = implode("\n", [
            '[2026-09-02 10:00:00] local.INFO: ok',
            '[2026-09-02 10:00:01] local.ERROR: falhou',
        ]);
        file_put_contents($this->tmpDir.'/laravel.log', $conteudo);

        $reader = new LaravelLogReader($this->tmpDir);
        $result = $reader->lerFinal('laravel.log', 50, null, 'error');

        $this->assertSame(1, $result['total_linhas_retornadas']);
        $this->assertStringContainsString('falhou', $result['linhas'][0]['texto']);
        $this->assertSame('error', $result['linhas'][0]['nivel']);
    }

    public function test_rejeita_path_traversal(): void
    {
        $reader = new LaravelLogReader($this->tmpDir);

        $this->expectException(\InvalidArgumentException::class);
        $reader->lerFinal('../laravel.log');
    }
}
