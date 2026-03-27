<?php

namespace Tests\Feature\Workflow;

use App\Models\Version;
use Tests\TestCase;

class VersionEditorLoadingTest extends TestCase
{
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
        $response->assertSee(route('version.editor.document', $version), false);
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
        $response->assertSee('Document payload text', false);
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
        $response->assertSee('Inline fallback text', false);
    }
}
