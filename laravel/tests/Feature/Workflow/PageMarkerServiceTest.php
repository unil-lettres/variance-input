<?php

namespace Tests\Feature\Workflow;

use App\Models\Author;
use App\Models\Comparison;
use App\Models\Version;
use App\Models\Work;
use App\Services\PageMarkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageMarkerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lignes_matching_ignores_legacy_backslash_emphasis_markers(): void
    {
        $version = $this->createVersionFixture('v1');
        File::put(
            storage_path('app/public/uploads/versions/v1.xml'),
            '<TEI><text><body><p>Et vous idem, Madame, répondit le vieux dragon.</p></body></text></TEI>'
        );

        $lignesPath = storage_path('app/private/lignes/v1_lignes.txt');
        File::put($lignesPath, "030 23 Et vous \\idem\\, Madame, répondit\n");

        $result = app(PageMarkerService::class)->generatePaginationSidecar($version, $lignesPath);

        $this->assertSame([], $result['misses']);
        $this->assertSame(1, $result['payload']['marker_count']);
        $this->assertSame('030', $result['payload']['markers'][0]['image_code']);
        $this->assertSame('23', $result['payload']['markers'][0]['page']);
    }

    public function test_comparison_injection_reanchors_phrases_and_does_not_insert_missing_phrase_at_stale_offset(): void
    {
        $author = Author::factory()->create([
            'folder' => 'honore_de_balzac',
        ]);
        $work = Work::factory()->for($author)->create([
            'folder' => 'melmoth_reconcilie',
        ]);
        $source = Version::factory()->for($work)->create([
            'folder' => '1mr',
        ]);
        $target = Version::factory()->for($work)->create([
            'folder' => '2mr',
        ]);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1mr-2mr-run1',
        ]);

        $comparisonDir = storage_path("app/public/uploads/{$author->folder}/{$work->folder}/comparisons/{$comparison->id}");
        File::ensureDirectoryExists($comparisonDir);
        File::put(
            "{$comparisonDir}/source.xhtml",
            '<a class="span_c">Préface déjà accentuée.</a><a class="span_r">NOTE.<br/>Ce conte, pour nous servir de l’expression à la mode.</a>'
        );

        $imageDir = storage_path("app/public/uploads/{$author->folder}/{$work->folder}/{$source->folder}");
        File::ensureDirectoryExists($imageDir);
        File::put("{$imageDir}/img_1mr_132.jpg", 'fixture');
        File::put("{$imageDir}/img_1mr_133.jpg", 'fixture');

        Storage::disk('local')->put("pagination/{$source->id}.json", json_encode([
            'version_id' => $source->id,
            'version_folder' => $source->folder,
            'work_id' => $work->id,
            'origin' => 'lignes',
            'marker_count' => 2,
            'markers' => [
                [
                    'char_index' => 900,
                    'image' => '132',
                    'image_code' => '132',
                    'page' => '125',
                    'phrase' => 'NOTE. \Ce\ conte, pour nous servir de',
                ],
                [
                    'char_index' => 950,
                    'image' => '133',
                    'image_code' => '133',
                    'page' => '126',
                    'phrase' => 'Texte absent de la comparaison',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = app(PageMarkerService::class)->applySidecarToComparison($comparison, true, true);

        $this->assertSame(1, $result['source']['inserted']);
        $this->assertCount(1, $result['source']['misses']);

        $updated = File::get("{$comparisonDir}/source.xhtml");
        $this->assertStringContainsString('data-image-name="132"', $updated);
        $this->assertStringNotContainsString('data-image-name="133"', $updated);
        $this->assertLessThan(strpos($updated, 'NOTE.'), strpos($updated, 'data-image-name="132"'));
    }

    private function createVersionFixture(string $folder): Version
    {
        $author = Author::factory()->create([
            'folder' => 'fixture_author',
        ]);
        $work = Work::factory()->for($author)->create([
            'folder' => 'fixture_work',
        ]);

        return Version::factory()->for($work)->create([
            'folder' => $folder,
        ]);
    }
}
