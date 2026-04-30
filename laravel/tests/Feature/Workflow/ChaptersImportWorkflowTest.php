<?php

namespace Tests\Feature\Workflow;

use App\Models\Chapter;
use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ChaptersImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_list_chapter_targets_for_selected_work(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Chapitres cibles',
            'short_title' => 'cdcible',
        ]);

        $source = Version::factory()->for($work)->create(['folder' => '1cdcible']);
        $target = Version::factory()->for($work)->create(['folder' => '2cdcible']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cdcible-2cdcible-run1',
            'created_by' => $user->id,
        ]);

        Chapter::create([
            'folder' => $comparison->folder,
            'level' => '1',
            'label_source' => 'Chapitre 1',
            'label_target' => 'Chapitre 1',
            'chapter_parent' => null,
            'start_line_source' => '1a',
            'start_line_target' => '9',
            'id_tome_source' => 0,
            'id_tome_target' => 0,
        ]);

        $response = $this->getJson("/chapters/targets?work_id={$work->id}");

        $response->assertOk()
            ->assertJsonPath('work.id', $work->id)
            ->assertJsonPath('targets.0.id', $comparison->id)
            ->assertJsonPath('targets.0.folder', $comparison->folder)
            ->assertJsonPath('targets.0.chapter_count', 1);
    }

    public function test_editor_can_preview_and_commit_chapters_import(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Chapitres import',
            'short_title' => 'cdimport',
        ]);

        $source = Version::factory()->for($work)->create(['folder' => '1cdimport']);
        $target = Version::factory()->for($work)->create(['folder' => '2cdimport']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cdimport-2cdimport-run1',
            'created_by' => $user->id,
        ]);

        Chapter::create([
            'folder' => $comparison->folder,
            'level' => 'stale',
            'label_source' => 'Ancien',
            'label_target' => 'Ancien',
            'chapter_parent' => null,
            'start_line_source' => '0',
            'start_line_target' => '0',
            'id_tome_source' => 0,
            'id_tome_target' => 0,
        ]);

        $upload = $this->makeLegacyChaptersWorkbook([
            ['level', 'label', 'start_line_source', 'start_line_target'],
            ['1', 'Première partie', '1a', '9'],
            ['1.1', 'I - Arrivée', '1a', '9'],
            ['1.2', 'II - Le voyage', '2a', '16'],
        ]);

        $preview = $this->post('/chapters/import/preview', [
            'comparison_id' => $comparison->id,
            'file' => $upload,
        ], ['Accept' => 'application/json']);

        $preview->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('summary.count', 3)
            ->assertJsonPath('summary.existing_count', 1)
            ->assertJsonPath('rows.0.level', '1')
            ->assertJsonPath('rows.1.parent_level', '1');

        $token = $preview->json('token');
        $this->assertIsString($token);

        $commit = $this->postJson('/chapters/import/commit', [
            'comparison_id' => $comparison->id,
            'token' => $token,
        ]);

        $commit->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('imported_count', 3);

        $chapters = Chapter::query()->forFolder($comparison->folder)->orderBy('id')->get();
        $this->assertCount(3, $chapters);
        $this->assertSame('Première partie', $chapters[0]->label_source);
        $this->assertSame(0, (int) $chapters[0]->chapter_parent);
        $this->assertSame('I - Arrivée', $chapters[1]->label_source);
        $this->assertSame($chapters[0]->id, $chapters[1]->chapter_parent);

        $show = $this->getJson("/chapters/{$comparison->id}");

        $show->assertOk()
            ->assertJsonPath('comparison.id', $comparison->id)
            ->assertJsonPath('comparison.readonly', false)
            ->assertJsonPath('summary.count', 3)
            ->assertJsonPath('rows.0.level', '1')
            ->assertJsonPath('rows.0.label', 'Première partie');
    }

    public function test_legacy_comparison_with_existing_chapters_is_listed_as_read_only_target(): void
    {
        $user = $this->signInEditor();
        $author = \App\Models\Author::factory()->create([
            'name' => 'Auteur legacy',
            'is_legacy' => true,
        ]);
        $this->grantAuthorEditPermission($user, $author);
        $work = \App\Models\Work::factory()->for($author)->create([
            'title' => 'Chapitres legacy visibles',
            'short_title' => 'cdlegacy',
            'is_legacy' => true,
        ]);

        $source = Version::factory()->for($work)->create(['folder' => '1cdlegacy']);
        $target = Version::factory()->for($work)->create(['folder' => '2cdlegacy']);
        $comparison = Comparison::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1cdlegacy-2cdlegacy',
            'is_legacy' => true,
            'created_by' => null,
        ]);

        Chapter::create([
            'folder' => $comparison->folder,
            'level' => '1',
            'label_source' => 'Chapitre legacy',
            'label_target' => 'Chapitre legacy',
            'chapter_parent' => 0,
            'start_line_source' => '1a',
            'start_line_target' => '1a',
            'id_tome_source' => 0,
            'id_tome_target' => 0,
        ]);

        $response = $this->getJson("/chapters/targets?work_id={$work->id}");

        $response->assertOk()
            ->assertJsonPath('targets.0.id', $comparison->id)
            ->assertJsonPath('targets.0.folder', $comparison->folder)
            ->assertJsonPath('targets.0.chapter_count', 1)
            ->assertJsonPath('targets.0.readonly', true);

        $show = $this->getJson("/chapters/{$comparison->id}");

        $show->assertOk()
            ->assertJsonPath('comparison.id', $comparison->id)
            ->assertJsonPath('comparison.readonly', true)
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('rows.0.level', '1')
            ->assertJsonPath('rows.0.label', 'Chapitre legacy');
    }

    private function makeLegacyChaptersWorkbook(array $rows): UploadedFile
    {
        $path = storage_path('app/testing/chapters-' . uniqid('', true) . '.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $instructionsRows = [
            ['Legacy chapters example'],
            ['The legacy importer reads the second worksheet only.'],
        ];

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($instructionsRows));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml($rows));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->close();

        return new UploadedFile(
            $path,
            'chapters.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function worksheetXml(array $rows): string
    {
        $parts = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $ref = $this->columnName($columnIndex + 1) . ($rowIndex + 1);
                $cells[] = sprintf(
                    '<c r="%s" t="inlineStr"><is><t>%s</t></is></c>',
                    $ref,
                    htmlspecialchars((string) $value, ENT_XML1)
                );
            }
            $parts[] = sprintf('<row r="%d">%s</row>', $rowIndex + 1, implode('', $cells));
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $parts) . '</sheetData>'
            . '</worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Instructions" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Chapitres" sheetId="2" r:id="rId2"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function coreXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title>Legacy chapters test</dc:title>'
            . '</cp:coreProperties>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            . '<Application>Microsoft Excel</Application>'
            . '</Properties>';
    }
}
