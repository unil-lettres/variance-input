<?php

namespace Tests\Feature\Workflow;

use App\Models\Version;
use App\Services\PageMarkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VersionEditorCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_editor_xml_is_cached_even_without_sidecar_markers(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'editorcachev1',
        ]);

        $this->writeVersionXml($version, '<p>Cached editor payload</p>');

        $cachePath = storage_path("app/private/cache/version-editor/{$version->id}.json");
        File::delete($cachePath);

        $payload = app(PageMarkerService::class)->buildVersionEditorXml($version);

        $this->assertNotSame('', (string) ($payload['xml'] ?? ''));
        $this->assertFileExists($cachePath);
        $this->assertStringContainsString('"payload"', File::get($cachePath));
    }

    public function test_version_editor_xml_indexes_large_documents_with_pb_tags(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'editorcache-large',
        ]);

        $body = '<p>'
            . str_repeat('Café &amp; Société. ', 50000)
            . '<pb n="1" facs="img_editorcache-large_001.jpg"/>'
            . 'Après le repère.'
            . '</p>';
        $this->writeVersionXml($version, $body);

        $payload = app(PageMarkerService::class)->buildVersionEditorXml($version);

        $this->assertSame(1, $payload['existing_pb']);
        $this->assertSame(0, $payload['injected']);
        $this->assertStringContainsString('img_editorcache-large_001.jpg', $payload['xml']);
    }
}
