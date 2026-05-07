<?php

namespace App\Services;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VersionReaderService
{
    private const READER_DATASET_SCHEMA_VERSION = 3;

    public function __construct(
        private PageMarkerService $pageMarkerService,
        private VersionTextService $versionTextService,
    ) {}

    public function responsePayload(Version $version, array $dataset): array
    {
        $pagePlans = is_array($dataset['page_plans'] ?? null)
            ? $dataset['page_plans']
            : $this->readerPagePlans($dataset['text'] ?? null, $dataset['markers'] ?? [], $dataset['facsimiles'] ?? []);
        $pageSummaries = array_map(function (array $page) {
            return [
                'label' => $page['label'] ?? null,
                'image' => $page['image'] ?? null,
                'line' => $page['line'] ?? null,
                'imageCode' => $page['imageCode'] ?? null,
                'anchorOffset' => $page['anchorOffset'] ?? null,
                'anchorPhrase' => $page['anchorPhrase'] ?? null,
                'guessed' => $page['guessed'] ?? false,
            ];
        }, $pagePlans);
        $currentPage = isset($pagePlans[0]) ? $this->materializeReaderPage($pagePlans[0], $dataset['text'] ?? null) : null;

        return [
            'version_id' => $version->id,
            'version_name' => $version->name,
            'version_folder' => $version->folder,
            'text_available' => $dataset['text_available'],
            'text_length' => $dataset['text_length'],
            'text_encoding' => $dataset['text_encoding'],
            'text_source' => $dataset['text_source'],
            'text_source_label' => $dataset['text_source_label'],
            'text_source_options' => $dataset['text_source_options'] ?? [],
            'facsimiles' => $dataset['facsimiles'],
            'pages' => $pageSummaries,
            'page_count' => count($pageSummaries),
            'current_page_index' => $currentPage ? 0 : null,
            'current_page' => $currentPage,
            'pagination' => $dataset['pagination'],
        ];
    }

    public function progressPayload(Version $version, ?string $requestedEncoding, ?string $requestedTextSource): array
    {
        $snapshot = Cache::get($this->readerProgressCacheKey($version->id, $requestedEncoding, $requestedTextSource));

        if (! is_array($snapshot)) {
            return [
                'version_id' => $version->id,
                'status' => 'idle',
                'percent' => 0,
                'label' => 'En attente du chargement du viewer.',
                'updated_at' => null,
            ];
        }

        return [
            'version_id' => $version->id,
            'status' => $snapshot['status'] ?? 'running',
            'percent' => max(0, min(100, (int) ($snapshot['percent'] ?? 0))),
            'label' => $snapshot['label'] ?? 'Chargement du viewer…',
            'updated_at' => $snapshot['updated_at'] ?? null,
        ];
    }

    public function pagePayload(Version $version, ?string $requestedEncoding, ?string $requestedTextSource, int $index): array
    {
        $dataset = $this->dataset($version, $requestedEncoding, $requestedTextSource);
        $pagePlans = is_array($dataset['page_plans'] ?? null)
            ? $dataset['page_plans']
            : $this->readerPagePlans($dataset['text'] ?? null, $dataset['markers'] ?? [], $dataset['facsimiles'] ?? []);
        $pagePlan = $pagePlans[$index] ?? null;

        if (! $pagePlan) {
            return [
                'status' => 'missing',
                'message' => 'Page du lecteur introuvable.',
                'index' => $index,
                'page_count' => count($pagePlans),
            ];
        }

        return [
            'version_id' => $version->id,
            'page_index' => $index,
            'page_count' => count($pagePlans),
            'text_encoding' => $dataset['text_encoding'],
            'text_source' => $dataset['text_source'],
            'page' => $this->materializeReaderPage($pagePlan, $dataset['text'] ?? null),
        ];
    }

    public function clearCache(Version|int $version): void
    {
        $this->clearReaderDatasetCache($version instanceof Version ? $version->id : $version);
    }

    private function readerFacsimiles(Version $version): array
    {
        return $this->collectReaderFacsimiles($version, false);
    }

    private function collectReaderFacsimiles(Version $version, bool $includeDimensions): array
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (! $authorFolder || ! $workFolder) {
            return [];
        }

