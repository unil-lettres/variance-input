<?php

namespace Tests\Feature\Workflow;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_uploads_are_mirrored_to_legacy_public_paths(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => "Page d'amour média",
            'short_title' => 'pdam',
        ]);

        foreach ([
            public_path('uploads_images'),
            public_path('uploads/pdf'),
            base_path('../variance/uploads_images'),
            base_path('../variance/uploads/pdf'),
        ] as $path) {
            File::ensureDirectoryExists($path);
        }

        try {
            $response = $this->post('/api/works/' . $work->id . '/media', [
                'vignette' => UploadedFile::fake()->image('cover.jpg', 16, 16),
                'pdf' => UploadedFile::fake()->create('notice.pdf', 8, 'application/pdf'),
            ]);

            $response->assertOk()
                ->assertJsonPath('success', true);

            $work->refresh();

            $this->assertNotEmpty($work->image_url);
            $this->assertMatchesRegularExpression('/^' . $work->id . '-[A-Za-z0-9]{16}\.pdf$/', $work->pdf_url);

            $publicImage = public_path('uploads_images/' . $work->image_url);
            $legacyImage = base_path('../variance/uploads_images/' . $work->image_url);
            $publicPdf = public_path('uploads/pdf/' . $work->pdf_url);
            $legacyPdf = base_path('../variance/uploads/pdf/' . $work->pdf_url);

            $this->assertFileExists($publicImage);
            $this->assertFileExists($legacyImage);
            $this->assertFileExists($publicPdf);
            $this->assertFileExists($legacyPdf);
            $this->assertSame(filesize($publicImage), filesize($legacyImage));
            $this->assertSame(filesize($publicPdf), filesize($legacyPdf));

            $this->getJson('/works/' . $work->id . '/media')
                ->assertOk()
                ->assertJsonPath('image_url', '/uploads_images/' . $work->image_url)
                ->assertJsonPath('pdf_url', '/uploads/pdf/' . $work->pdf_url);
        } finally {
            $work->refresh();

            if ($work->image_url) {
                @unlink(public_path('uploads_images/' . $work->image_url));
                @unlink(base_path('../variance/uploads_images/' . $work->image_url));
            }

            if ($work->pdf_url) {
                @unlink(public_path('uploads/pdf/' . $work->pdf_url));
                @unlink(base_path('../variance/uploads/pdf/' . $work->pdf_url));
            }
        }
    }
}
