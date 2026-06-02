<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyCatalogRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_catalog_uses_shared_media_roots_for_vignettes_and_notices(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [
            'name' => 'TESTS_JG',
            'folder' => 'tests_jg',
            'order' => 1,
        ], [
            'title' => "Page d'amour (TEST)",
            'folder' => 'page_damour_test',
            'short_title' => 'pdat',
            'catalog_group' => 'allographic',
            'image_url' => 'cover-test.jpg',
            'pdf_url' => 'page-damour-notice.pdf',
        ]);

        $source = Version::factory()->for($work)->create([
            'name' => '1 PDAB',
            'folder' => '1pdat',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => '2 PDAB',
            'folder' => '2pdat',
        ]);
        Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1pdat-2pdat-run1',
            'number' => 1,
            'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);

        $pdfPath = base_path('../variance/uploads/pdf/page-damour-notice.pdf');
        File::ensureDirectoryExists(dirname($pdfPath));
        File::put($pdfPath, '%PDF-1.4 test');

        try {
            $html = $this->renderLegacyCatalogSection('allographic', [$work->id]);
        } finally {
            @unlink($pdfPath);
        }

        $this->assertStringContainsString('Page d&#039;amour (TEST)', $html);
        $this->assertStringContainsString('src="/uploads_images/cover-test.jpg"', $html);
        $this->assertStringContainsString('href="/uploads/pdf/page-damour-notice.pdf"', $html);
        $this->assertStringNotContainsString('href="/uploads/pdf/' . $work->id . '.pdf"', $html);
        $this->assertStringNotContainsString('src="/dev/uploads_images/cover-test.jpg"', $html);
    }

    public function test_legacy_catalog_filters_main_and_allographic_sections(): void
    {
        $user = $this->signInEditor();
        $mainWork = $this->createCatalogWorkWithDevComparison($user, 'Main Catalog Work', 'mcw', 'main');
        $allographicWork = $this->createCatalogWorkWithDevComparison($user, 'Allographic Catalog Work', 'acw', 'allographic');

        $workIds = [$mainWork->id, $allographicWork->id];

        $mainHtml = $this->renderLegacyCatalogSection('main', $workIds);
        $allographicHtml = $this->renderLegacyCatalogSection('allographic', $workIds);

        $this->assertStringContainsString('Main Catalog Work', $mainHtml);
        $this->assertStringNotContainsString('Allographic Catalog Work', $mainHtml);
        $this->assertStringContainsString('Allographic Catalog Work', $allographicHtml);
        $this->assertStringNotContainsString('Main Catalog Work', $allographicHtml);
    }

    public function test_non_legacy_work_without_pdf_url_does_not_fall_back_to_orphaned_id_pdf(): void
    {
        $user = $this->signInEditor();
        $work = $this->createCatalogWorkWithDevComparison($user, 'No Notice Work', 'nnw', 'allographic');
        $work->forceFill([
            'pdf_url' => null,
            'is_legacy' => false,
        ])->save();

        $orphanPdfPath = base_path("../variance/uploads/pdf/{$work->id}.pdf");
        File::ensureDirectoryExists(dirname($orphanPdfPath));
        File::put($orphanPdfPath, '%PDF-1.4 orphan');

        try {
            $html = $this->renderLegacyCatalogSection('allographic', [$work->id]);
        } finally {
            @unlink($orphanPdfPath);
        }

        $this->assertStringContainsString('No Notice Work', $html);
        $this->assertStringNotContainsString('href="/uploads/pdf/' . $work->id . '.pdf"', $html);
        $this->assertStringNotContainsString('>Notice</a>', $html);
    }

    public function test_legacy_catalog_preserves_stored_comparison_numbering(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [
            'name' => 'Charles Perrault',
            'folder' => 'charles_perrault',
            'order' => 1,
        ], [
            'title' => 'Histoires ou contes du temps passé',
            'folder' => 'histoires',
            'short_title' => 'hist',
            'catalog_group' => 'main',
            'image_url' => 'perrault.jpg',
            'is_legacy' => true,
        ]);

        $source = Version::factory()->for($work)->create([
            'name' => 'Manuscrit',
            'folder' => '01ms',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Mercure Galant',
            'folder' => '02mg',
        ]);
        Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '01ms-02mg',
            'number' => 0.1,
            'prefix_label' => '« La Belle au bois dormant »,',
            'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);

        $html = $this->renderLegacyCatalogSection('main', [$work->id]);

        $this->assertStringContainsString('0.1. « La Belle au bois dormant », Manuscrit', $html);
        $this->assertStringNotContainsString('>1. « La Belle au bois dormant », Manuscrit', $html);
    }

    public function test_catalog_uses_custom_numbering_for_editable_comparisons(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [
            'name' => 'Auteur test',
            'folder' => 'auteur_test',
            'order' => 1,
        ], [
            'title' => 'Œuvre test',
            'folder' => 'oeuvre_test',
            'short_title' => 'oct',
            'catalog_group' => 'main',
            'image_url' => 'oeuvre.jpg',
            'is_legacy' => false,
        ]);

        $source = Version::factory()->for($work)->create([
            'name' => 'Source',
            'folder' => '1oct',
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Cible',
            'folder' => '2oct',
        ]);
        Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1oct-2oct',
            'number' => 0.2,
            'prefix_label' => 'Fragment',
            'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);

        $html = $this->renderLegacyCatalogSection('main', [$work->id]);

        $this->assertStringContainsString('0.2. Fragment Source', $html);
        $this->assertStringNotContainsString('1. Fragment Source', $html);
    }

    public function test_catalog_order_uses_admin_sort_order_not_public_number(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [
            'name' => 'Auteur ordre',
            'folder' => 'auteur_ordre',
            'order' => 1,
        ], [
            'title' => 'Œuvre ordre',
            'folder' => 'oeuvre_ordre',
            'short_title' => 'ord',
            'catalog_group' => 'main',
            'image_url' => 'ordre.jpg',
        ]);

        $sourceA = Version::factory()->for($work)->create([
            'name' => 'Source A',
            'folder' => '1ord',
        ]);
        $targetA = Version::factory()->for($work)->create([
            'name' => 'Cible A',
            'folder' => '2ord',
        ]);
        $sourceB = Version::factory()->for($work)->create([
            'name' => 'Source B',
            'folder' => '3ord',
        ]);
        $targetB = Version::factory()->for($work)->create([
            'name' => 'Cible B',
            'folder' => '4ord',
        ]);

        Comparison::factory()->create([
            'source_id' => $sourceA->id,
            'target_id' => $targetA->id,
            'folder' => '1ord-2ord',
            'number' => 1,
            'sort_order' => 2,
            'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);
        Comparison::factory()->create([
            'source_id' => $sourceB->id,
            'target_id' => $targetB->id,
            'folder' => '3ord-4ord',
            'number' => 2,
            'sort_order' => 1,
            'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);

        $html = $this->renderLegacyCatalogSection('main', [$work->id]);

        $firstPosition = strpos($html, '2. Source B');
        $secondPosition = strpos($html, '1. Source A');

        $this->assertIsInt($firstPosition);
        $this->assertIsInt($secondPosition);
        $this->assertLessThan($secondPosition, $firstPosition);
    }

    private function createCatalogWorkWithDevComparison($user, string $title, string $shortTitle, string $catalogGroup)
    {
        $work = $this->createEditableWork($user, [
            'name' => 'Author ' . $shortTitle,
            'order' => 1,
        ], [
            'title' => $title,
            'short_title' => $shortTitle,
            'catalog_group' => $catalogGroup,
            'image_url' => "{$shortTitle}.jpg",
        ]);

        $source = Version::factory()->for($work)->create([
            'name' => 'Source ' . $shortTitle,
            'folder' => '1' . $shortTitle,
        ]);
        $target = Version::factory()->for($work)->create([
            'name' => 'Target ' . $shortTitle,
            'folder' => '2' . $shortTitle,
        ]);
        Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => "1{$shortTitle}-2{$shortTitle}-run1",
            'publication_scope' => 'dev',
            'created_by' => $user->id,
        ]);

        return $work;
    }

    private function renderLegacyCatalogSection(string $catalogGroup, array $workIds): string
    {
        $this->defineLegacyCatalogConstants();

        $cnx = DB::connection()->getPdo();
        $catalogSectionTitle = $catalogGroup === 'allographic' ? 'Réécritures allographiques' : 'Catalogue';
        $catalogWorkIds = $workIds;
        $catalogPageParam = 'page';
        $catalogPaginate = false;
        $catalogHideWhenEmpty = false;
        $catalogPerPage = 40;
        $catalogEmptyMessage = 'Aucune comparaison en cours pour le moment.';
        $catalogComparisonQuery = 'SELECT c.id as c_id, c.number as c_number, c.prefix_label as c_prefix_label, c.folder AS c_folder, c.publication_scope AS c_scope, s.name as s_name, t.name AS t_name FROM comparisons c INNER JOIN versions s ON c.source_id = s.id INNER JOIN versions t ON c.target_id = t.id WHERE s.work_id = :id ORDER BY CASE WHEN COALESCE(c.sort_order, c.number) IS NULL THEN 1 ELSE 0 END, COALESCE(c.sort_order, c.number) ASC, c.id ASC';
        $catalogComparisonFilter = static fn (array $comparison, array $element): bool => true;
        $catalogComparisonUrlBuilder = static fn (array $comparison, array $element): string => '/dev/' . $comparison['c_id'];

        ob_start();
        include base_path('../variance/partials/cover/catalog_section.php');

        return (string) ob_get_clean();
    }

    private function defineLegacyCatalogConstants(): void
    {
        if (!defined('DIR_REL')) {
            define('DIR_REL', '/dev');
        }

        if (!defined('ROOT')) {
            define('ROOT', base_path('../variance/dev'));
        }

        if (!defined('UPLOAD_ROOT')) {
            define('UPLOAD_ROOT', base_path('../variance/uploads'));
        }
    }
}