        $cacheKey = null;
        if ($includeDimensions) {
            $cacheKey = $this->readerFacsimilesCacheKey($version);
            if ($cacheKey) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        $dirRel = "uploads/{$authorFolder}/{$workFolder}/{$version->folder}";
        $disk = Storage::disk('public');
        $legacyDir = base_path('../variance/'.$dirRel);
        $useLegacy = false;

        if ($disk->exists($dirRel)) {
            $all = collect($disk->files($dirRel))
                ->map(fn ($path) => [
                    'name' => basename($path),
                    'path' => $path,
                    'absolute' => $disk->path($path),
                ]);
        } elseif (File::isDirectory($legacyDir)) {
            $useLegacy = true;
            $all = collect(File::files($legacyDir))
                ->map(fn ($file) => [
                    'name' => $file->getFilename(),
                    'path' => $file->getFilename(),
                    'absolute' => $file->getPathname(),
                ]);
        } else {
            return [];
        }

        $facsimiles = $all
            ->filter(fn ($entry) => preg_match('/\.(jpe?g|png)$/i', $entry['name']) && ! str_contains($entry['name'], '_thumb'))
            ->map(function (array $entry) use ($disk, $dirRel, $legacyDir, $useLegacy, $includeDimensions) {
                $thumbName = preg_replace('/(\.\w+)$/', '_thumb$1', $entry['name']);
                $thumbPath = $useLegacy ? $legacyDir.'/'.$thumbName : $dirRel.'/'.$thumbName;
                $thumbExists = $useLegacy ? is_file($thumbPath) : $disk->exists($thumbPath);

                $width = null;
                $height = null;
                $sizeBytes = null;
                $sizeHuman = null;
                if ($includeDimensions && is_file($entry['absolute'])) {
                    $sizeBytes = filesize($entry['absolute']) ?: 0;
                    $sizeHuman = $this->humanReadableSize((int) $sizeBytes);
                    $info = @getimagesize($entry['absolute']);
                    if (is_array($info)) {
                        $width = $info[0] ?? null;
                        $height = $info[1] ?? null;
                    }
                }

                $bigUrl = $useLegacy
                    ? legacy_url($dirRel.'/'.$entry['name'])
                    : admin_url('storage/'.ltrim($entry['path'], '/'));
                $thumbUrl = null;
                if ($thumbExists) {
                    $thumbUrl = $useLegacy
                        ? legacy_url($dirRel.'/'.$thumbName)
                        : admin_url('storage/'.ltrim($thumbPath, '/'));
                }

                return [
                    'name' => $entry['name'],
                    'image_code' => $this->readerImageCode($entry['name']),
                    'big' => $bigUrl,
                    'thumb' => $thumbUrl,
                    'hasThumb' => $thumbExists,
                    'size_bytes' => $sizeBytes,
                    'size_human' => $sizeHuman,
                    'width' => $width,
                    'height' => $height,
                ];
            })
            ->sortBy(fn (array $entry) => $entry['image_code'] ?: $entry['name'], SORT_NATURAL)
            ->values()
            ->all();

        if ($includeDimensions && $cacheKey) {
            Cache::put($cacheKey, $facsimiles, now()->addMinutes(30));
        }

        return $facsimiles;
    }

    private function readerFacsimilesCacheKey(Version $version): ?string
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (! $authorFolder || ! $workFolder) {
            return null;
        }

        $dirRel = "uploads/{$authorFolder}/{$workFolder}/{$version->folder}";
        $disk = Storage::disk('public');
        $legacyDir = base_path('../variance/'.$dirRel);

        if ($disk->exists($dirRel)) {
            $files = $disk->files($dirRel);
            $fingerprint = array_map(function (string $path) use ($disk): array {
                return [
                    'name' => basename($path),
                    'mtime' => (int) @filemtime($disk->path($path)),
                    'size' => (int) $disk->size($path),
                ];
            }, $files);
        } elseif (File::isDirectory($legacyDir)) {
            $files = File::files($legacyDir);
            $fingerprint = array_map(function (\SplFileInfo $file): array {
                return [
                    'name' => $file->getFilename(),
                    'mtime' => (int) $file->getMTime(),
                    'size' => (int) $file->getSize(),
                ];
            }, $files);
        } else {
            return null;
        }

