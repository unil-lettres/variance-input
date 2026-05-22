<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Comparison;
use App\Models\User;
use App\Models\Version;
use App\Models\Work;
use App\Models\WorkStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SeedPaginationEditorTestbed extends Command
{
    protected $signature = 'variance:seed-pagination-editor-testbed
        {--reset : Remove existing testbed records/files before recreating them}';

    protected $description = 'Create a local author/work/versions/comparison testbed for editor-driven pagination markers.';

    private const AUTHOR_FOLDER = 'test-pagination-editor';
    private const WORK_FOLDER = 'atelier-pagination-editeur';
    private const WORK_SHORT_TITLE = 'pgedt';

    public function handle(): int
    {
        DB::beginTransaction();
        try {
            if ($this->option('reset')) {
                $this->cleanupExistingTestbed();
            }

            $author = Author::firstOrCreate(
                ['folder' => self::AUTHOR_FOLDER],
                [
                    'name' => 'Auteur test pagination éditeur',
                    'order' => 99,
                    'is_legacy' => false,
                ]
            );

            $work = Work::firstOrCreate(
                ['folder' => self::WORK_FOLDER],
                [
                    'author_id' => $author->id,
                    'title' => 'Atelier de pagination (éditeur)',
                    'short_title' => self::WORK_SHORT_TITLE,
                    'desc' => 'Jeu de test local pour vérifier les marqueurs ajoutés dans l’éditeur de version puis injectés dans les comparaisons.',
                    'is_legacy' => false,
                ]
            );

            if ((int) $work->author_id !== (int) $author->id) {
                $work->author_id = $author->id;
                $work->save();
            }
            if ($work->short_title !== self::WORK_SHORT_TITLE) {
                $work->short_title = self::WORK_SHORT_TITLE;
                $work->save();
            }

            WorkStatus::updateOrCreate(
                ['work_id' => $work->id],
                [
                    'global_status' => 0,
                    'desc_status' => 1,
                    'notice_status' => 0,
                    'image_status' => 0,
                    'comparison_status' => 1,
                ]
            );

            $sourceText = $this->sourceText();
            $targetText = $this->targetText();

            $v1 = $this->upsertVersionWithFiles($work, 'Édition témoin (1898)', '1' . self::WORK_SHORT_TITLE, $sourceText);
            $v2 = $this->upsertVersionWithFiles($work, 'Édition revue (1902)', '2' . self::WORK_SHORT_TITLE, $targetText);

            $comparison = Comparison::firstOrNew(['folder' => $this->comparisonFolder()]);
            $comparison->source_id = $v1->id;
            $comparison->target_id = $v2->id;
            $comparison->number = $comparison->number ?? 1;
            $comparison->prefix_label = $comparison->prefix_label ?? 'test';
            $comparison->lg_pivot = $comparison->lg_pivot ?? 7;
            $comparison->ratio = $comparison->ratio ?? 15;
            $comparison->seuil = $comparison->seuil ?? 50;
            $comparison->case_sensitive = false;
            $comparison->diacri_sensitive = false;
            $comparison->created_by = User::query()->orderBy('id')->value('id');
            $comparison->save();

            $this->writeComparisonScaffold($author->folder, $work->folder, (int) $comparison->id, $sourceText, $targetText);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Testbed creation failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->components->info('Pagination editor testbed ready.');
        $this->line('Author folder: ' . self::AUTHOR_FOLDER);
        $this->line('Work folder: ' . self::WORK_FOLDER);
        $this->line('Versions: 1' . self::WORK_SHORT_TITLE . ', 2' . self::WORK_SHORT_TITLE);
        $this->line('Comparison folder: ' . $this->comparisonFolder());
        $this->newLine();
        $this->line('Suggested workflow in admin:');
        $this->line('1. Open each version editor and add <pb/> + italic fixes manually.');
        $this->line('2. Build pagination from editor PB tags.');
        $this->line('3. Inject pagination into the seeded comparison.');

        return self::SUCCESS;
    }

    private function cleanupExistingTestbed(): void
    {
        $work = Work::query()->where('folder', self::WORK_FOLDER)->first();
        if (!$work) {
            return;
        }

        $work->loadMissing('author', 'versions');
        $author = $work->author;
        $versionIds = $work->versions->pluck('id')->all();

        if (!empty($versionIds)) {
            Comparison::query()
                ->whereIn('source_id', $versionIds)
                ->orWhereIn('target_id', $versionIds)
                ->delete();
        }

        foreach ($work->versions as $version) {
            $this->deleteVersionFiles((string) $version->folder);
            $version->delete();
        }

        WorkStatus::query()->where('work_id', $work->id)->delete();
        $work->delete();

        if ($author && Work::query()->where('author_id', $author->id)->count() === 0) {
            $author->delete();
        }

        $this->deleteWorkUploadsTree();
    }

    private function upsertVersionWithFiles(Work $work, string $name, string $folder, string $text): Version
    {
        $version = Version::firstOrNew([
            'work_id' => $work->id,
            'folder' => $folder,
        ]);
        $version->name = $name;
        $version->is_legacy = false;
        $version->save();

        $this->writeVersionFiles($folder, $name, $text);

        return $version;
    }

    private function writeVersionFiles(string $folder, string $title, string $text): void
    {
        $textPath = "uploads/versions/{$folder}.txt";
        $xmlPath = "uploads/versions/{$folder}.xml";
        $tei = $this->buildTei($folder, $title, $text);

        Storage::disk('public')->put($textPath, $text);
        Storage::disk('public')->put($xmlPath, $tei);

        $this->mirrorToPublic("uploads/versions/{$folder}.txt", $text);
        $this->mirrorToPublic("uploads/versions/{$folder}.xml", $tei);
        $this->mirrorToLegacy("uploads/versions/{$folder}.txt", $text);
        $this->mirrorToLegacy("uploads/versions/{$folder}.xml", $tei);
    }

    private function writeComparisonScaffold(string $authorFolder, string $workFolder, int $comparisonId, string $sourceText, string $targetText): void
    {
        $base = "uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparisonId}";

        $source = $this->buildXhtmlFromText('source', $sourceText);
        $target = $this->buildXhtmlFromText('target', $targetText);
        $d = $this->emptyXhtml('d');
        $i = $this->emptyXhtml('i');
        $r = $this->emptyXhtml('r');
        $s = $this->emptyXhtml('s');

        Storage::disk('public')->put("{$base}/source.xhtml", $source);
        Storage::disk('public')->put("{$base}/target.xhtml", $target);
        Storage::disk('public')->put("{$base}/d.xhtml", $d);
        Storage::disk('public')->put("{$base}/i.xhtml", $i);
        Storage::disk('public')->put("{$base}/r.xhtml", $r);
        Storage::disk('public')->put("{$base}/s.xhtml", $s);

        $this->mirrorToLegacy("{$base}/source.xhtml", $source);
        $this->mirrorToLegacy("{$base}/target.xhtml", $target);
        $this->mirrorToLegacy("{$base}/d.xhtml", $d);
        $this->mirrorToLegacy("{$base}/i.xhtml", $i);
        $this->mirrorToLegacy("{$base}/r.xhtml", $r);
        $this->mirrorToLegacy("{$base}/s.xhtml", $s);
    }

    private function buildTei(string $folder, string $title, string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $escaped = array_map(static fn (string $line) => htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8'), $lines);
        $body = implode("\n          <lb/>\n", $escaped);
        $xmlId = 'v' . preg_replace('/[^A-Za-z0-9]/', '', strtolower($folder));

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<TEI xml:id=\"{$xmlId}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n"
            . "  <teiHeader>\n"
            . "    <fileDesc>\n"
            . "      <titleStmt><title>" . htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</title></titleStmt>\n"
            . "      <publicationStmt><p>Seeded locally for pagination editor tests</p></publicationStmt>\n"
            . "      <sourceDesc><p>Generated by variance:seed-pagination-editor-testbed</p></sourceDesc>\n"
            . "    </fileDesc>\n"
            . "  </teiHeader>\n"
            . "  <text>\n"
            . "    <body>\n"
            . "      <div>\n"
            . "        <p>\n"
            . "          {$body}\n"
            . "        </p>\n"
            . "      </div>\n"
            . "    </body>\n"
            . "  </text>\n"
            . "</TEI>\n";
    }

    private function buildXhtmlFromText(string $role, string $text): string
    {
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];
        $items = [];
        foreach ($paragraphs as $paragraph) {
            $line = htmlspecialchars(trim(preg_replace("/\s+/", ' ', $paragraph) ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
            if ($line === '') {
                continue;
            }
            $items[] = "    <p>{$line}</p>";
        }

        $body = implode("\n", $items);
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
            . "  <head>\n"
            . "    <title>{$role}</title>\n"
            . "  </head>\n"
            . "  <body>\n"
            . "{$body}\n"
            . "  </body>\n"
            . "</html>\n";
    }

    private function emptyXhtml(string $name): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
            . "  <head><title>{$name}</title></head>\n"
            . "  <body></body>\n"
            . "</html>\n";
    }

    private function mirrorToPublic(string $relative, string $contents): void
    {
        $path = public_path($relative);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    private function mirrorToLegacy(string $relative, string $contents): void
    {
        $path = base_path('../variance/' . ltrim($relative, '/'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    private function deleteVersionFiles(string $folder): void
    {
        Storage::disk('public')->delete("uploads/versions/{$folder}.txt");
        Storage::disk('public')->delete("uploads/versions/{$folder}.xml");

        $publicTxt = public_path("uploads/versions/{$folder}.txt");
        $publicXml = public_path("uploads/versions/{$folder}.xml");
        @unlink($publicTxt);
        @unlink($publicXml);

        $legacyTxt = base_path("../variance/uploads/versions/{$folder}.txt");
        $legacyXml = base_path("../variance/uploads/versions/{$folder}.xml");
        @unlink($legacyTxt);
        @unlink($legacyXml);
    }

    private function deleteWorkUploadsTree(): void
    {
        $relative = "uploads/" . self::AUTHOR_FOLDER . "/" . self::WORK_FOLDER;
        Storage::disk('public')->deleteDirectory($relative);

        $legacyPath = base_path('../variance/' . $relative);
        if (is_dir($legacyPath)) {
            File::deleteDirectory($legacyPath);
        }
    }

    private function comparisonFolder(): string
    {
        return 'pgedt-editor-pagination-test';
    }

    private function sourceText(): string
    {
        return <<<TXT
Chapitre I

La neige couvrait encore les toits de la ville, et le matin gardait une lumière pâle.
Clara avançait lentement vers la bibliothèque, un carnet à la main, en répétant une phrase qu'elle n'arrivait pas à fixer.
Elle voulait retrouver la version primitive d'un passage effacé, persuadée que le sens se cachait dans une variante minuscule.

En traversant la place, elle relut la note ajoutée la veille: «ne pas oublier le témoin de 1898».
Ce rappel, d'apparence banale, ouvrait tout un chantier de comparaisons et de doutes.

Chapitre II

Dans la salle de lecture, les pages bruissaient à peine.
Un étudiant corrigeait des italiques oubliés; une chercheuse marquait des débuts de page au crayon.
Clara observa ce geste méthodique et se demanda combien d'erreurs naissaient d'un seul décalage de ligne.

Avant midi, elle établit un plan simple:
première version, repérer les insertions;
seconde version, mesurer les déplacements;
puis injecter les marqueurs pour vérifier la cohérence de l'ensemble.
TXT;
    }

    private function targetText(): string
    {
        return <<<TXT
Chapitre I

La neige avait presque disparu des toits, mais le matin conservait une clarté hésitante.
Clara marchait vers la bibliothèque, carnet ouvert, en reformulant une phrase qui résistait encore.
Elle cherchait la rédaction première d'un passage modifié, convaincue que la nuance se logeait dans une variante infime.

En traversant la place, elle relut la note ajoutée la veille: «penser au témoin de 1898».
Ce rappel, plus net qu'autrefois, lançait un chantier de comparaisons et d'hypothèses.

Chapitre II

Dans la salle de lecture, les pages frémissaient à peine.
Un étudiant rétablissait des italiques absents; une chercheuse traçait des repères de page dans la marge.
Clara observa ce travail patient et mesura combien d'erreurs naissaient d'un simple décalage.

Avant midi, elle fixa une méthode en trois temps:
première version, noter les insertions;
seconde version, suivre les déplacements;
puis injecter les marqueurs afin d'éprouver la cohérence de l'ensemble.
TXT;
    }
}
