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

class SeedPaginationMarkerPlacementTestbed extends Command
{
    protected $signature = 'variance:seed-marker-placement-testbed
        {--reset : Remove existing testbed records/files before recreating them}';

    protected $description = 'Create a dedicated 5-page local dataset to validate exact pagination marker placement around chapter headings.';

    private const AUTHOR_FOLDER = 'test-pagination-placement';
    private const WORK_FOLDER = 'atelier-position-pagination';
    private const WORK_SHORT_TITLE = 'pgpos';
    private const COMPARISON_FOLDER = 'pgpos-marker-placement-test';
    private const PAGE_COUNT = 5;

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
                    'name' => 'Auteur test position pagination',
                    'order' => 98,
                    'is_legacy' => false,
                ]
            );

            $work = Work::firstOrCreate(
                ['folder' => self::WORK_FOLDER],
                [
                    'author_id' => $author->id,
                    'title' => 'Atelier de positionnement de pagination',
                    'short_title' => self::WORK_SHORT_TITLE,
                    'desc' => 'Jeu de test local (5 pages) pour vérifier que les marqueurs placés avant les titres de chapitre restent ancrés au bon endroit.',
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
                    'image_status' => 1,
                    'comparison_status' => 1,
                ]
            );

            $sourceText = $this->sourceText();
            $targetText = $this->targetText();

            $v1 = $this->upsertVersionWithFiles($work, 'Edition repere A (1898)', '1' . self::WORK_SHORT_TITLE, $sourceText);
            $v2 = $this->upsertVersionWithFiles($work, 'Edition repere B (1902)', '2' . self::WORK_SHORT_TITLE, $targetText);

            $this->writeFacsimiles($author->folder, $work->folder, (string) $v1->folder, self::PAGE_COUNT, false);
            $this->writeFacsimiles($author->folder, $work->folder, (string) $v2->folder, self::PAGE_COUNT, true);

            $comparison = Comparison::firstOrNew(['folder' => self::COMPARISON_FOLDER]);
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

        $this->components->info('Marker placement testbed ready (5 pages per version).');
        $this->line('Author folder: ' . self::AUTHOR_FOLDER);
        $this->line('Work folder: ' . self::WORK_FOLDER);
        $this->line('Versions: 1' . self::WORK_SHORT_TITLE . ', 2' . self::WORK_SHORT_TITLE);
        $this->line('Comparison folder: ' . self::COMPARISON_FOLDER);
        $this->line('Facsimiles: ' . self::PAGE_COUNT . ' pages + thumbnails per version');
        $this->newLine();
        $this->line('Suggested workflow in admin:');
        $this->line('1. Open each version editor and italicize each "Chapitre ..." title.');
        $this->line('2. Insert one <pb/> immediately before each chapter title (5 markers).');
        $this->line('3. In Versions, click "Importer depuis l\'editeur" for both versions.');
        $this->line('4. In Comparaisons, inject pagination for source and cible.');
        $this->line('5. Verify in XHTML that each page marker remains before the chapter title line.');

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

        $this->mirrorToPublic($textPath, $text);
        $this->mirrorToPublic($xmlPath, $tei);
        $this->mirrorToLegacy($textPath, $text);
        $this->mirrorToLegacy($xmlPath, $tei);
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

    private function writeFacsimiles(string $authorFolder, string $workFolder, string $versionFolder, int $pages, bool $isTarget): void
    {
        $baseRel = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        Storage::disk('public')->makeDirectory($baseRel);

        for ($i = 1; $i <= $pages; $i++) {
            $index = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $file = "img_{$versionFolder}_{$index}.png";
            $thumb = "img_{$versionFolder}_{$index}_thumb.png";

            $mainColor = $this->palette($i, $isTarget);
            $thumbColor = $this->palette($i + 2, $isTarget);

            $mainBytes = $this->buildSolidPng(900, 1300, $mainColor[0], $mainColor[1], $mainColor[2]);
            $thumbBytes = $this->buildSolidPng(240, 320, $thumbColor[0], $thumbColor[1], $thumbColor[2]);

            Storage::disk('public')->put("{$baseRel}/{$file}", $mainBytes);
            Storage::disk('public')->put("{$baseRel}/{$thumb}", $thumbBytes);

            $this->mirrorToLegacy("{$baseRel}/{$file}", $mainBytes);
            $this->mirrorToLegacy("{$baseRel}/{$thumb}", $thumbBytes);
        }
    }

    /**
     * Generate a minimal RGB PNG without requiring GD.
     */
    private function buildSolidPng(int $width, int $height, int $r, int $g, int $b): string
    {
        $width = max(1, $width);
        $height = max(1, $height);

        $pixel = chr(max(0, min(255, $r)))
            . chr(max(0, min(255, $g)))
            . chr(max(0, min(255, $b)));
        $scanline = chr(0) . str_repeat($pixel, $width);
        $raw = str_repeat($scanline, $height);
        $compressed = gzcompress($raw, 9);

        $png = "\x89PNG\r\n\x1a\n";
        $png .= $this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0));
        $png .= $this->pngChunk('IDAT', $compressed);
        $png .= $this->pngChunk('IEND', '');

        return $png;
    }

    private function pngChunk(string $type, string $data): string
    {
        $chunk = $type . $data;
        $crc = crc32($chunk);
        if ($crc < 0) {
            $crc += 4294967296;
        }

        return pack('N', strlen($data))
            . $type
            . $data
            . pack('N', $crc);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function palette(int $seed, bool $isTarget): array
    {
        $base = $isTarget ? [120, 145, 190] : [160, 130, 100];
        $delta = ($seed % 5) * 12;

        return [
            min(240, $base[0] + $delta),
            min(240, $base[1] + intdiv($delta, 2)),
            min(240, $base[2] + intdiv($delta, 3)),
        ];
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
            . "      <publicationStmt><p>Seeded locally for pagination marker placement tests</p></publicationStmt>\n"
            . "      <sourceDesc><p>Generated by variance:seed-marker-placement-testbed</p></sourceDesc>\n"
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
        $relative = 'uploads/' . self::AUTHOR_FOLDER . '/' . self::WORK_FOLDER;
        Storage::disk('public')->deleteDirectory($relative);

        $legacyPath = base_path('../variance/' . $relative);
        if (is_dir($legacyPath)) {
            File::deleteDirectory($legacyPath);
        }
    }

    private function sourceText(): string
    {
        return <<<TXT
Chapitre I

L'atelier ouvre sur un titre bref, suivi d'une phrase de controle.
La phrase suivante sert de repere pour verifier si un marqueur glisse vers le paragraphe.

Chapitre II

Ici, le titre doit rester colle au marqueur place juste avant lui.
On compare ensuite la position de la premiere phrase visible dans les resultats.

Chapitre III

La section centrale contient une ponctuation stable et peu de variantes.
Elle permet de verifier un cas sans bruit editorial important.

Chapitre IV

Ce chapitre introduit une phrase plus longue afin de multiplier les points d'alignement.
Si le marqueur est deplace, l'ancrage se voit immediatement au debut du bloc.

Chapitre V

Derniere section de controle pour confirmer le comportement sur cinq pages consecutives.
La conclusion doit conserver la meme logique de placement que les chapitres precedents.
TXT;
    }

    private function targetText(): string
    {
        return <<<TXT
Chapitre I

L'atelier s'ouvre sur un titre bref, puis une phrase de verification.
La ligne suivante sert aussi de repere pour detecter un glissement de marqueur.

Chapitre II

Le titre doit rester ancre au marqueur positionne immediatement avant.
On controle ensuite l'endroit ou commence la premiere phrase du chapitre.

Chapitre III

La section mediane conserve une ponctuation reguliere et peu de remaniements.
Elle sert de cas temoin pour limiter les effets parasites de comparaison.

Chapitre IV

Ce chapitre ajoute une phrase plus ample pour augmenter les possibilites d'alignement.
Quand le marqueur derive, le decalage apparait tout de suite en tete de bloc.

Chapitre V

Derniere section de verification pour valider le comportement sur cinq pages successives.
La fermeture doit suivre la meme regle de positionnement que les chapitres precedents.
TXT;
    }
}
