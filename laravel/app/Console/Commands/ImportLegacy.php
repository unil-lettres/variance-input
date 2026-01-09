<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Comparison;
use App\Models\Version;
use App\Models\Work;
use App\Models\WorkStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportLegacy extends Command
{
    protected $signature = 'variance:import-legacy
        {dump : Path to the legacy SQL dump}
        {--assets-root= : Legacy assets root (defaults to ../variance)}
        {--dry-run : Parse the dump and report counts only}
        {--skip-pdfs : Do not copy/rename legacy PDFs}';

    protected $description = 'Import legacy Variance data (authors, works, versions, comparisons, chapters).';

    public function handle(): int
    {
        $dumpPath = (string) $this->argument('dump');
        if (!is_file($dumpPath)) {
            $this->error("Dump not found: {$dumpPath}");
            return self::FAILURE;
        }

        $assetsRoot = (string) ($this->option('assets-root') ?: base_path('../variance'));
        $tables = ['authors', 'works', 'versions', 'comparisons', 'chapters'];
        $data = $this->parseDump($dumpPath, $tables);

        foreach ($tables as $table) {
            $count = count($data[$table] ?? []);
            $this->info(sprintf('%s: %d rows', $table, $count));
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run only; no data imported.');
            return self::SUCCESS;
        }

        $workModels = [];
        $workMap = [];

        DB::beginTransaction();
        try {
            $authorMap = $this->importAuthors($data['authors'] ?? []);
            $workImport = $this->importWorks($data['works'] ?? [], $authorMap);
            $workModels = $workImport['models'];
            $workMap = $workImport['map'];

            $versionMap = $this->importVersions($data['versions'] ?? [], $workMap);
            $this->importComparisons($data['comparisons'] ?? [], $versionMap);
            $this->importChapters($data['chapters'] ?? []);
            $this->ensureWorkStatuses(array_values($workModels));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            throw $e;
        }

        $this->writeWorkMap($workModels);

        if (!$this->option('skip-pdfs')) {
            $this->syncLegacyPdfs($workModels, $assetsRoot);
        }

        $this->info('Legacy import completed.');

        return self::SUCCESS;
    }

    private function parseDump(string $dumpPath, array $tables): array
    {
        $sql = file_get_contents($dumpPath);
        if ($sql === false) {
            throw new \RuntimeException('Unable to read dump: ' . $dumpPath);
        }

        $data = array_fill_keys($tables, []);
        $len = strlen($sql);
        $pos = 0;
        $needle = 'INSERT INTO `';

        while (true) {
            $insertPos = strpos($sql, $needle, $pos);
            if ($insertPos === false) {
                break;
            }

            $tableStart = $insertPos + strlen($needle);
            $tableEnd = strpos($sql, '`', $tableStart);
            if ($tableEnd === false) {
                break;
            }

            $table = substr($sql, $tableStart, $tableEnd - $tableStart);

            $valuesPos = strpos($sql, 'VALUES', $tableEnd);
            if ($valuesPos === false) {
                $pos = $tableEnd + 1;
                continue;
            }

            $valuesPos += strlen('VALUES');
            while ($valuesPos < $len && ctype_space($sql[$valuesPos])) {
                $valuesPos++;
            }

            $inString = false;
            $escaped = false;
            $i = $valuesPos;
            for (; $i < $len; $i++) {
                $char = $sql[$i];
                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($char === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($char === "'") {
                        $inString = false;
                    }
                    continue;
                }

                if ($char === "'") {
                    $inString = true;
                    continue;
                }

                if ($char === ';') {
                    break;
                }
            }

            if ($i >= $len) {
                break;
            }

            $valuesPart = substr($sql, $valuesPos, $i - $valuesPos);
            if (array_key_exists($table, $data)) {
                $data[$table] = array_merge($data[$table], $this->parseValues($valuesPart));
            }

            $pos = $i + 1;
        }

        return $data;
    }

    private function parseValues(string $valuesPart): array
    {
        $rows = [];
        $row = [];
        $value = '';
        $inString = false;
        $escaped = false;
        $valueWasQuoted = false;
        $depth = 0;

        $len = strlen($valuesPart);
        for ($i = 0; $i < $len; $i++) {
            $char = $valuesPart[$i];

            if ($inString) {
                if ($escaped) {
                    $value .= $char;
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === "'") {
                    $inString = false;
                    continue;
                }
                $value .= $char;
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $valueWasQuoted = true;
                continue;
            }

            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    $row = [];
                    $value = '';
                    $valueWasQuoted = false;
                }
                continue;
            }

            if ($char === ')') {
                if ($depth === 1) {
                    $row[] = $this->normalizeValue($value, $valueWasQuoted);
                    $rows[] = $row;
                    $row = [];
                    $value = '';
                    $valueWasQuoted = false;
                }
                $depth--;
                continue;
            }

            if ($char === ',' && $depth === 1) {
                $row[] = $this->normalizeValue($value, $valueWasQuoted);
                $value = '';
                $valueWasQuoted = false;
                continue;
            }

            if ($depth >= 1) {
                $value .= $char;
            }
        }

        return $rows;
    }

    private function normalizeValue(string $value, bool $wasQuoted): mixed
    {
        $trimmed = trim($value);
        if (!$wasQuoted && strtoupper($trimmed) === 'NULL') {
            return null;
        }

        if ($wasQuoted) {
            return $this->decodeString($trimmed);
        }

        if ($trimmed === '') {
            return '';
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $this->decodeString($trimmed);
    }

    private function decodeString(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return str_replace("\xc2\xa0", ' ', $decoded);
    }

    private function importAuthors(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            [$legacyId, $name, $folder, $order] = array_pad($row, 4, null);
            if (!$folder) {
                $this->warn('Skipping author without folder (legacy id ' . $legacyId . ').');
                continue;
            }

            $author = Author::firstOrNew(['folder' => $folder]);
            $author->name = $name;
            $author->order = $order;
            $author->is_legacy = true;
            $author->save();

            $map[(int) $legacyId] = $author->id;
        }

        return $map;
    }

    private function importWorks(array $rows, array $authorMap): array
    {
        $map = [];
        $models = [];

        foreach ($rows as $row) {
            [$legacyId, $legacyAuthorId, $title, $folder, $desc, $imageUrl] = array_pad($row, 6, null);
            if (!$folder) {
                $this->warn('Skipping work without folder (legacy id ' . $legacyId . ').');
                continue;
            }

            $authorId = $authorMap[(int) $legacyAuthorId] ?? null;
            if (!$authorId) {
                $this->warn('Skipping work with unknown author (legacy id ' . $legacyId . ').');
                continue;
            }

            $work = Work::firstOrNew(['folder' => $folder]);
            $work->author_id = $authorId;
            $work->title = $title;
            $work->desc = $desc;
            $work->image_url = $imageUrl;
            $work->is_legacy = true;
            $work->save();

            $map[(int) $legacyId] = $work->id;
            $models[(int) $legacyId] = $work;
        }

        return ['map' => $map, 'models' => $models];
    }

    private function importVersions(array $rows, array $workMap): array
    {
        $map = [];

        foreach ($rows as $row) {
            [$legacyId, $legacyWorkId, $name, $folder] = array_pad($row, 4, null);
            if (!$folder) {
                $this->warn('Skipping version without folder (legacy id ' . $legacyId . ').');
                continue;
            }

            $workId = $workMap[(int) $legacyWorkId] ?? null;
            if (!$workId) {
                $this->warn('Skipping version with unknown work (legacy id ' . $legacyId . ').');
                continue;
            }

            $version = Version::firstOrNew(['folder' => $folder]);
            $version->work_id = $workId;
            $version->name = $name;
            $version->is_legacy = true;
            $version->save();

            $map[(int) $legacyId] = $version->id;
        }

        return $map;
    }

    private function importComparisons(array $rows, array $versionMap): void
    {
        foreach ($rows as $row) {
            [$legacySourceId, $legacyTargetId, $folder, $number, $prefix] = array_pad($row, 5, null);
            if (!$folder) {
                $this->warn('Skipping comparison without folder.');
                continue;
            }

            $sourceId = $versionMap[(int) $legacySourceId] ?? null;
            $targetId = $versionMap[(int) $legacyTargetId] ?? null;
            if (!$sourceId || !$targetId) {
                $this->warn('Skipping comparison with missing versions (' . $folder . ').');
                continue;
            }

            $comparison = Comparison::firstOrNew(['folder' => $folder]);
            $comparison->source_id = $sourceId;
            $comparison->target_id = $targetId;
            $comparison->number = $number;
            $comparison->prefix_label = $prefix;
            $comparison->is_legacy = true;
            $comparison->save();
        }
    }

    private function importChapters(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $folders = array_unique(array_filter(array_map(
            fn ($row) => $row[1] ?? null,
            $rows
        )));

        foreach ($folders as $folder) {
            DB::table('chapters')->where('folder', $folder)->delete();
        }

        $idMap = [];
        $pendingParents = [];
        foreach ($rows as $row) {
            [
                $legacyId,
                $folder,
                $level,
                $labelSource,
                $labelTarget,
                $legacyParent,
                $startSource,
                $startTarget,
                $idTomeSource,
                $idTomeTarget,
            ] = array_pad($row, 10, null);

            if (!$folder) {
                $this->warn('Skipping chapter without folder (legacy id ' . $legacyId . ').');
                continue;
            }

            $newId = DB::table('chapters')->insertGetId([
                'folder'            => $folder,
                'level'             => $level ?? '',
                'label_source'      => $labelSource ?? '',
                'label_target'      => $labelTarget ?? '',
                'chapter_parent'    => null,
                'start_line_source' => (string) ($startSource ?? ''),
                'start_line_target' => (string) ($startTarget ?? ''),
                'id_tome_source'    => (int) ($idTomeSource ?? 0),
                'id_tome_target'    => (int) ($idTomeTarget ?? 0),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $idMap[(int) $legacyId] = $newId;

            $legacyParent = (int) ($legacyParent ?? 0);
            if ($legacyParent > 0) {
                $pendingParents[] = [$newId, $legacyParent];
            }
        }

        foreach ($pendingParents as [$newId, $legacyParent]) {
            if (!isset($idMap[$legacyParent])) {
                continue;
            }
            DB::table('chapters')
                ->where('id', $newId)
                ->update(['chapter_parent' => $idMap[$legacyParent]]);
        }
    }

    private function ensureWorkStatuses(array $works): void
    {
        foreach ($works as $work) {
            WorkStatus::updateOrCreate(
                ['work_id' => $work->id],
                [
                    'global_status'     => 1,
                    'desc_status'       => 1,
                    'notice_status'     => 1,
                    'image_status'      => $work->image_url ? 1 : 0,
                    'comparison_status' => 1,
                ]
            );
        }
    }

    private function writeWorkMap(array $workModels): void
    {
        $outputDir = storage_path('app/private/legacy_import');
        if (!is_dir($outputDir)) {
            File::makeDirectory($outputDir, 0775, true);
        }

        $map = [];
        foreach ($workModels as $legacyId => $work) {
            $map[] = [
                'legacy_id' => (int) $legacyId,
                'new_id'    => (int) $work->id,
                'folder'    => $work->folder,
                'title'     => $work->title,
            ];
        }

        $path = $outputDir . '/work_id_map.json';
        File::put($path, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Work ID map saved to: ' . $path);
    }

    private function syncLegacyPdfs(array $workModels, string $assetsRoot): void
    {
        $pdfDir = rtrim($assetsRoot, '/') . '/uploads/pdf';
        if (!is_dir($pdfDir)) {
            $this->warn('PDF directory not found: ' . $pdfDir);
            return;
        }

        $copied = 0;
        $missing = 0;
        foreach ($workModels as $legacyId => $work) {
            $legacyPdf = $pdfDir . '/' . $legacyId . '.pdf';
            if (!is_file($legacyPdf)) {
                $missing++;
                continue;
            }

            $newPdf = $pdfDir . '/' . $work->id . '.pdf';
            if (!is_file($newPdf)) {
                if (!@copy($legacyPdf, $newPdf)) {
                    $this->warn('Failed to copy PDF for work ' . $work->id);
                    continue;
                }
                $copied++;
            }

            if ($work->pdf_url !== basename($newPdf)) {
                $work->pdf_url = basename($newPdf);
                $work->save();
            }
        }

        $this->info(sprintf('PDF sync complete: %d copied, %d missing.', $copied, $missing));
    }
}
