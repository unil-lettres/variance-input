<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Version;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Authenticated HTTP -> editor -> sidecar -> real pagination job -> XHTML.
 * Medite output is a synthetic fixture; this does not run Medite or browser JS.
 */
class PaginationEndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $fixtureDirectories = [];
    private array $fixtureFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureDirectories as $directory) {
            File::deleteDirectory($directory);
        }
        File::delete($this->fixtureFiles);

        parent::tearDown();
    }

    public static function headingCases(): array
    {
        return [
            'goloane-shaped heading and double break' => ['Chant du départ', '346', '<br/><br/>'],
            'absence-shaped parenthesized heading' => ['Chant (au loin)', '339', '<br/><br/>'],
            'accented heading and entity across formatted spans' => ['À l’aube & au soir', '347', '<br />'],
        ];
    }

    #[DataProvider('headingCases')]
    public function test_editor_pb_reaches_both_comparison_roles_without_changing_canonical_text(
        string $heading,
        string $page,
        string $breaks
    ): void {
        [$source, $target, $comparison] = $this->createWorkflowFixture();
        $escapedHeading = htmlspecialchars($heading, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $firstPage = $escapedHeading . "\n\n"
            . 'Écoutez la <emph>lu</emph>mière du 1<sup>er</sup> matin. '
            . str_repeat('La rivière poursuit son chemin. ', 4);
        $secondPage = 'Un autre jour commence auprès de la rivière.';
        $prefix = "Préambule éditorial.\n";
        $canonical = [];
        $xhtml = [];
        $sidecars = [];

        foreach (['source' => $source, 'target' => $target] as $role => $version) {
            $this->writeFacsimilePair($version, '001');
            $this->writeFacsimilePair($version, '002');
            $canonical[$role] = $this->tei($prefix
                . $this->pb($version, '001', $page) . $firstPage . "\n"
                . $this->pb($version, '002', (string) ((int) $page + 1)) . $secondPage);

            $this->saveEditorXml($version, $canonical[$role])
                ->assertOk()
                ->assertJsonPath('text_synced', true)
                ->assertJsonPath('pagination.created', true)
                ->assertJsonPath('pagination.count', 2);

            $sidecars[$role] = $this->readSidecar($version);
            $this->assertSame('pb-tei', $sidecars[$role]['origin']);
            $this->assertSame(2, $sidecars[$role]['marker_count']);
            $this->assertSame(mb_strlen($prefix), $sidecars[$role]['markers'][0]['char_index']);
            $this->assertStringStartsWith(
                $heading . ' Écoutez la lumière du 1er matin.',
                $sidecars[$role]['markers'][0]['phrase']
            );
            $this->assertSame(
                mb_strlen($prefix . html_entity_decode(strip_tags($firstPage), ENT_QUOTES | ENT_XML1, 'UTF-8') . "\n"),
                $sidecars[$role]['markers'][1]['char_index']
            );

            // Explicit rebuild and merge are the same public actions exposed by the UI.
            $this->postJson("/api/versions/{$version->id}/pagination/from-pb")
                ->assertOk()->assertJsonPath('count', 2);
            $this->postJson("/api/versions/{$version->id}/pagination/merge-from-pb")
                ->assertOk()->assertJsonPath('added', 0)->assertJsonPath('total', 2);
            $this->assertSame($sidecars[$role]['markers'], $this->readSidecar($version)['markers']);
            $this->get("/api/versions/{$version->id}/editor-document")
                ->assertOk()->assertContent($canonical[$role]);

            // Medite-shaped XHTML: line separators become tags and TEI inline tags survive as formatting.
            $xhtml[$role . '.xhtml'] = '<div><a class="span_c">'
                . str_replace(["\n\n", "\n", '<emph>', '</emph>'], [$breaks, '<br/>', '<em>', '</em>'],
                    $prefix . $firstPage . "\n" . $secondPage)
                . '</a></div>';
        }

        $directory = $this->writeComparisonArtifacts($comparison, $xhtml);
        $unchangedLists = [];
        foreach (['d', 'i', 'r', 's'] as $component) {
            $unchangedLists[$component] = File::get("{$directory}/{$component}.xhtml");
        }

        $this->injectThroughHttp($comparison);

        $firstOutput = [];
        foreach (['source' => $source, 'target' => $target] as $role => $version) {
            $output = File::get("{$directory}/{$role}.xhtml");
            $firstOutput[$role] = $output;
            $this->assertMarkerImmediatelyBefore($output, '001', $heading, $page);
            $this->assertMarkerImmediatelyBefore($output, '002', $secondPage, (string) ((int) $page + 1));
            $this->assertStringContainsString('<em>lu</em>mière du 1<sup>er</sup> matin.', $output);
            $this->assertStringContainsString($role === 'source' ? 'page_right.svg' : 'page_left.svg', $output);
            $this->assertSame($canonical[$role], File::get($version->getXMLFilePath()));
            $this->assertSame($sidecars[$role]['markers'], $this->readSidecar($version)['markers']);

            $manifestPath = $this->manifestPath($comparison, $version, $role);
            $this->assertFileExists($manifestPath);
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(2, $manifest);
            $this->assertStringEndsWith("img_{$version->folder}_001.jpg", $manifest[0]['big']);
            $this->assertStringEndsWith("img_{$version->folder}_002.jpg", $manifest[1]['big']);
        }

        $this->getJson("/api/comparisons/{$comparison->id}/page-markers/progress")
            ->assertOk()
            ->assertJsonPath('roles.source.inserted', 2)->assertJsonPath('roles.source.missed', 0)
            ->assertJsonPath('roles.target.inserted', 2)->assertJsonPath('roles.target.missed', 0);

        $this->injectThroughHttp($comparison);
        foreach (['source' => $source, 'target' => $target] as $role => $version) {
            $repeated = File::get("{$directory}/{$role}.xhtml");
            $this->assertMarkerImmediatelyBefore($repeated, '001', $heading, $page);
            $this->assertMarkerImmediatelyBefore($repeated, '002', $secondPage, (string) ((int) $page + 1));
            $this->assertStringContainsString($role === 'source' ? 'page_right.svg' : 'page_left.svg', $repeated);
            $this->assertSame($this->normalizedXhtmlText($firstOutput[$role]), $this->normalizedXhtmlText($repeated));
            $this->assertSame($canonical[$role], File::get($version->getXMLFilePath()));
        }
        foreach ($unchangedLists as $component => $original) {
            $this->assertSame($original, File::get("{$directory}/{$component}.xhtml"));
        }
    }

    public function test_editor_changes_refresh_offsets_and_remove_deleted_pb_before_reinjection(): void
    {
        [$source, , $comparison] = $this->createWorkflowFixture();
        $this->writeFacsimilePair($source, '001');
        $this->writeFacsimilePair($source, '002');
        $first = str_repeat('Texte initial de la première page. ', 4);
        $last = 'Dernière page du récit.';
        $this->saveEditorXml($source, $this->tei(
            $this->pb($source, '001', '1') . $first . "\n" . $this->pb($source, '002', '2') . $last
        ))->assertOk()->assertJsonPath('pagination.total', 2);
        $oldSecondOffset = $this->readSidecar($source)['markers'][1]['char_index'];

        $added = 'Édition revue : <emph>texte</emph> au 2<sup>e</sup> jour. ';
        $editedXml = $this->tei($this->pb($source, '001', '1') . $added . $first . "\n"
            . $this->pb($source, '002', '2') . $last);
        $this->saveEditorXml($source, $editedXml)->assertOk()->assertJsonPath('pagination.total', 2);
        $this->assertSame(
            $oldSecondOffset + mb_strlen(strip_tags($added)),
            $this->readSidecar($source)['markers'][1]['char_index']
        );

        $deletedXml = str_replace($this->pb($source, '001', '1'), '', $editedXml);
        $this->saveEditorXml($source, $deletedXml)
            ->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('pagination.removed', 1);
        $this->postJson("/api/versions/{$source->id}/pagination/merge-from-pb")
            ->assertOk()->assertJsonPath('added', 0)->assertJsonPath('total', 1);
        $this->assertSame('2', $this->readSidecar($source)['markers'][0]['page']);

        $directory = $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<div>' . str_replace(['<emph>', '</emph>'], ['<em>', '</em>'], $added)
                . $first . '<br/><br/>' . $last . '</div>',
        ]);
        $this->injectThroughHttp($comparison, 'source');
        $output = File::get("{$directory}/source.xhtml");
        $this->assertStringNotContainsString('data-image-name="001"', $output);
        $this->assertMarkerImmediatelyBefore($output, '002', $last, '2');
        $this->assertSame($deletedXml, File::get($source->getXMLFilePath()));
        $this->getJson("/api/versions/{$source->id}/reader/page?index=1")
            ->assertOk()->assertJsonPath('page.text', $last);
    }

    public function test_comparison_rejects_missing_anchor_instead_of_using_a_plausible_old_offset(): void
    {
        [$source, , $comparison] = $this->createWorkflowFixture();
        $this->writeFacsimilePair($source, '001');
        $this->writeFacsimilePair($source, '002');
        $first = 'Chant du témoin' . "\n\n" . str_repeat('La forêt écoute la rivière. ', 5);
        $xml = $this->tei($this->pb($source, '001', '1') . $first
            . $this->pb($source, '002', '2') . 'Cette phrase a disparu de la comparaison.');
        $this->saveEditorXml($source, $xml)->assertOk()->assertJsonPath('pagination.count', 2);

        $directory = $this->writeComparisonArtifacts($comparison, [
            'source.xhtml' => '<div>' . str_replace("\n", '<br/>', $first)
                . str_repeat('Un autre passage prend maintenant sa place. ', 5) . '</div>',
        ]);
        $this->injectThroughHttp($comparison, 'source');

        $this->getJson("/api/comparisons/{$comparison->id}/page-markers/progress")
            ->assertOk()
            ->assertJsonPath('roles.source.inserted', 1)
            ->assertJsonPath('roles.source.missed', 1)
            ->assertJsonPath('summary.source.misses.0.reason', 'index_introuvable');
        $output = File::get("{$directory}/source.xhtml");
        $this->assertMarkerImmediatelyBefore($output, '001', 'Chant du témoin', '1');
        $this->assertStringNotContainsString('data-image-name="002"', $output);
        $this->assertSame($xml, File::get($source->getXMLFilePath()));
    }

    private function createWorkflowFixture(): array
    {
        config()->set('queue.default', 'sync');
        $user = $this->signInEditor();
        $folder = 'pagination-e2e-' . Str::uuid();
        $work = $this->createEditableWork($user, ['folder' => $folder], ['folder' => 'fixture']);
        $this->fixtureDirectories[] = storage_path("app/public/uploads/{$folder}");
        $this->fixtureDirectories[] = base_path("../variance/uploads/{$folder}");
        $versions = [];
        foreach (['source', 'target'] as $role) {
            $version = Version::factory()->for($work)->create(['folder' => "{$folder}-{$role}"]);
            $this->fixtureFiles[] = $this->writeVersionXml($version);
            $this->fixtureFiles[] = storage_path("app/public/uploads/versions/{$version->folder}.txt");
            $versions[$role] = $version;
        }

        $comparison = Comparison::factory()->create([
            'source_id' => $versions['source']->id,
            'target_id' => $versions['target']->id,
            'created_by' => $user->id,
            'folder' => 'pagination-e2e-run1',
        ]);

        return [$versions['source'], $versions['target'], $comparison];
    }

    private function tei(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<TEI xmlns="http://www.tei-c.org/ns/1.0"><teiHeader><fileDesc><titleStmt>'
            . '<title>Synthetic pagination fixture</title></titleStmt></fileDesc></teiHeader>'
            . '<text><body><p>' . $body . '</p></body></text></TEI>';
    }

    private function pb(Version $version, string $image, string $page): string
    {
        return '<pb n="' . $page . '" facs="img_' . $version->folder . '_' . $image . '.jpg"/>';
    }

    private function saveEditorXml(Version $version, string $xml): \Illuminate\Testing\TestResponse
    {
        return $this->call('PUT', "/version/{$version->id}/editor", [], [], [], [
            'CONTENT_TYPE' => 'application/xml',
            'HTTP_ACCEPT' => 'application/json',
        ], $xml);
    }

    private function readSidecar(Version $version): array
    {
        return json_decode(Storage::disk('local')->get("pagination/{$version->id}.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    private function injectThroughHttp(Comparison $comparison, ?string $role = null): void
    {
        $payload = ['clear_existing' => true, 'replace_existing' => true];
        if ($role !== null) {
            $payload['role'] = $role;
        }
        $this->postJson("/api/comparisons/{$comparison->id}/page-markers", $payload)
            ->assertStatus(202)->assertJsonPath('status', 'queued');
    }

    private function assertMarkerImmediatelyBefore(string $html, string $code, string $text, string $page): void
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($html));
        $xpath = new DOMXPath($dom);
        $markers = $xpath->query('//*[@data-image-name="' . $code . '"]');
        $this->assertSame(1, $markers->length, "Expected exactly one marker for image {$code}.");
        $this->assertSame($page, $xpath->evaluate('string(./span[@class="page-number"])', $markers->item(0)));
        $next = $markers->item(0)->nextSibling;
        $this->assertNotNull($next);
        $this->assertStringStartsWith($text, $next->textContent);
    }

    private function normalizedXhtmlText(string $html): string
    {
        $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_XML1, 'UTF-8')));
    }

    private function manifestPath(Comparison $comparison, Version $version, string $role): string
    {
        $authorFolder = $version->work->author->folder;
        $workFolder = $version->work->folder;
        $name = strtolower("{$authorFolder}--{$workFolder}--{$comparison->folder}");

        return storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$version->folder}/images_{$role}_{$name}.json");
    }
}
