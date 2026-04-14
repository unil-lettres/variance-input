<?php

namespace Tests\Feature\Workflow;

use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionEditorLoadingTest extends TestCase
{
    use RefreshDatabase;

    private function normalizeXmlText(string $xml): string
    {
        $normalized = strip_tags($xml);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $normalized ?? '');

        return trim((string) $normalized);
    }

    private function extractInlineEditorXml(string $html): string
    {
        $matches = [];
        $found = preg_match('/xmlContent:\s*"((?:\\\\.|[^"])*)"/s', $html, $matches);
        $this->assertSame(1, $found, 'Inline editor payload is missing xmlContent.');

        $decoded = json_decode('"' . $matches[1] . '"', true, 512, JSON_THROW_ON_ERROR);

        return (string) $decoded;
    }

    public function test_version_editor_page_uses_lazy_document_loading_by_default(): void
    {
        config()->set('variance.version_editor_lazy_load', true);

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'lazyv1',
        ]);

        $this->writeVersionXml($version, '<p>Performance smoke text</p>');

        $response = $this->get(route('version.editor', $version));

        $response->assertOk();
        $response->assertSee('urlDocumentLoad', false);
        $response->assertSee('editor-document', false);
        $response->assertDontSee('Performance smoke text', false);
    }

    public function test_version_editor_document_endpoint_returns_xml_payload(): void
    {
        config()->set('variance.version_editor_lazy_load', true);

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'lazyv2',
        ]);

        $this->writeVersionXml($version, '<p>Document payload text</p>');

        $response = $this->get(route('version.editor.document', $version));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString(
            'Document payload text',
            $this->normalizeXmlText($response->getContent())
        );
    }

    public function test_version_editor_inline_mode_remains_available_as_fallback(): void
    {
        config()->set('variance.version_editor_lazy_load', true);

        $user = $this->signInEditor();
        $work = $this->createEditableWork($user);
        $version = Version::factory()->for($work)->create([
            'folder' => 'lazyv3',
        ]);

        $this->writeVersionXml($version, '<p>Inline fallback text</p>');

        $response = $this->get(route('version.editor', [
            'version' => $version,
            'editor_mode' => 'inline',
        ]));

        $response->assertOk();
        $inlineXml = $this->extractInlineEditorXml($response->getContent());
        $this->assertStringContainsString(
            'Inline fallback text',
            $this->normalizeXmlText($inlineXml)
        );
    }
}
