<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Comparison;

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
        $request->validate(['comparison_id' => 'required|integer']);

        // 1. Récupération de la comparaison --------------------------------
        /** @var Comparison $comparison */
        $comparison = Comparison::findOrFail($request->input('comparison_id'));

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

        if (!is_dir($destPath)) {
            Storage::disk('public')->makeDirectory($destDir);
        }

        // 5. Copier uniquement les composants XHTML nécessaires -----------
        if (!is_dir($sourceDir)) {
            return response()->json([
                'error' => 'Dossier source introuvable pour cette comparaison.',
                'source_dir' => $sourceDir,
            ], 404);
        }

        $copied = [];
        $missing = [];
        foreach (self::COMPONENTS as $name) {
            $srcFile = $sourceDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($srcFile)) {
                $missing[] = $name;
                continue;
            }

            $raw = file_get_contents($srcFile);
            $sanitized = $this->sanitizeComponent($name, $raw);

            Storage::disk('public')->put("{$destDir}/{$name}", $sanitized);
            $copied[] = $name;

            $this->mirrorToLegacy($destDir, $name, $sanitized);
        }

        $manifestInfo = $this->publishManifests($comparison, $paths);

        return response()->json([
            'status'        => 'ok',
            'published_to'  => $destDir,
            'copied_files'  => $copied,
            'missing_files' => $missing,
            'manifests'     => $manifestInfo,
        ]);
    }

    public function unpublish(Comparison $comparison)
    {
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

        if (!is_dir($destPath)) {
            return response()->json([
                'status' => 'ok',
                'deleted_files' => [],
                'not_found' => self::COMPONENTS,
            ]);
        }

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
        return preg_replace('#</?(?:div|p)[^>]*>#i', '', $contents);
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

            $entries = $this->collectManifestEntries($authorFolder, $workFolder, $versionFolder);
            if (empty($entries)) {
                continue;
            }

            $relativeDir = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";
            $filename    = sprintf('images_%s_%s.json', $type, $baseName);
            $jsonPayload = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            Storage::disk('public')->put("{$relativeDir}/{$filename}", $jsonPayload);
            $this->mirrorToLegacy($relativeDir, $filename, $jsonPayload);

            $manifests[$type] = [
                'file'  => "{$relativeDir}/{$filename}",
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
