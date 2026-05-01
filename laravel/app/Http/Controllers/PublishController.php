<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Comparison;
use App\Models\Version;
use App\Services\PageMarkerService;

class PublishController extends Controller
{
    public function __construct(private PageMarkerService $pageMarkerService)
    {
    }

    public const COMPONENTS = [
        'd.xhtml',
        'i.xhtml',
        'r.xhtml',
        's.xhtml',
        'source.xhtml',
        'target.xhtml',
    ];

    public function publish(Request $request)
    {
        $request->validate([
            'comparison_id' => 'required|integer',
            'destination'   => 'nullable|string|in:prod,dev',
            'insert_default_marker' => 'sometimes|boolean',
        ]);
        $destination = $request->input('destination', 'prod');
        $insertDefaultMarker = $request->boolean('insert_default_marker', false);

        // 1. Récupération de la comparaison --------------------------------
        /** @var Comparison $comparison */
        $comparison = Comparison::findOrFail($request->input('comparison_id'));
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        // 2. Récupérer l’œuvre via la version source ------------------------
        try {
            $paths = $this->resolvePaths($comparison);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'comparison_id' => $comparison->id,
            ], 422);
        }
        $sourceDir = $paths['source_dir'];
        $destDir   = $paths['dest_dir'];
        $destPath  = $paths['dest_path'];

        if (!is_dir($sourceDir)) {
            return response()->json([
                'error' => 'Dossier source introuvable pour cette comparaison.',
                'source_dir' => $sourceDir,
            ], 404);
        }

        $defaultMarkerInfo = null;
        if ($insertDefaultMarker) {
            $defaultMarkerInfo = $this->insertDefaultMarkers($comparison, $sourceDir);
        }

        $missing = [];
        foreach (self::COMPONENTS as $name) {
            $srcFile = $sourceDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($srcFile)) {
                $missing[] = $name;
            }
        }

        if ($destination === 'dev') {
            $this->deletePublishedFiles($destDir);
            $comparison->publication_scope = 'dev';
            $comparison->save();

            ['results' => $facsimiles, 'warnings' => $publishWarnings] = $this->publishFacsimilesForComparison($comparison);
            $manifestInfo = $this->publishManifests($comparison, $paths);
            $draftMirror = $this->mirrorDraftComparisonToLegacy($comparison, $paths, $sourceDir);

            $this->audit('comparison.published', [
                'comparison_id' => $comparison->id,
                'comparison_folder' => $comparison->folder,
                'destination' => 'dev',
                'missing_files' => $missing,
                'warning_count' => count($publishWarnings),
                'insert_default_marker' => $insertDefaultMarker,
            ]);

            return response()->json([
                'status'        => 'ok',
                'published_to'  => 'dev',
                'copied_files'  => [],
                'missing_files' => $missing,
                'manifests'     => $manifestInfo,
                'facsimiles'    => $facsimiles,
                'warnings'      => $publishWarnings,
                'default_marker' => $defaultMarkerInfo,
                'draft_mirror'  => $draftMirror,
            ]);
        }

        if (!is_dir($destPath)) {
            Storage::disk('public')->makeDirectory($destDir);
        }

        $copied = [];
        foreach (self::COMPONENTS as $name) {
            $srcFile = $sourceDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($srcFile)) {
                continue;
            }

            $raw = file_get_contents($srcFile);
            $sanitized = $this->sanitizeComponent($name, $raw);

            Storage::disk('public')->put("{$destDir}/{$name}", $sanitized);
            $copied[] = $name;

            $this->mirrorToLegacy($destDir, $name, $sanitized);
        }

        ['results' => $facsimiles, 'warnings' => $publishWarnings] = $this->publishFacsimilesForComparison($comparison);
        $manifestInfo = $this->publishManifests($comparison, $paths);
        $comparison->publication_scope = 'prod';
        $comparison->save();

        $this->audit('comparison.published', [
            'comparison_id' => $comparison->id,
            'comparison_folder' => $comparison->folder,
            'destination' => 'prod',
            'missing_files' => $missing,
            'warning_count' => count($publishWarnings),
            'insert_default_marker' => $insertDefaultMarker,
        ]);

        return response()->json([
            'status'        => 'ok',
            'published_to'  => $destDir,
            'copied_files'  => $copied,
            'missing_files' => $missing,
            'manifests'     => $manifestInfo,
            'facsimiles'    => $facsimiles,
            'warnings'      => $publishWarnings,
            'default_marker' => $defaultMarkerInfo,
        ]);
    }

    public function unpublish($comparison)
    {
        $comparison = Comparison::find($comparison);
        if (!$comparison) {
            return response()->json([
                'error' => 'Comparaison introuvable ou déjà supprimée.',
            ], 404);
        }
        $this->assertComparisonOwnership($comparison);
        $this->assertComparisonEditable($comparison);

        try {
            $paths = $this->resolvePaths($comparison);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'comparison_id' => $comparison->id,
            ], 422);
        }

        $sourceDir = $paths['source_dir'];
        $destDir   = $paths['dest_dir'];
        $destPath  = $paths['dest_path'];

        $deleted = [];
        $notFound = self::COMPONENTS;
        if (is_dir($destPath)) {
            ['deleted' => $deleted, 'not_found' => $notFound] = $this->deletePublishedFiles($destDir);
        }

        $comparison->publication_scope = null;
        $comparison->save();

        $this->audit('comparison.unpublished', [
            'comparison_id' => $comparison->id,
            'comparison_folder' => $comparison->folder,
            'deleted_files' => $deleted,
            'not_found' => $notFound,
        ]);

        return response()->json([
            'status'        => 'ok',
            'deleted_files' => $deleted,
            'not_found'     => $notFound,
        ]);
    }

    private function resolvePaths(Comparison $comparison): array
    {
        $workInfo = DB::table('versions')
            ->where('versions.id', $comparison->source_id)
            ->join('works', 'versions.work_id', '=', 'works.id')
            ->select('works.folder as work_folder', 'works.author_id', 'works.title as work_title')
            ->first();

        if (!$workInfo) {
            throw new \RuntimeException('Impossible de retrouver l’œuvre pour cette comparaison');
        }

        $author = DB::table('authors')
            ->where('id', $workInfo->author_id)
            ->select('folder', 'name')
            ->first();

        if (!$author || !$author->folder) {
            throw new \RuntimeException('Impossible de retrouver le dossier auteur.');
        }

        $basePath  = "uploads/{$author->folder}/{$workInfo->work_folder}";
        $sourceDir = storage_path("app/public/{$basePath}/comparisons/{$comparison->id}");
        if (!is_dir($sourceDir)) {
            $legacy = public_path("{$basePath}/comparisons/{$comparison->id}");
            if (is_dir($legacy)) {
                $sourceDir = $legacy;
            }
        }

        $destDir  = "{$basePath}/{$comparison->folder}";
        $destPath = storage_path("app/public/{$destDir}");

        return [
            'source_dir'        => $sourceDir,
            'dest_dir'          => $destDir,
            'dest_path'         => $destPath,
            'author_folder'     => $author->folder,
            'work_folder'       => $workInfo->work_folder,
            'comparison_folder' => $comparison->folder,
        ];
    }

    private function mirrorToLegacy(string $destDir, string $fileName, string $contents): void
    {
        $legacyDir = base_path('../variance/' . $destDir);
        $publicDir = Storage::disk('public')->path($destDir);

        if ($this->pathsShareSameEntry($publicDir, $legacyDir)) {
            return;
        }

        if (!is_dir($legacyDir)) {
            File::makeDirectory($legacyDir, 0775, true, true);
        }

        $legacyFile = $legacyDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($legacyFile) && @file_get_contents($legacyFile) === $contents) {
            return;
        }

        $tempFile = tempnam($legacyDir, 'variance-legacy-');
        if ($tempFile === false) {
            throw new \RuntimeException(sprintf('Impossible de créer un fichier temporaire dans %s', $legacyDir));
        }

        try {
            File::put($tempFile, $contents);
            @chmod($tempFile, 0664);

            if (!@rename($tempFile, $legacyFile)) {
                if (is_file($legacyFile) && is_writable($legacyDir)) {
                    @unlink($legacyFile);
                }

                if (!@rename($tempFile, $legacyFile)) {
                    throw new \RuntimeException(sprintf('Impossible de remplacer le fichier legacy %s', $legacyFile));
                }
            }
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function sanitizeComponent(string $fileName, string $contents): string
    {
        $needsCleanup = in_array($fileName, self::COMPONENTS, true);
        if (!$needsCleanup) {
            return $contents;
        }

        // Legacy templates expect plain inline markup inside the lists/workareas.
        // Block-level elements (e.g., <div>, <p>) generated by the automation
        // break the layout by prematurely closing surrounding containers.
        // The \b after <p> prevents stripping inline tags like <pb>.
        return preg_replace('#</?(?:div|p)\b[^>]*>#i', '', $contents);
    }

    private function deletePublishedFiles(string $destDir): array
    {
        $deleted = [];
        $notFound = [];

        foreach (self::COMPONENTS as $name) {
            $relative = "{$destDir}/{$name}";
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
                $deleted[] = $name;
            } else {
                $notFound[] = $name;
            }
        }

        if (empty(Storage::disk('public')->files($destDir))
            && empty(Storage::disk('public')->directories($destDir))) {
            Storage::disk('public')->deleteDirectory($destDir);
        }

        $legacyDir = base_path('../variance/' . $destDir);
        if (is_dir($legacyDir)) {
            File::deleteDirectory($legacyDir);
        }

        return ['deleted' => $deleted, 'not_found' => $notFound];
    }

    private function publishFacsimilesForComparison(Comparison $comparison): array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $results = [];
        $warnings = [];

        $sourceVersion = $comparison->sourceVersion;
        if ($sourceVersion instanceof Version) {
            try {
                $results['source'] = $this->publishFacsimilesForVersion($sourceVersion);
            } catch (\Throwable $e) {
                report($e);
                $results['source'] = [
                    'status' => 'warning',
                    'reason' => 'copy_failed',
                    'message' => $e->getMessage(),
                ];
                $warnings[] = sprintf(
                    'Les fac-similés source n’ont pas pu être recopiés vers le miroir legacy : %s',
                    $e->getMessage()
                );
            }
        }

        $targetVersion = $comparison->targetVersion;
        if ($targetVersion instanceof Version) {
            try {
                $results['target'] = $this->publishFacsimilesForVersion($targetVersion);
            } catch (\Throwable $e) {
                report($e);
                $results['target'] = [
                    'status' => 'warning',
                    'reason' => 'copy_failed',
                    'message' => $e->getMessage(),
                ];
                $warnings[] = sprintf(
                    'Les fac-similés cible n’ont pas pu être recopiés vers le miroir legacy : %s',
                    $e->getMessage()
                );
            }
        }

        return [
            'results' => $results,
            'warnings' => $warnings,
        ];
    }

    private function publishFacsimilesForVersion(Version $version): array
    {
        $version->loadMissing('work.author');

        if ($version->is_legacy || $version->work?->is_legacy) {
            return ['status' => 'skipped', 'reason' => 'legacy'];
        }

        $paths = $this->facsimilePaths($version);
        if (!$paths['source_exists'] || empty($paths['source_files'])) {
            return ['status' => 'skipped', 'reason' => 'empty'];
        }

        $sourceDir = Storage::disk('public')->path($paths['source_prefix']);
        if ($this->pathsShareSameEntry($sourceDir, $paths['dest_dir'])) {
            return ['status' => 'skipped', 'reason' => 'same_mount'];
        }

        $parentDir = dirname($paths['dest_dir']);
        if ($this->legacyFacsimilesAreSynced($paths['source_prefix'], $paths['source_files'], $paths['dest_dir'])) {
            return ['status' => 'skipped', 'reason' => 'already_synced'];
        }

        if ($this->legacyFacsimileMirrorIsReadOnlyWithImages($paths['dest_dir'])) {
            return [
                'status' => 'skipped',
                'reason' => 'read_only_legacy_mirror',
                'dest_dir' => $paths['dest_dir'],
            ];
        }

        $this->ensureLegacyDirectoryExists($parentDir);
        if (is_dir($paths['dest_dir'])) {
            File::deleteDirectory($paths['dest_dir']);
        }
        $this->ensureLegacyDirectoryExists($paths['dest_dir']);

        $copied = 0;
        $disk = Storage::disk('public');
        foreach ($paths['source_files'] as $fileName) {
            $contents = $disk->get($paths['source_prefix'] . '/' . $fileName);
            File::put($paths['dest_dir'] . '/' . $fileName, $contents);
            @chmod($paths['dest_dir'] . '/' . $fileName, 0664);
            $copied++;
        }

        return [
            'status' => 'ok',
            'copied' => $copied,
            'dest_dir' => $paths['dest_dir'],
        ];
    }

    private function legacyFacsimilesAreSynced(string $sourcePrefix, array $sourceFiles, string $destDir): bool
    {
        if (!is_dir($destDir)) {
            return false;
        }

        $disk = Storage::disk('public');
        $destFiles = collect(File::files($destDir))
            ->map(fn (\SplFileInfo $file) => $file->getFilename())
            ->filter(fn (string $name) => preg_match('/\.(jpe?g|png)$/i', $name))
            ->sort()
            ->values()
            ->all();

        $sortedSourceFiles = collect($sourceFiles)->sort()->values()->all();
        if ($destFiles !== $sortedSourceFiles) {
            return false;
        }

        foreach ($sortedSourceFiles as $fileName) {
            $sourcePath = $disk->path($sourcePrefix . '/' . $fileName);
            $destPath = $destDir . '/' . $fileName;
            if (!is_file($destPath)) {
                return false;
            }

            if (@filesize($sourcePath) !== @filesize($destPath)) {
                return false;
            }
        }

        return true;
    }

    private function legacyFacsimileMirrorIsReadOnlyWithImages(string $destDir): bool
    {
        if (!is_dir($destDir) || $this->pathIsWritableByCurrentProcess($destDir)) {
            return false;
        }

        return collect(File::files($destDir))
            ->contains(fn (\SplFileInfo $file) => preg_match('/\.(jpe?g|png)$/i', $file->getFilename()));
    }

    private function pathIsWritableByCurrentProcess(string $path): bool
    {
        $stat = @stat($path);
        if (!is_array($stat)) {
            return is_writable($path);
        }

        $mode = (int) ($stat['mode'] ?? 0);
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $gid = function_exists('posix_getegid') ? posix_getegid() : null;
        $groups = function_exists('posix_getgroups') ? posix_getgroups() : [];

        if ($uid !== null && $uid !== 0 && (int) ($stat['uid'] ?? -1) === $uid) {
            return (bool) ($mode & 0o200);
        }

        if ($gid !== null && ((int) ($stat['gid'] ?? -1) === $gid || in_array((int) ($stat['gid'] ?? -1), $groups, true))) {
            return (bool) ($mode & 0o020);
        }

        return (bool) ($mode & 0o002);
    }

    private function ensureLegacyDirectoryExists(string $path): void
    {
        File::ensureDirectoryExists($path, 0775, true);
        @chmod($path, 02775);
    }

    private function pathsShareSameEntry(?string $left, ?string $right): bool
    {
        if (!$left || !$right || !file_exists($left) || !file_exists($right)) {
            return false;
        }

        $leftStat = @stat($left);
        $rightStat = @stat($right);
        if (!is_array($leftStat) || !is_array($rightStat)) {
            return false;
        }

        return ($leftStat['dev'] ?? null) === ($rightStat['dev'] ?? null)
            && ($leftStat['ino'] ?? null) === ($rightStat['ino'] ?? null);
    }

    private function facsimilePaths(Version $version): array
    {
        $authorFolder  = $version->work->author->folder;
        $workFolder    = $version->work->folder;
        $versionFolder = $version->folder;

        $sourcePrefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk = Storage::disk('public');

        $files = $disk->exists($sourcePrefix)
            ? collect($disk->files($sourcePrefix))
                ->map(fn ($path) => basename($path))
                ->filter(fn ($name) => preg_match('/\.(jpe?g|png)$/i', $name))
                ->values()
                ->toArray()
            : [];

        $destDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}");

        return [
            'source_prefix' => $sourcePrefix,
            'source_exists' => $disk->exists($sourcePrefix),
            'source_files'  => $files,
            'dest_dir'      => $destDir,
        ];
    }

    private function insertDefaultMarkers(Comparison $comparison, string $sourceDir): array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $results = [];
        $inserted = 0;

        foreach ([
            'source' => $comparison->sourceVersion,
            'target' => $comparison->targetVersion,
        ] as $role => $version) {
            $results[$role] = $this->insertDefaultMarkerForRole($comparison, $version, $role, $sourceDir);
            if (($results[$role]['status'] ?? null) === 'inserted') {
                $inserted++;
            }
        }

        return [
            'inserted' => $inserted,
            'details'  => $results,
        ];
    }

    private function insertDefaultMarkerForRole(Comparison $comparison, ?Version $version, string $role, string $sourceDir): array
    {
        if (!$version) {
            return ['status' => 'skipped', 'reason' => 'missing_version'];
        }

        $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
        $filePath = $sourceDir . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($filePath)) {
            return ['status' => 'skipped', 'reason' => 'missing_file'];
        }

        $paths = [$filePath];
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder = $version->work->folder ?? null;
        if ($authorFolder && $workFolder) {
            $legacyPath = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}/{$fileName}");
            if (is_file($legacyPath) && $legacyPath !== $filePath) {
                $paths[] = $legacyPath;
            }
        }

        $imageNumber = $this->findFirstFacsimileImageNumber($version);
        if ($imageNumber === null) {
            return ['status' => 'skipped', 'reason' => 'no_images'];
        }

        $marker = $this->buildDefaultMarker($imageNumber, $role);
        $updated = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $html = File::get($path);
            if (preg_match('/<span\s+class="page-marker"/i', $html)) {
                continue;
            }
            $backupName = $role === 'source' ? 'source.original.xhtml' : 'target.original.xhtml';
            $backupPath = dirname($path) . DIRECTORY_SEPARATOR . $backupName;
            if (!is_file($backupPath)) {
                File::ensureDirectoryExists(dirname($backupPath));
                File::put($backupPath, $html);
            }
            File::put($path, $marker . "\n" . ltrim($html));
            $updated[] = $path;
        }

        if (empty($updated)) {
            return ['status' => 'skipped', 'reason' => 'already_has_marker'];
        }

        return [
            'status' => 'inserted',
            'image'  => $imageNumber,
            'files'  => $updated,
        ];
    }

    private function findFirstFacsimileImageNumber(Version $version): ?int
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work?->author?->folder;
        $workFolder   = $version->work?->folder;
        $versionFolder = $version->folder;

        if (!$authorFolder || !$workFolder || !$versionFolder) {
            return null;
        }

        $numbers = [];
        $sourcePrefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        $disk = Storage::disk('public');
        if ($disk->exists($sourcePrefix)) {
            foreach ($disk->files($sourcePrefix) as $path) {
                $name = basename($path);
                if (str_contains(strtolower($name), '_thumb')) {
                    continue;
                }
                if (preg_match('/_(\d+)\.(?:jpe?g|png)$/i', $name, $m)) {
                    $numbers[] = (int) $m[1];
                }
            }
        }

        if (empty($numbers)) {
            $legacyDir = base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}");
            if (is_dir($legacyDir)) {
                foreach (File::files($legacyDir) as $file) {
                    $name = $file->getFilename();
                    if (str_contains(strtolower($name), '_thumb')) {
                        continue;
                    }
                    if (preg_match('/_(\d+)\.(?:jpe?g|png)$/i', $name, $m)) {
                        $numbers[] = (int) $m[1];
                    }
                }
            }
        }

        if (empty($numbers)) {
            return null;
        }

        sort($numbers, SORT_NUMERIC);
        return $numbers[0];
    }

    private function buildDefaultMarker(int $imageNumber, string $role): string
    {
        $orientation = $role === 'source' ? 'right' : 'left';
        $padded = str_pad((string) $imageNumber, 3, '0', STR_PAD_LEFT);

        return sprintf(
            '<span class="page-marker" data-image-name="%1$s"><span class="page-number">%1$s</span><img src="/img/settings/page_%2$s.svg" /></span>',
            $padded,
            $orientation
        );
    }

    private function mirrorDraftComparisonToLegacy(Comparison $comparison, array $paths, string $sourceDir): array
    {
        $authorFolder = $paths['author_folder'] ?? null;
        $workFolder = $paths['work_folder'] ?? null;
        if (!$authorFolder || !$workFolder || !is_dir($sourceDir)) {
            return ['status' => 'skipped'];
        }

        $legacyRoot = base_path('../variance/uploads');
        $legacyDir = $legacyRoot . DIRECTORY_SEPARATOR . $authorFolder . DIRECTORY_SEPARATOR . $workFolder . DIRECTORY_SEPARATOR . 'comparisons' . DIRECTORY_SEPARATOR . $comparison->id;

        $sourceReal = realpath($sourceDir);
        $legacyRootReal = realpath($legacyRoot);
        if ($sourceReal && $legacyRootReal && str_starts_with($sourceReal, $legacyRootReal)) {
            return ['status' => 'skipped', 'reason' => 'already_legacy'];
        }

        if ($this->legacyDraftComparisonAlreadySynced($sourceDir, $legacyDir)) {
            return ['status' => 'skipped', 'reason' => 'already_synced', 'dir' => $legacyDir];
        }

        File::ensureDirectoryExists($legacyDir);
        $copied = [];
        foreach ($this->draftMirrorFiles($sourceDir) as $file) {
            $name = $file->getFilename();
            $dest = $legacyDir . DIRECTORY_SEPARATOR . $name;
            File::copy($file->getPathname(), $dest);
            $copied[] = $name;
        }

        return [
            'status' => 'ok',
            'dir'    => $legacyDir,
            'files'  => $copied,
        ];
    }

    private function legacyDraftComparisonAlreadySynced(string $sourceDir, string $legacyDir): bool
    {
        if (!is_dir($sourceDir) || !is_dir($legacyDir)) {
            return false;
        }

        $sourceFiles = $this->draftMirrorFiles($sourceDir);
        if (empty($sourceFiles)) {
            return false;
        }

        foreach ($sourceFiles as $file) {
            $legacyFile = $legacyDir . DIRECTORY_SEPARATOR . $file->getFilename();
            if (!is_file($legacyFile)) {
                return false;
            }

            if (@filesize($file->getPathname()) !== @filesize($legacyFile)) {
                return false;
            }

            if (@md5_file($file->getPathname()) !== @md5_file($legacyFile)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int,\SplFileInfo> */
    private function draftMirrorFiles(string $sourceDir): array
    {
        return array_values(array_filter(
            File::files($sourceDir),
            static fn (\SplFileInfo $file) => !preg_match('/\.original\.xhtml$/i', $file->getFilename())
        ));
    }

    private function publishManifests(Comparison $comparison, array $paths): array
    {
        $authorFolder    = $paths['author_folder'];
        $workFolder      = $paths['work_folder'];
        $comparisonFolder= $paths['comparison_folder'];

        $sourceVersion = DB::table('versions')->select('folder')->find($comparison->source_id);
        $targetVersion = DB::table('versions')->select('folder')->find($comparison->target_id);
        if (!$sourceVersion || !$targetVersion) {
            return [];
        }

        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparisonFolder));
        $manifests = [];

        foreach ([
            'source' => $sourceVersion->folder,
            'target' => $targetVersion->folder,
        ] as $type => $versionFolder) {
            if (!$versionFolder) {
                continue;
            }

            $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
            $filename    = sprintf('images_%s_%s.json', $type, $baseName);
            $storagePath = "{$relativeDir}/{$filename}";

            $entries = $this->loadExistingManifestEntries($storagePath);
            if ($entries === null) {
                $entries = $this->collectManifestEntries($authorFolder, $workFolder, $versionFolder);
            }
            if (empty($entries)) {
                continue;
            }

            $jsonPayload = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            Storage::disk('public')->put($storagePath, $jsonPayload);
            $this->mirrorToLegacy($relativeDir, $filename, $jsonPayload);

            $manifests[$type] = [
                'file'  => $storagePath,
                'count' => count($entries),
            ];
        }

        return $manifests;
    }

    private function removeManifests(Comparison $comparison, array $paths): void
    {
        $authorFolder    = $paths['author_folder'];
        $workFolder      = $paths['work_folder'];
        $comparisonFolder= $paths['comparison_folder'];

        $sourceVersion = DB::table('versions')->select('folder')->find($comparison->source_id);
        $targetVersion = DB::table('versions')->select('folder')->find($comparison->target_id);
        if (!$sourceVersion || !$targetVersion) {
            return;
        }

        $baseName = strtolower(sprintf('%s--%s--%s', $authorFolder, $workFolder, $comparisonFolder));
        $disk = Storage::disk('public');

        foreach ([
            'source' => $sourceVersion->folder,
            'target' => $targetVersion->folder,
        ] as $type => $versionFolder) {
            if (!$versionFolder) {
                continue;
            }

            $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
            $filename    = sprintf('images_%s_%s.json', $type, $baseName);
            $storagePath = "{$relativeDir}/{$filename}";

            if ($disk->exists($storagePath)) {
                $disk->delete($storagePath);
            }

            $legacyFile = base_path('../variance/' . $storagePath);
            if (is_file($legacyFile)) {
                @unlink($legacyFile);
            }
        }
    }

    private function loadExistingManifestEntries(string $relativePath): ?array
    {
        $disk = Storage::disk('public');
        if ($disk->exists($relativePath)) {
            $entries = json_decode($disk->get($relativePath), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($entries)) {
                return $entries;
            }
        }

        $legacyPath = base_path('../variance/' . $relativePath);
        if (is_file($legacyPath)) {
            $entries = json_decode(File::get($legacyPath), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($entries)) {
                return $entries;
            }
        }

        return null;
    }

    private function assertComparisonEditable(Comparison $comparison): void
    {
        if ($comparison->is_legacy) {
            abort(403, 'Cette comparaison est en lecture seule.');
        }
    }

    private function assertComparisonOwnership(Comparison $comparison): void
    {
        $user = auth()->user();
        if (! $user || $user->is_admin) {
            return;
        }

        if ($comparison->is_legacy && request()->isMethod('get')) {
            return;
        }

        if ((int) $comparison->created_by !== (int) $user->id) {
            abort(403, 'Accès limité aux comparaisons personnelles.');
        }
    }

    private function collectManifestEntries(string $authorFolder, string $workFolder, string $versionFolder): array
    {
        $disk = Storage::disk('public');
        $prefix = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
        if (!$disk->exists($prefix)) {
            return [];
        }

        $files = collect($disk->files($prefix))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => preg_match('/\.(jpe?g|png)$/i', $name))
            ->reject(fn ($name) => str_contains(strtolower($name), '_thumb'))
            ->sort(function ($a, $b) {
                return strnatcasecmp($a, $b);
            })
            ->values();

        return $files->map(function ($file) use ($disk, $prefix, $authorFolder, $workFolder, $versionFolder) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $ext  = pathinfo($file, PATHINFO_EXTENSION);
            $thumbName = $base . '_thumb.' . $ext;

            $big   = "/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}/{$file}";
            $small = $disk->exists("{$prefix}/{$thumbName}")
                ? "/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}/{$thumbName}"
                : $big;

            return [
                'small' => $small,
                'big'   => $big,
            ];
        })->toArray();
    }
}