        return 'versions:reader-facsimiles:'.$version->id.':'.md5(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    public function dataset(Version $version, ?string $requestedEncoding, ?string $requestedTextSource): array
    {
        $requestedEncoding = $this->versionTextService->normalizeSourceEncodingHint($requestedEncoding);
        $version->loadMissing('work.author');
        $fingerprint = $this->readerDatasetFingerprint($version);
        $nonce = (int) Cache::get($this->readerDatasetNonceKey($version->id), 0);
        $cacheKey = $this->readerDatasetCacheKey($version->id, $requestedEncoding, $requestedTextSource, $fingerprint, $nonce);
        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 6, 'Vérification du cache du lecteur…');

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Lecteur prêt.', 'ready');

            return $cached;
        }

        try {
            $artifact = $this->loadReaderDatasetArtifact($version, $requestedEncoding, $requestedTextSource, $fingerprint);
            if (is_array($artifact)) {
                Cache::put($cacheKey, $artifact, now()->addMinutes(10));
                $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Lecteur prêt.', 'ready');

                return $artifact;
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $bundle = $this->buildReaderDatasetBundle($version, $requestedEncoding, $requestedTextSource);
            $dataset = $bundle['selected'];
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 84, 'Préparation des pages du lecteur…');
            $dataset['page_plans'] = $this->readerPagePlans($dataset['text'] ?? null, $dataset['markers'] ?? [], $dataset['facsimiles'] ?? []);
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 93, 'Mise en cache du lecteur…');
            $this->storeReaderDatasetArtifact($version, $requestedEncoding, $requestedTextSource, $fingerprint, $dataset);
            $this->warmReaderDatasetArtifacts(
                $version,
                $requestedEncoding,
                $fingerprint,
                $nonce,
                $bundle['variants'] ?? []
            );
            Cache::put($cacheKey, $dataset, now()->addMinutes(10));
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Lecteur prêt.', 'ready');

