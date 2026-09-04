<?php

namespace Tests\Feature;

use App\Support\FotoStorage;
use Tests\TestCase;

class V2UploadsRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        $fixture = FotoStorage::fotosDir().'/test_fixture.jpg';
        if (is_file($fixture)) {
            @unlink($fixture);
        }

        parent::tearDown();
    }

    public function test_uploads_foto_returns_404_when_missing(): void
    {
        $this->get('/uploads/fotos/arquivo_inexistente_12345.jpg')
            ->assertNotFound();
    }

    public function test_v2_uploads_foto_returns_404_when_missing(): void
    {
        $this->get('/v2/uploads/fotos/arquivo_inexistente_12345.jpg')
            ->assertNotFound();
    }

    public function test_uploads_foto_serves_existing_image(): void
    {
        FotoStorage::ensureFotosDir();
        $path = FotoStorage::fotosDir().'/test_fixture.jpg';
        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//2wBDAQ//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//AP//wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k='
        ));

        $this->get('/v2/uploads/fotos/test_fixture.jpg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $cacheControl = (string) $this->get('/v2/uploads/fotos/test_fixture.jpg')
            ->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=86400', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
    }

    public function test_uploads_foto_rejects_directory_traversal(): void
    {
        $this->get('/uploads/fotos/..%2F..%2F.env')
            ->assertNotFound();
    }
}
