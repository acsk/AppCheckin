<?php

namespace Tests\Unit;

use App\Support\FotoStorage;
use Tests\TestCase;

class FotoStorageTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'appcheckin.uploads_fotos_dir' => '',
            'appcheckin.uploads_legacy_dir' => '',
        ]);

        parent::tearDown();
    }

    public function test_resolve_uses_configured_fotos_dir(): void
    {
        $dir = sys_get_temp_dir().'/fotostorage_test_'.uniqid('', true);
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/aluno_1.jpg', 'fake');

        config(['appcheckin.uploads_fotos_dir' => $dir]);

        $this->assertSame(
            $dir.'/aluno_1.jpg',
            FotoStorage::resolveAbsolutePath('/uploads/fotos/aluno_1.jpg'),
        );

        @unlink($dir.'/aluno_1.jpg');
        @rmdir($dir);
    }

    public function test_read_by_filename_rejects_traversal(): void
    {
        $this->assertNull(FotoStorage::readByFilename('../.env'));
    }
}