            return $dataset;
        } catch (\Throwable $e) {
            $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 100, 'Échec du chargement du lecteur.', 'error');
            throw $e;
        }
    }

    private function buildReaderDatasetBundle(Version $version, ?string $requestedEncoding, ?string $requestedTextSource): array
    {
        $version->loadMissing('work.author');

        $textPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        $textVariants = [];
        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 18, 'Lecture du texte de version…');
        if (is_file($textPath)) {
            try {
                $versionText = $this->versionTextService->readFileAsUtf8($textPath, $requestedEncoding);
                if ($versionText !== '') {
                    $textVariants['version-txt'] = [
                        'value' => 'version-txt',
                        'text' => $versionText,
                        'label' => 'TXT de version',
                        'origin' => null,
                        'markers' => [],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Could not load version text for reader.', [
                    'version_id' => $version->id,
                    'folder' => $version->folder,
                    'encoding' => $requestedEncoding,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 34, 'Reconstruction éventuelle depuis le XHTML…');
        $fallback = $this->readerTextFromComparisonXhtml($version);
        if (is_array($fallback) && is_string($fallback['text'] ?? null) && $fallback['text'] !== '') {
            $textVariants['comparison-xhtml'] = [
                'value' => 'comparison-xhtml',
                'text' => $fallback['text'],
                'label' => (string) ($fallback['label'] ?? 'XHTML de comparaison'),
                'origin' => (string) ($fallback['origin'] ?? 'pb-xhtml'),
                'markers' => is_array($fallback['markers'] ?? null) ? $fallback['markers'] : [],
            ];
        }

        $selectedTextSource = $requestedTextSource;
        if (! $selectedTextSource || ! array_key_exists($selectedTextSource, $textVariants)) {
            $selectedTextSource = array_key_exists('version-txt', $textVariants)
                ? 'version-txt'
                : (array_key_first($textVariants) ?: null);
        }

        $textSourceOptions = array_values(array_map(function (array $variant): array {
            return [
                'value' => (string) ($variant['value'] ?? ''),
                'label' => (string) ($variant['label'] ?? ''),
            ];
        }, $textVariants));

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 52, 'Chargement des repères de pagination…');
        $sidecar = $this->pageMarkerService->getPaginationSidecar($version->id);
        $sidecarMarkers = collect(is_array($sidecar['markers'] ?? null) ? $sidecar['markers'] : [])
            ->filter(fn ($marker) => is_array($marker))
            ->map(function (array $marker) {
                $imageInferred = (bool) ($marker['image_inferred'] ?? false);

                return [
                    'char_index' => max(0, (int) ($marker['char_index'] ?? 0)),
                    'image_code' => $marker['image_code'] ?? $marker['image'] ?? null,
                    'explicit_image_code' => $imageInferred ? null : ($marker['image_code'] ?? $marker['image'] ?? null),
                    'image_inferred' => $imageInferred,
                    'page' => $marker['page'] ?? null,
                    'line' => isset($marker['line']) && is_numeric($marker['line']) ? (int) $marker['line'] : null,
                    'phrase' => $marker['match'] ?? $marker['phrase'] ?? null,
                ];
            })
            ->sortBy('char_index')
            ->values()
            ->all();

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 68, 'Inventaire des fac-similés…');
        $facsimiles = $this->readerFacsimiles($version);
        $paginationInfo = $this->pageMarkerService->getPaginationInfo($version->id);

        $this->setReaderProgress($version->id, $requestedEncoding, $requestedTextSource, 76, 'Assemblage des sources du lecteur…');
        $variantDatasets = [];
        foreach ($textVariants as $sourceKey => $variant) {
            $variantDatasets[$sourceKey] = $this->assembleReaderDatasetPayload(
                is_string($variant['text'] ?? null) ? $variant['text'] : null,
                $requestedEncoding,
                $sourceKey,
                (string) ($variant['label'] ?? 'source texte non précisée'),
                $textSourceOptions,
                is_array($variant['markers'] ?? null) ? $variant['markers'] : [],
                $variant['origin'] ?? null,
                $sidecarMarkers,
                $sidecar['origin'] ?? null,
                $facsimiles,
                $paginationInfo
            );
        }

        if (array_key_exists($selectedTextSource, $variantDatasets)) {
            $selectedDataset = $variantDatasets[$selectedTextSource];
        }

        return [
            'selected' => $selectedDataset ?? $this->assembleReaderDatasetPayload(
                null,
                $requestedEncoding,
                null,
                null,
                $textSourceOptions,
                [],
                null,
                $sidecarMarkers,
                $sidecar['origin'] ?? null,
                $facsimiles,
                $paginationInfo
            ),
            'variants' => $variantDatasets,
        ];
    }

    private function assembleReaderDatasetPayload(
        ?string $text,
        ?string $requestedEncoding,
        ?string $selectedTextSource,
        ?string $textSourceLabel,
        array $textSourceOptions,
        array $variantMarkers,
        ?string $variantOrigin,
        array $sidecarMarkers,
        ?string $sidecarOrigin,
        array $facsimiles,
        ?array $paginationInfo
    ): array {
        $paginationOrigin = null;
        $markers = [];

        if (! empty($sidecarMarkers)) {
            $paginationOrigin = $sidecarOrigin;
            $markers = $sidecarMarkers;
        } elseif ($selectedTextSource === 'comparison-xhtml' && ! empty($variantMarkers)) {
            $paginationOrigin = $variantOrigin;
            $markers = $variantMarkers;
        }

        if (is_string($text) && $text !== '' && ! empty($markers)) {
            $markers = $this->pageMarkerService->resolveMarkersForPlainText($text, $markers);
        }

        return [
            'text' => is_string($text) ? $text : null,
            'text_available' => is_string($text),
            'text_length' => is_string($text) ? mb_strlen($text, 'UTF-8') : null,
            'text_encoding' => $requestedEncoding ?: 'AUTO',
            'text_source' => is_string($text) ? $selectedTextSource : null,
            'text_source_label' => $textSourceLabel,
            'text_source_options' => $textSourceOptions,
            'facsimiles' => $facsimiles,
            'markers' => $markers,
            'pagination' => [
                'available' => ! empty($markers),
                'origin' => $paginationOrigin,
                'marker_count' => count($markers),
                'updated_at' => $paginationInfo['updated_at'] ?? null,
            ],
        ];
    }

    private function warmReaderDatasetArtifacts(
        Version $version,
        ?string $requestedEncoding,
        array $fingerprint,
        int $nonce,
        array $variants
    ): void {
        foreach ($variants as $source => $dataset) {
            if (! is_array($dataset)) {
                continue;
            }

            if (! isset($dataset['page_plans'])) {
                $dataset['page_plans'] = $this->readerPagePlans(
                    $dataset['text'] ?? null,
                    $dataset['markers'] ?? [],
                    $dataset['facsimiles'] ?? []
                );
            }

            $this->storeReaderDatasetArtifact($version, $requestedEncoding, $source, $fingerprint, $dataset);

            Cache::put(
                $this->readerDatasetCacheKey($version->id, $requestedEncoding, $source, $fingerprint, $nonce),
                $dataset,
                now()->addMinutes(10)
            );
        }
    }

    private function readerProgressCacheKey(int $versionId, ?string $requestedEncoding, ?string $requestedTextSource): string
    {
        $encoding = $requestedEncoding ?: 'AUTO';
        $source = $requestedTextSource ?: 'AUTO';

        return 'versions:reader-progress:'.$versionId.':'.md5($encoding).':'.md5($source);
    }

    private function setReaderProgress(
        int $versionId,
        ?string $requestedEncoding,
        ?string $requestedTextSource,
        int $percent,
        string $label,
        string $status = 'running'
    ): void {
        Cache::put(
            $this->readerProgressCacheKey($versionId, $requestedEncoding, $requestedTextSource),
            [
                'status' => $status,
                'percent' => max(0, min(100, $percent)),
                'label' => $label,
                'updated_at' => now()->toIso8601String(),
            ],
            now()->addMinutes(5)
        );
    }

    public function normalizeReaderTextSourceHint(?string $hint): ?string
    {
        $value = strtolower(trim((string) $hint));

        return in_array($value, ['version-txt', 'comparison-xhtml'], true) ? $value : null;
    }

    private function readerDatasetCacheKey(int $versionId, ?string $requestedEncoding, ?string $requestedTextSource, array $fingerprint = [], int $nonce = 0): string
    {
        $encoding = $requestedEncoding ?: 'AUTO';
        $source = $requestedTextSource ?: 'AUTO';
        $fingerprintHash = md5(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');

        return "versions:reader-dataset:{$versionId}:{$nonce}:".md5($encoding).':'.md5($source).":{$fingerprintHash}";
    }

    private function readerDatasetNonceKey(int $versionId): string
    {
        return "versions:reader-dataset:nonce:{$versionId}";
    }

    private function clearReaderDatasetCache(int $versionId): void
    {
        $this->removeReaderDatasetArtifacts($versionId);
        Cache::increment($this->readerDatasetNonceKey($versionId));
    }

    private function readerDatasetArtifactRelativePath(int $versionId, ?string $requestedEncoding, ?string $requestedTextSource): string
    {
        $encoding = $requestedEncoding ?: 'AUTO';
        $source = $requestedTextSource ?: 'AUTO';

        return 'reader_cache/'.$versionId.'/'.md5($encoding).'-'.md5($source).'.json';
    }

    private function loadReaderDatasetArtifact(Version $version, ?string $requestedEncoding, ?string $requestedTextSource, array $fingerprint): ?array
    {
        $relative = $this->readerDatasetArtifactRelativePath($version->id, $requestedEncoding, $requestedTextSource);
        $disk = Storage::disk('local');
        if (! $disk->exists($relative)) {
            return null;
        }

        try {
            $decoded = json_decode((string) $disk->get($relative), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('Could not decode persisted reader dataset artifact.', [
                'version_id' => $version->id,
                'path' => $relative,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $storedFingerprint = is_array($decoded['fingerprint'] ?? null) ? $decoded['fingerprint'] : null;
        $dataset = is_array($decoded['dataset'] ?? null) ? $decoded['dataset'] : null;
        if (! $storedFingerprint || ! $dataset) {
            return null;
        }

        if (($decoded['version_id'] ?? null) !== $version->id || $storedFingerprint !== $fingerprint) {
            return null;
        }

        return $dataset;
    }

    private function storeReaderDatasetArtifact(Version $version, ?string $requestedEncoding, ?string $requestedTextSource, array $fingerprint, array $dataset): void
    {
        $relative = $this->readerDatasetArtifactRelativePath($version->id, $requestedEncoding, $requestedTextSource);
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($relative));
        $payload = [
            'version_id' => $version->id,
            'encoding' => $requestedEncoding ?: 'AUTO',
            'text_source' => $requestedTextSource ?: 'AUTO',
            'generated_at' => time(),
            'fingerprint' => $fingerprint,
            'dataset' => $dataset,
        ];

        $disk->put($relative, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function removeReaderDatasetArtifacts(int $versionId): void
    {
        $dir = 'reader_cache/'.$versionId;
        $disk = Storage::disk('local');
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
    }

    private function readerDatasetFingerprint(Version $version): array
    {
        $textPath = storage_path("app/public/uploads/versions/{$version->folder}.txt");
        $sidecarRelative = $this->pageMarkerService->paginationRelativePath($version->id);
        $sidecarPath = Storage::disk('local')->exists($sidecarRelative)
            ? Storage::disk('local')->path($sidecarRelative)
            : null;
        $fallbackCandidate = $this->readerFirstComparisonXhtmlCandidate($version);
        $facsimiles = $this->readerFacsimiles($version);

        return [
            'schema_version' => self::READER_DATASET_SCHEMA_VERSION,
            'text' => [
                'path' => is_file($textPath) ? $textPath : null,
                'mtime' => is_file($textPath) ? ((int) @filemtime($textPath)) : null,
                'size' => is_file($textPath) ? ((int) @filesize($textPath)) : null,
            ],
            'sidecar' => [
                'path' => $sidecarPath,
                'mtime' => $sidecarPath && is_file($sidecarPath) ? ((int) @filemtime($sidecarPath)) : null,
                'size' => $sidecarPath && is_file($sidecarPath) ? ((int) @filesize($sidecarPath)) : null,
            ],
            'fallback' => [
                'comparison_id' => $fallbackCandidate['comparison_id'] ?? null,
                'role' => $fallbackCandidate['role'] ?? null,
                'path' => $fallbackCandidate['path'] ?? null,
                'mtime' => isset($fallbackCandidate['path']) && is_file($fallbackCandidate['path']) ? ((int) @filemtime($fallbackCandidate['path'])) : null,
                'size' => isset($fallbackCandidate['path']) && is_file($fallbackCandidate['path']) ? ((int) @filesize($fallbackCandidate['path'])) : null,
            ],
            'facsimiles' => [
                'count' => count($facsimiles),
                'names' => array_values(array_map(
                    static fn (array $entry) => (string) ($entry['name'] ?? ''),
                    $facsimiles
                )),
            ],
        ];
    }

    private function readerTextFromComparisonXhtml(Version $version): ?array
    {
        foreach ($this->readerComparisonXhtmlCandidates($version) as $candidate) {
            $path = $candidate['path'];
            $fileName = $candidate['file_name'];
            $comparisonId = $candidate['comparison_id'];
            $role = $candidate['role'];

            try {
                $contents = File::get($path);
                $text = $this->extractReaderTextFromComparisonXhtml($path);
                if ($text === '') {
                    continue;
                }

                return [
                    'text' => $text,
                    'source' => 'comparison-xhtml',
                    'label' => "Texte reconstruit depuis {$fileName} (#{$comparisonId})",
                    'origin' => 'pb-xhtml',
                    'markers' => $this->pageMarkerService->extractRuntimeMarkersFromComparisonHtml($contents),
                ];
            } catch (\Throwable $e) {
                Log::warning('Could not reconstruct reader text from comparison XHTML.', [
                    'version_id' => $version->id,
                    'comparison_id' => $comparisonId,
                    'role' => $role,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function readerFirstComparisonXhtmlCandidate(Version $version): ?array
    {
        $candidates = $this->readerComparisonXhtmlCandidates($version);

        return $candidates[0] ?? null;
    }

    private function readerComparisonXhtmlCandidates(Version $version): array
    {
        $version->loadMissing('work.author');

        $authorFolder = $version->work?->author?->folder;
        $workFolder = $version->work?->folder;
        if (! $authorFolder || ! $workFolder) {
            return [];
        }

        $comparisons = Comparison::query()
            ->where(function ($query) use ($version) {
                $query->where('source_id', $version->id)
                    ->orWhere('target_id', $version->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'source_id', 'target_id', 'folder']);

        $candidates = [];
        foreach ($comparisons as $comparison) {
            $role = (int) $comparison->source_id === (int) $version->id ? 'source' : 'target';
            $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
            $candidatePaths = [
                storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}/{$fileName}"),
                storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}/{$fileName}"),
                base_path("../variance/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}/{$fileName}"),
                base_path("../variance/uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}/{$fileName}"),
            ];

            foreach ($candidatePaths as $path) {
                if (! is_file($path)) {
                    continue;
                }

                $candidates[] = [
                    'comparison_id' => (int) $comparison->id,
                    'role' => $role,
                    'file_name' => $fileName,
                    'path' => $path,
                ];
                break;
            }
        }

        return $candidates;
    }

    private function extractReaderTextFromComparisonXhtml(string $path): string
    {
        $contents = File::get($path);
        if ($contents === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
        $normalized = preg_replace('~<span\b[^>]*class="page-marker"[^>]*>.*?</span>~is', '', $normalized) ?? $normalized;
        $normalized = preg_replace('~<pb\b[^>]*/?>~i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('~<br\s*/?>~i', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('~</(p|div|li|section|article|h[1-6]|tr)>~i', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $normalized) ?? $normalized;
        $normalized = strip_tags($normalized);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
        $normalized = preg_replace("/[ \t]+\n/", "\n", $normalized) ?? $normalized;
        $normalized = preg_replace("/\n[ \t]+/", "\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function readerPagePlans(?string $text, array $markers, array $facsimiles): array
    {
        $text = is_string($text) ? $text : '';
        if ($text === '') {
            return [];
        }

        if (empty($markers)) {
            $firstFacsimile = $facsimiles[0] ?? null;
            $firstImageCode = $this->readerImageCode($firstFacsimile['image_code'] ?? $firstFacsimile['name'] ?? null);

            return [[
                'label' => 'Texte complet',
                'image' => $firstFacsimile,
                'start' => 0,
                'end' => mb_strlen($text, 'UTF-8'),
                'line' => null,
                'imageCode' => $firstImageCode,
                'anchorOffset' => null,
                'anchorPhrase' => null,
                'guessed' => false,
            ]];
        }

        $textLength = mb_strlen($text, 'UTF-8');
        $imagesByCode = [];
        foreach ($facsimiles as $facsimile) {
            $code = $this->readerImageCode($facsimile['image_code'] ?? $facsimile['name'] ?? null);
            if ($code && ! array_key_exists($code, $imagesByCode)) {
                $imagesByCode[$code] = $facsimile;
            }
        }

        $normalizedMarkers = array_values(array_map(function (array $marker) {
            $marker['resolved_char_index'] = max(0, (int) ($marker['resolved_char_index'] ?? $marker['char_index'] ?? 0));
            $imageInferred = (bool) ($marker['image_inferred'] ?? false);
            $explicitSource = $marker['explicit_image_code']
                ?? ($imageInferred ? null : ($marker['image_code'] ?? $marker['image'] ?? null));
            $explicitImageCode = $this->readerImageCode($explicitSource);
            $marker['explicit_image_code'] = $explicitImageCode;
            $marker['image_code'] = $explicitImageCode ?? $this->readerImageCode($marker['page'] ?? null);
            $marker['page_label'] = trim((string) ($marker['page'] ?? ''));

            return $marker;
        }, $markers));

        usort($normalizedMarkers, static fn (array $a, array $b) => ((int) ($a['resolved_char_index'] ?? 0)) <=> ((int) ($b['resolved_char_index'] ?? 0)));

        $allSequentialLabels = ! empty($normalizedMarkers)
            && collect($normalizedMarkers)->every(static fn (array $marker) => preg_match('/^\d+(?:[a-z]+)?$/i', (string) ($marker['page_label'] ?? '')) === 1);
        $exactExplicitMarkerMatches = collect($normalizedMarkers)
            ->filter(static fn (array $marker) => ! empty($marker['explicit_image_code']) && array_key_exists((string) $marker['explicit_image_code'], $imagesByCode))
            ->count();
        $useTrailingSequentialAlignment = count($facsimiles) >= count($normalizedMarkers)
            && $allSequentialLabels
            && $exactExplicitMarkerMatches === 0;
        $trailingImageOffset = $useTrailingSequentialAlignment
            ? max(0, count($facsimiles) - count($normalizedMarkers))
            : 0;

        $pages = [];
        $firstMarker = $normalizedMarkers[0] ?? null;
        if ($firstMarker && ! $useTrailingSequentialAlignment) {
            $firstStart = min(max(0, (int) ($firstMarker['resolved_char_index'] ?? 0)), $textLength);
            $firstCode = $this->readerImageCode($firstMarker['image_code'] ?? null);
            if ($firstStart > 0 && $firstCode) {
                $leadingImage = null;
                foreach ($imagesByCode as $code => $image) {
                    if ($code < $firstCode) {
                        $leadingImage = $image;
                    }
                }

                if ($leadingImage) {
                    if ($firstStart > 0) {
                        $pages[] = [
                            'label' => 'Avant '.(($firstMarker['page'] ?? $firstCode) ?: 'le premier repère'),
                            'image' => $leadingImage,
                            'start' => 0,
                            'end' => $firstStart,
                            'line' => null,
                            'imageCode' => $this->readerImageCode($leadingImage['image_code'] ?? $leadingImage['name'] ?? null),
                            'anchorOffset' => null,
                            'anchorPhrase' => null,
                        ];
                    }
                }
            }
        }

        $count = count($normalizedMarkers);
        for ($index = 0; $index < $count; $index++) {
            $marker = $normalizedMarkers[$index];
            $start = min(max(0, (int) ($marker['resolved_char_index'] ?? 0)), $textLength);
            $nextMarker = $normalizedMarkers[$index + 1] ?? null;
            $end = $nextMarker
                ? min(max($start, (int) ($nextMarker['resolved_char_index'] ?? 0)), $textLength)
                : $textLength;

            if ($useTrailingSequentialAlignment) {
                $image = $facsimiles[$trailingImageOffset + $index] ?? null;
                $imageCode = $this->readerImageCode($image['image_code'] ?? $image['name'] ?? null);
            } else {
                $imageCode = $this->readerImageCode($marker['image_code'] ?? null);
                $image = $imageCode && array_key_exists($imageCode, $imagesByCode)
                    ? $imagesByCode[$imageCode]
                    : null;
            }
            $label = trim((string) ($marker['page'] ?? '')) ?: ($imageCode ? 'p. '.$imageCode : 'Repère '.($index + 1));
            $excerptStart = $start;
            $excerptEnd = $end;
            $anchorPhrase = trim((string) ($marker['phrase'] ?? ''));

            $pages[] = [
                'label' => $label,
                'image' => $image,
                'start' => $excerptStart,
                'end' => $excerptEnd,
                'line' => isset($marker['line']) ? (int) $marker['line'] : null,
                'imageCode' => $imageCode,
                'anchorOffset' => max(0, $start - $excerptStart),
                'anchorPhrase' => $anchorPhrase !== '' ? $anchorPhrase : null,
            ];
        }

        return $pages;
    }

    private function materializeReaderPage(array $pagePlan, ?string $text): array
    {
        $page = $pagePlan;
        $sourceText = is_string($text) ? $text : '';
        if ($sourceText === '') {
            $page['text'] = '';

            return $page;
        }

        $start = max(0, (int) ($pagePlan['start'] ?? 0));
        $end = max($start, (int) ($pagePlan['end'] ?? $start));
        $segment = mb_substr($sourceText, $start, max(0, $end - $start), 'UTF-8');
        if (($pagePlan['guessed'] ?? false) === true) {
            $trimmed = trim($segment);
            $page['text'] = $trimmed !== '' ? $trimmed : $segment;

            return $page;
        }

        $page['text'] = $segment;

        return $page;
    }

    private function readerImageCode(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/_(\d+)(?:_thumb)?\.(?:jpe?g|png)$/i', $raw, $m)) {
            return str_pad((string) ((int) $m[1]), 3, '0', STR_PAD_LEFT);
        }

        if (preg_match('/(\d+)/', $raw, $m)) {
            return str_pad((string) ((int) $m[1]), 3, '0', STR_PAD_LEFT);
        }

        return null;
    }

    private function humanReadableSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 o';
        }

        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1, '.', ' ').' '.$units[$index];
    }
}
