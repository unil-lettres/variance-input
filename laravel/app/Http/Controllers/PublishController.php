<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Comparison;
use App\Models\Version;

class PublishController extends Controller
{
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

            $manifestInfo = $this->publishManifests($comparison, $paths);
            $facsimiles = $this->publishFacsimilesForComparison($comparison);
            $draftMirror = $this->mirrorDraftComparisonToLegacy($comparison, $paths, $sourceDir);

            return response()->json([
                'status'        => 'ok',
                'published_to'  => 'dev',
                'copied_files'  => [],
                'missing_files' => $missing,
                'manifests'     => $manifestInfo,
                'facsimiles'    => $facsimiles,
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

        $manifestInfo = $this->publishManifests($comparison, $paths);
        $facsimiles = $this->publishFacsimilesForComparison($comparison);
        $comparison->publication_scope = 'prod';
        $comparison->save();

        return response()->json([
            'status'        => 'ok',
            'published_to'  => $destDir,
            'copied_files'  => $copied,
            'missing_files' => $missing,
            'manifests'     => $manifestInfo,
            'facsimiles'    => $facsimiles,
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
        if (!is_dir($legacyDir)) {
            File::makeDirectory($legacyDir, 0775, true, true);
        }

        $legacyFile = $legacyDir . DIRECTORY_SEPARATOR . $fileName;
        File::put($legacyFile, $contents);
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

        $sourceVersion = $comparison->sourceVersion;
        if ($sourceVersion instanceof Version) {
            $results['source'] = $this->publishFacsimilesForVersion($sourceVersion);
        }

        $targetVersion = $comparison->targetVersion;
        if ($targetVersion instanceof Version) {
            $results['target'] = $this->publishFacsimilesForVersion($targetVersion);
        }

        return $results;
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

        File::ensureDirectoryExists($paths['dest_dir']);
        // Preserve existing manifest JSON files while refreshing published images.
        foreach (File::files($paths['dest_dir']) as $file) {
            $name = $file->getFilename();
            if (preg_match('/\.(jpe?g|png)$/i', $name)) {
                File::delete($file->getPathname());
            }
        }

        $copied = 0;
        $disk = Storage::disk('public');
        foreach ($paths['source_files'] as $fileName) {
            $contents = $disk->get($paths['source_prefix'] . '/' . $fileName);
            File::put($paths['dest_dir'] . '/' . $fileName, $contents);
            $copied++;
        }

        return [
            'status' => 'ok',
            'copied' => $copied,
            'dest_dir' => $paths['dest_dir'],
        ];
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

        $destDir = "/var/www/variance/uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";

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

        File::ensureDirectoryExists($legacyDir);
        $copied = [];
        foreach (File::files($sourceDir) as $file) {
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
        $comparison->loadMissing('sourceVersion.work', 'targetVersion.work');

        $sourceVersion = $comparison->sourceVersion;
        $targetVersion = $comparison->targetVersion;

        if ($comparison->is_legacy
            || $sourceVersion?->is_legacy
            || $targetVersion?->is_legacy
            || $sourceVersion?->work?->is_legacy
            || $targetVersion?->work?->is_legacy) {
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
