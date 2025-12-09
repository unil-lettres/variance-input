<?php

namespace App\Services;

use App\Exceptions\PaginationCancelledException;
use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PageMarkerService
{
    private const MARKER_TEMPLATE = '<span class="page-marker" data-image-name="%s"><span class="page-number">%s</span><img src="%s" /></span>';
    private const ORIGINAL_SOURCE = 'source.original.xhtml';
    private const ORIGINAL_TARGET = 'target.original.xhtml';
    private ?string $progressPath = null;
    private array $progress = [];
    private ?int $progressVersionId = null;

    /**
     * Apply a _lignes file to every comparison that involves the given version.
     *
     * @param  Version $version
     * @param  string  $lignesPath Absolute path to the _lignes file
     * @param  array   $options    Supported: clear_existing(bool)
     * @return array  Summary of the operation per role (source/target)
     */
    public function applyLignesToVersion(Version $version, string $lignesPath, array $options = []): array
    {
        $entries = $this->parseLignesFile($lignesPath);
        if (empty($entries)) {
            throw new \RuntimeException('Le fichier _lignes ne contient aucune pagination exploitable.');
        }

        $clear = Arr::get($options, 'clear_existing', true);
        $replaceExisting = Arr::get($options, 'replace_existing', true);

        $version->loadMissing('work.author');
        if (!$version->work || !$version->work->author) {
            throw new \RuntimeException('Version incomplète : dossier auteur/œuvre introuvable.');
        }

        $this->initProgress($version->id, count($entries), 1);

        if ($this->isCancelled($version->id)) {
            throw new PaginationCancelledException('Annulé par l\'utilisateur');
        }

        $result = $this->applyToVersionDocument(
            $entries,
            $version,
            $clear,
            $replaceExisting
        );

        $summary = [
            'total'  => $result['inserted'],
            'source' => [
                'comparisons' => 1,
                'processed'   => $result['processed'],
                'inserted'    => $result['inserted'],
                'missed'      => count($result['misses']),
                'skipped'     => 0,
                'details'     => [[
                    'status'   => 'ok',
                    'comparison_id' => $version->id,
                    'paths'    => $result['paths'],
                    'inserted' => $result['inserted'],
                    'misses'   => $result['misses'],
                ]],
            ],
            'target' => ['comparisons' => 0, 'processed' => 0, 'inserted' => 0, 'missed' => 0, 'skipped' => 0, 'details' => []],
        ];

        $this->finishProgress($summary);
        return $summary;
    }

    /**
     * Generate (or regenerate) the pagination sidecar JSON for the given version.
     *
     * @return array{relative_path:string,payload:array,summary:array,misses:array}
     */
    public function generatePaginationSidecar(Version $version, string $lignesAbsolutePath): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $entries = $this->parseLignesFile($lignesAbsolutePath);
        if (empty($entries)) {
            throw new \RuntimeException('Le fichier _lignes ne contient aucune pagination exploitable.');
        }

        $version->loadMissing('work.author');
        if (!$version->work || !$version->work->author) {
            throw new \RuntimeException('Version incomplète : dossier auteur/œuvre introuvable.');
        }

        $teiPath = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        if (!is_file($teiPath)) {
            throw new \RuntimeException("Fichier version introuvable : {$teiPath}");
        }

        $teiContents = file_get_contents($teiPath);
        if ($teiContents === false) {
            throw new \RuntimeException("Impossible de lire {$teiPath}");
        }

        $this->markQueued($version->id);
        $this->initProgress($version->id, count($entries), 1);

        $htmlForMatching = $this->clearExistingMarkers($teiContents);

        $result = $this->insertMarkers(
            $htmlForMatching,
            $entries,
            'left',
            function (string $event, array $data = []) {
                $this->progressTick('source', $event, $data);
            },
            true,
            true,
            true
        );

        $markerCount = count($result['positions']);
        $missCount   = count($result['misses']);

        $summary = [
            'total'  => $markerCount,
            'source' => [
                'comparisons' => 1,
                'processed'   => count($entries),
                'inserted'    => $markerCount,
                'missed'      => $missCount,
                'skipped'     => 0,
                'details'     => [[
                    'status'        => 'ok',
                    'comparison_id' => $version->id,
                    'paths'         => [$this->paginationRelativePath($version->id)],
                    'inserted'      => $markerCount,
                    'misses'        => $result['misses'],
                ]],
            ],
            'target' => [
                'comparisons' => 0,
                'processed'   => 0,
                'inserted'    => 0,
                'missed'      => 0,
                'skipped'     => 0,
                'details'     => [],
            ],
        ];

        $this->finishProgress($summary);

        $shadowText = (string) ($result['shadow'] ?? '');
        $payloadMarkers = [];
        foreach ($result['positions'] as $position) {
            $entry = $position['entry'] ?? [];
            $charIndex = (int) ($position['char_index'] ?? 0);
            $payloadMarkers[] = [
                'char_index' => $charIndex,
                'match'      => $position['matched'] ?? '',
                'context'    => $this->extractContextSnippet($shadowText, $charIndex),
                'image'      => $entry['image'] ?? null,
                'image_code' => $position['image_code'] ?? null,
                'page'       => $entry['page'] ?? null,
                'phrase'     => $entry['phrase'] ?? null,
                'line'       => $entry['line'] ?? null,
            ];
        }

        $relative = $this->paginationRelativePath($version->id);
        Storage::disk('local')->makeDirectory('private/pagination');

        $payload = [
            'version_id'     => $version->id,
            'version_folder' => $version->folder,
            'work_id'        => $version->work_id,
            'generated_at'   => time(),
            'text_length'    => mb_strlen($shadowText, 'UTF-8'),
            'marker_count'   => $markerCount,
            'missed_count'   => $missCount,
            'markers'        => $payloadMarkers,
            'misses'         => $result['misses'],
            'source_sha1'    => sha1($htmlForMatching),
        ];

        Storage::disk('local')->put(
            $relative,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return [
            'relative_path' => $relative,
            'payload'       => $payload,
            'summary'       => $summary,
            'misses'        => $result['misses'],
        ];
    }

    /**
     * Inject pagination markers from the generated sidecar into a comparison's
     * source/target XHTML files.
     */
    public function applySidecarToComparison(
        Comparison $comparison,
        bool $clearExisting,
        bool $replaceExisting
    ): array {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $this->ensureOriginalBackups($comparison);

        $results = [
            'source' => null,
            'target' => null,
        ];

        $comparisonId = $comparison->id;

        $roleContexts = [];

        foreach (['source', 'target'] as $role) {
            $version = $role === 'source'
                ? $comparison->sourceVersion
                : $comparison->targetVersion;

            if (!$version) {
                $roleContexts[$role] = [
                    'status' => 'skipped',
                    'reason' => 'Version absente',
                    'markers' => [],
                ];
                continue;
            }

            $sidecar = $this->loadPaginationSidecar($version->id);
            if (!$sidecar) {
                $roleContexts[$role] = [
                    'status' => 'skipped',
                    'reason' => 'Aucun sidecar pagination disponible',
                    'markers' => [],
                ];
                continue;
            }

            $markers = $sidecar['markers'] ?? [];
            $roleContexts[$role] = [
                'status'  => 'pending',
                'version' => $version,
                'markers' => $markers,
            ];
        }

        $initialRoles = [];
        foreach ($roleContexts as $role => $context) {
            $initialRoles[$role] = [
                'status'   => $context['status'] === 'pending' ? 'queued' : $context['status'],
                'total'    => count($context['markers'] ?? []),
                'inserted' => 0,
                'missed'   => 0,
            ];
            if (!empty($context['reason'])) {
                $initialRoles[$role]['reason'] = $context['reason'];
            }
        }

        $this->writeComparisonProgress($comparisonId, [
            'status'        => 'running',
            'comparison_id' => $comparisonId,
            'roles'         => $initialRoles,
            'updated_at'    => time(),
        ]);

        foreach ($roleContexts as $role => $context) {
            if ($context['status'] !== 'pending') {
                $results[$role] = [
                    'status' => $context['status'],
                    'reason' => $context['reason'] ?? null,
                    'inserted' => 0,
                    'misses'   => [],
                    'paths'    => [],
                    'total'    => 0,
                ];
                $this->updateComparisonRoleProgress($comparisonId, $role, [
                    'status' => $context['status'],
                    'reason' => $context['reason'] ?? null,
                ]);
                continue;
            }

            $version = $context['version'];
            $markers = $context['markers'];
            $totalMarkers = count($markers);

            $this->updateComparisonRoleProgress($comparisonId, $role, [
                'status' => 'running',
                'total'  => $totalMarkers,
            ]);

            try {
                $result = $this->applySidecarToComparisonRole(
                    $comparison,
                    $version,
                    $role,
                    $markers,
                    $clearExisting,
                    $replaceExisting
                );
            } catch (\Throwable $e) {
                $this->updateComparisonRoleProgress($comparisonId, $role, [
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->updateComparisonRoleProgress($comparisonId, $role, [
                'status'   => $result['status'],
                'inserted' => $result['inserted'],
                'missed'   => count($result['misses']),
                'total'    => $totalMarkers,
                'paths'    => $result['paths'] ?? [],
            ]);

            $results[$role] = $result + [
                'total' => $totalMarkers,
            ];
        }

        $current = $this->getComparisonProgressSnapshot($comparisonId);
        $finalRoles = $current['roles'] ?? [];

        $this->finishComparisonProgress($comparisonId, [
            'status'        => 'done',
            'roles'         => $finalRoles,
            'summary'       => $results,
            'updated_at'    => time(),
        ]);

        return $results;
    }

    public function applySidecarToComparisonRoleOnly(
        Comparison $comparison,
        string $role,
        bool $clearExisting,
        bool $replaceExisting
    ): array {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');
        $this->ensureOriginalBackups($comparison);

        $role = strtolower($role) === 'target' ? 'target' : 'source';

        $version = $role === 'source'
            ? $comparison->sourceVersion
            : $comparison->targetVersion;

        if (!$version) {
            $result = [
                'status' => 'skipped',
                'reason' => 'Version absente',
                'inserted' => 0,
                'misses'   => [],
                'paths'    => [],
                'total'    => 0,
            ];
            $this->finishComparisonProgress($comparison->id, [
                'status' => 'done',
                'roles'  => [
                    $role => [
                        'status'   => $result['status'],
                        'reason'   => $result['reason'],
                        'total'    => 0,
                        'inserted' => 0,
                        'missed'   => 0,
                    ],
                ],
                'summary' => [$role => $result],
                'updated_at' => time(),
            ]);
            return $result;
        }

        $sidecar = $this->loadPaginationSidecar($version->id);
        if (!$sidecar) {
            throw new untimeException('Aucun sidecar pagination disponible pour cette version.');
        }

        $markers = $sidecar['markers'] ?? [];
        $totalMarkers = count($markers);

        $this->writeComparisonProgress($comparison->id, [
            'status'        => 'running',
            'comparison_id' => $comparison->id,
            'roles'         => [
                $role => [
                    'status'   => 'queued',
                    'total'    => $totalMarkers,
                    'inserted' => 0,
                    'missed'   => 0,
                ],
            ],
            'updated_at'    => time(),
        ]);

        $this->updateComparisonRoleProgress($comparison->id, $role, [
            'status' => 'running',
            'total'  => $totalMarkers,
        ]);

        $result = $this->applySidecarToComparisonRole(
            $comparison,
            $version,
            $role,
            $markers,
            $clearExisting,
            $replaceExisting
        );

        $this->updateComparisonRoleProgress($comparison->id, $role, [
            'status'   => $result['status'],
            'inserted' => $result['inserted'],
            'missed'   => count($result['misses']),
            'total'    => $totalMarkers,
            'paths'    => $result['paths'] ?? [],
        ]);

        $this->finishComparisonProgress($comparison->id, [
            'status'        => 'done',
            'roles'         => [
                $role => [
                    'status'   => $result['status'],
                    'total'    => $totalMarkers,
                    'inserted' => $result['inserted'],
                    'missed'   => count($result['misses']),
                ],
            ],
            'summary'       => [$role => $result + ['total' => $totalMarkers]],
            'updated_at'    => time(),
        ]);

        return $result + ['total' => $totalMarkers];
    }

    public function loadPaginationSidecar(int $versionId): ?array
    {
        $relative = $this->paginationRelativePath($versionId);
        if (!Storage::disk('local')->exists($relative)) {
            return null;
        }

        try {
            $contents = Storage::disk('local')->get($relative);
            return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('Impossible de décoder le sidecar pagination', [
                'version_id' => $versionId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function applySidecarToComparisonRole(
        Comparison $comparison,
        Version $version,
        string $role,
        array $markers,
        bool $clearExisting,
        bool $replaceExisting
    ): array {
        $version->loadMissing('work.author');
        $work = $version->work;
        $author = $work?->author;

        if (!$work || !$author) {
            throw new \RuntimeException('Version incomplète : dossier auteur/œuvre introuvable.');
        }

        $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
        $paths    = $this->candidatePaths($author->folder, $work->folder, $comparison, $fileName);
        $existing = array_values(array_filter($paths, static fn ($path) => is_file($path)));

        if (empty($existing)) {
            return [
                'status'   => 'skipped',
                'reason'   => 'Aucun fichier XHTML trouvé pour la comparaison',
                'paths'    => $paths,
                'inserted' => 0,
                'misses'   => [],
            ];
        }

        $html = file_get_contents($existing[0]);
        if ($html === false) {
            throw new \RuntimeException("Impossible de lire {$existing[0]}");
        }

        if ($clearExisting) {
            $html = $this->clearExistingMarkers($html);
        }

        $result = $this->insertMarkersFromOffsets(
            $html,
            $markers,
            $role === 'source' ? 'right' : 'left',
            $clearExisting,
            $replaceExisting
        );

        foreach ($existing as $path) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $result['html']);
        }

        return [
            'status'        => 'ok',
            'paths'         => $existing,
            'inserted'      => $result['inserted'],
            'misses'        => $result['misses'],
            'total'         => count($markers),
        ];
    }

    /** Count page markers currently present for a version across all comparisons. */
    public function countMarkers(Version $version): array
    {
        $teiMarkers = $this->countMarkersInVersion($version);

        return [
            'total'  => $teiMarkers,
            'source' => ['comparisons' => 1, 'markers' => $teiMarkers],
            'target' => ['comparisons' => 0, 'markers' => 0],
        ];
    }

    /**
     * Ensure a fallback page marker exists for both source and target XHTML
     * files of the given comparison. Returns true if at least one file was
     * modified.
     */
    public function ensureDefaultMarkers(Comparison $comparison): bool
    {
        $comparison->loadMissing(
            'sourceVersion.work.author',
            'targetVersion.work.author'
        );

        $updated = false;

        $roles = [
            'source' => $comparison->sourceVersion,
            'target' => $comparison->targetVersion,
        ];

        foreach ($roles as $role => $version) {
            if (!$version) {
                continue;
            }

            $work    = $version->work;
            $author  = $work?->author;
            $authorFolder = $author?->folder;
            $workFolder   = $work?->folder;
            $versionFolder = $version->folder;

            if (!$authorFolder || !$workFolder || !$versionFolder) {
                continue;
            }

            $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
            $paths    = $this->candidatePaths($authorFolder, $workFolder, $comparison, $fileName);
            $existing = array_values(array_filter($paths, 'is_file'));
            if (empty($existing)) {
                continue;
            }

            $html = file_get_contents($existing[0]);
            if ($html === false || stripos($html, 'page-marker') !== false) {
                continue;
            }

            $imageNumbers = $this->collectImageNumbers($authorFolder, $workFolder, $versionFolder);
            if (empty($imageNumbers)) {
                continue;
            }

            $first = min($imageNumbers);
            $orient = $role === 'source' ? 'right' : 'left';
            $padded = str_pad((string) $first, 3, '0', STR_PAD_LEFT);
            $marker = sprintf(
                '<span class="page-marker" data-image-name="%1$s"><span class="page-number">%1$s</span><img src="/img/settings/page_%2$s.svg" /></span>',
                $padded,
                $orient
            );

            $newHtml = $marker . "\n" . ltrim($html, "\xEF\xBB\xBF\r\n\t ");

            foreach ($existing as $path) {
                File::put($path, $newHtml);
            }

            $updated = true;
        }

        return $updated;
    }

    /* ───────────────────────── INTERNALS ───────────────────────── */

    /** @return array<int> */
    private function collectImageNumbers(string $authorFolder, string $workFolder, string $versionFolder): array
    {
        $directories = $this->candidateImageDirectories($authorFolder, $workFolder, $versionFolder);
        $numbers = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $glob = glob($dir . '/img_*_*.*');
            if (!$glob) {
                continue;
            }

            foreach ($glob as $file) {
                $base = basename($file);
                if (preg_match('/_(\d+)\.(?:jpe?g|png)$/i', $base, $match)) {
                    $numbers[] = (int) $match[1];
                }
            }

            if (!empty($numbers)) {
                break; // prefer first directory that yields results
            }
        }

        $numbers = array_values(array_unique($numbers));
        sort($numbers, SORT_NUMERIC);
        return $numbers;
    }

    /**
     * @return array<int, string> Candidate absolute directories where facsimile
     *         images for a version may be stored.
     */
    private function candidateImageDirectories(string $authorFolder, string $workFolder, string $versionFolder): array
    {
        $relative = "uploads/{$authorFolder}/{$workFolder}/{$versionFolder}";

        $dirs = [
            storage_path("app/public/{$relative}"),
            base_path("../variance/{$relative}"),
        ];

        $lower = strtolower($versionFolder);
        if ($lower !== $versionFolder) {
            $relativeLower = "uploads/{$authorFolder}/{$workFolder}/{$lower}";
            $dirs[] = storage_path("app/public/{$relativeLower}");
            $dirs[] = base_path("../variance/{$relativeLower}");
        }

        return array_values(array_unique($dirs));
    }

    private function mergeDetail(array &$bucket, array $result): void
    {
        $bucket['details'][] = $result;
        if ($result['status'] === 'skipped') {
            $bucket['skipped']++;
            return;
        }

        $bucket['processed']++;
        $bucket['inserted'] += $result['inserted'];
        $bucket['missed']   += count($result['misses']);
    }

    private function applyToVersionDocument(array $entries, Version $version, bool $clearExisting, bool $replaceExisting): array
    {
        $teiPath = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        if (!is_file($teiPath)) {
            throw new \RuntimeException("Fichier version introuvable : {$teiPath}");
        }

        $this->progressTick('source', 'prepare', ['total' => count($entries)]);

        $html = file_get_contents($teiPath);
        if ($html === false) {
            throw new \RuntimeException("Impossible de lire {$teiPath}");
        }

        if ($clearExisting) {
            $html = $this->clearExistingMarkers($html);
        }

        $result = $this->insertMarkers(
            $html,
            $entries,
            'left',
            function (string $event, array $data = []) {
                $this->progressTick('source', $event, $data);
            },
            $replaceExisting,
            $clearExisting
        );

        File::ensureDirectoryExists(dirname($teiPath));
        File::put($teiPath, $result['html']);

        $mirrors = [
            public_path("uploads/versions/{$version->folder}.xml"),
            base_path("../variance/uploads/versions/{$version->folder}.xml"),
        ];

        foreach ($mirrors as $mirror) {
            if ($mirror === null) {
                continue;
            }
            File::ensureDirectoryExists(dirname($mirror));
            @file_put_contents($mirror, $result['html']);
        }

        return [
            'status'    => 'ok',
            'paths'     => [$teiPath],
            'inserted'  => $result['inserted'],
            'misses'    => $result['misses'],
            'processed' => count($entries),
        ];
    }

    private function applyToComparison(array $entries, string $authorFolder, string $workFolder, Comparison $comparison, string $role, bool $clearExisting, bool $replaceExisting): array
    {
        $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
        $paths    = $this->candidatePaths($authorFolder, $workFolder, $comparison, $fileName);

        // Report comparison start to progress even if files are missing
        $this->progressTick($role, 'start', ['total' => count($entries), 'comparison_id' => $comparison->id]);

        $existing = array_values(array_filter($paths, fn ($path) => is_file($path)));
        if (empty($existing)) {
            return [
                'status'   => 'skipped',
                'reason'   => 'Aucun fichier trouvé',
                'paths'    => $paths,
                'inserted' => 0,
                'misses'   => [],
            ];
        }

        $html = file_get_contents($existing[0]);
        if ($clearExisting) {
            $html = $this->clearExistingMarkers($html);
        }

        $orientation = $role === 'source' ? 'right' : 'left';
        $result      = $this->insertMarkers(
            $html,
            $entries,
            $orientation,
            function(string $event, array $data = []) use ($role, $comparison) {
                $this->progressTick($role, $event, $data + ['comparison_id' => $comparison->id]);
            },
            $replaceExisting,
            $clearExisting
        );

        foreach ($existing as $path) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $result['html']);
        }

        return [
            'status'        => 'ok',
            'comparison_id' => $comparison->id,
            'paths'         => $existing,
            'inserted'      => $result['inserted'],
            'misses'        => $result['misses'],
        ];
    }

    private function countMarkersForRole(string $authorFolder, string $workFolder, Comparison $comparison, string $role): int
    {
        $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
        $paths    = $this->candidatePaths($authorFolder, $workFolder, $comparison, $fileName, true);

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $content = file_get_contents($path);
            preg_match_all('/<span\s+class="page-marker"/i', $content, $matches);
            if (!empty($matches[0])) {
                return count($matches[0]);
            }
        }

        return 0;
    }

    private function countMarkersInVersion(Version $version): int
    {
        $candidates = [
            storage_path("app/public/uploads/versions/{$version->folder}.xml"),
            public_path("uploads/versions/{$version->folder}.xml"),
            base_path("../variance/uploads/versions/{$version->folder}.xml"),
        ];

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            $contents = @file_get_contents($path);
            if ($contents === false || $contents === '') {
                continue;
            }

            $count = preg_match_all('/<span\s+class="page-marker"/i', $contents, $matches);
            if ($count > 0) {
                return $count;
            }
        }

        return 0;
    }

    /** Count <pb> markers inside the TEI version. */
    public function countPbMarkers(Version $version): int
    {
        $contents = $this->readVersionXml($version);
        if ($contents === null) {
            return 0;
        }

        return preg_match_all('/<pb\b[^>]*>/i', $contents, $matches) ?: 0;
    }

    /**
     * Generate a pagination sidecar from inline <pb> tags found in the TEI.
     * Uses the character index in the stripped plaintext as the insertion point.
     *
     * @return array{count:int,relative:string}
     */
    public function createSidecarFromPb(Version $version): array
    {
        $contents = $this->readVersionXml($version);
        if ($contents === null) {
            return ['count' => 0, 'relative' => $this->paginationRelativePath($version->id)];
        }

        $markers = $this->extractPbMarkers($contents);
        $relative = $this->paginationRelativePath($version->id);

        $payload = [
            'version_id'   => $version->id,
            'version_folder' => $version->folder,
            'work_id'      => $version->work_id,
            'marker_count' => count($markers),
            'markers'      => $markers,
            'origin'       => 'pb-tei',
            'generated_at' => time(),
        ];

        Storage::disk('local')->put($relative, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['count' => count($markers), 'relative' => $relative];
    }

    /**
     * Read the TEI XML for a version.
     */
    private function readVersionXml(Version $version): ?string
    {
        $candidates = [
            storage_path("app/public/uploads/versions/{$version->folder}.xml"),
            public_path("uploads/versions/{$version->folder}.xml"),
            base_path("../variance/uploads/versions/{$version->folder}.xml"),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $contents = @file_get_contents($path);
                if ($contents !== false && $contents !== '') {
                    return $contents;
                }
            }
        }

        return null;
    }

    /**
     * Turn inline <pb> nodes into pagination entries with char_index/image/page.
     *
     * @param string $contents TEI XML
     * @return array<int, array{char_index:int,image?:string,page?:string}>
     */
    private function extractPbMarkers(string $contents): array
    {
        // Ignore the header so offsets line up with Medite XHTML (which never includes teiHeader).
        $contents = $this->stripTeiHeader($contents);

        // Build the plaintext shadow + offset map to compute char_index the same way insertion does.
        [, $map] = $this->buildIndexedPlaintext($contents);

        $markers = [];
        $mapIndex = 0;
        $offset = 0;
        $pattern = '/<pb\b[^>]*>/i';

        while (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $tag = $match[0][0];
            $pos = $match[0][1];

            // Advance through the map to find the plaintext index that corresponds to this tag position.
            $mapCount = count($map);
            while ($mapIndex < $mapCount && $map[$mapIndex] < $pos) {
                $mapIndex++;
            }

            $attrs = $this->parsePbAttributes($tag);
            $entry = ['char_index' => $mapIndex];
            if (!empty($attrs['facs'])) {
                $entry['image'] = $attrs['facs'];
            }
            if (!empty($attrs['pagination'])) {
                $entry['page'] = $attrs['pagination'];
            } elseif (!empty($attrs['n'])) {
                $entry['page'] = $attrs['n'];
            }

            $markers[] = $entry;
            $offset = $pos + strlen($tag);
        }

        return $markers;
    }

    /**
     * Remove the <teiHeader> block to align offsets with comparison XHTML content.
     */
    private function stripTeiHeader(string $contents): string
    {
        $stripped = preg_replace('~<teiHeader.*?</teiHeader>~is', '', $contents);
        return $stripped ?? $contents;
    }

    /**
     * Build a pagination sidecar from the pb tags present in comparison XHTML
     * files (source.xhtml / target.xhtml). This aligns char_index with the
     * Medite-normalized text that will receive the pagination spans.
     *
     * @return array{status:string,details:array}
     */
    public function createSidecarFromComparisonOutputs(Comparison $comparison, ?string $role = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $roles = [
            'source' => $comparison->sourceVersion,
            'target' => $comparison->targetVersion,
        ];

        if ($role !== null) {
            $role = strtolower($role);
            if (!array_key_exists($role, $roles)) {
                return [
                    'status'  => 'error',
                    'details' => [['role' => $role, 'message' => 'Rôle invalide']],
                ];
            }
            $roles = [$role => $roles[$role]];
        }

        $details = [];

        foreach ($roles as $r => $version) {
            if (!$version || !$version->work || !$version->work->author) {
                $details[] = ['role' => $r, 'status' => 'missing', 'message' => 'Version ou dossier auteur/œuvre manquant'];
                continue;
            }

            $fileName = $r === 'source' ? 'source.xhtml' : 'target.xhtml';
            $paths    = $this->candidatePaths($version->work->author->folder, $version->work->folder, $comparison, $fileName);
            $existing = array_values(array_filter($paths, static fn ($p) => is_file($p)));
            if (empty($existing)) {
                $details[] = ['role' => $r, 'status' => 'missing', 'message' => "Fichier {$fileName} introuvable"];
                continue;
            }

            $contents = file_get_contents($existing[0]);
            if ($contents === false || $contents === '') {
                $details[] = ['role' => $r, 'status' => 'missing', 'message' => "Impossible de lire {$fileName}"];
                continue;
            }

            $markers = $this->extractPbMarkersFromHtml($contents);
            $relative = $this->paginationRelativePath($version->id);

            $payload = [
                'version_id'     => $version->id,
                'version_folder' => $version->folder,
                'work_id'        => $version->work_id,
                'comparison_id'  => $comparison->id,
                'role'           => $r,
                'marker_count'   => count($markers),
                'markers'        => $markers,
                'origin'         => 'pb-xhtml',
                'generated_at'   => time(),
            ];

            Storage::disk('local')->put(
                $relative,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $details[] = [
                'role'   => $r,
                'status' => 'ok',
                'count'  => count($markers),
                'path'   => $relative,
            ];
        }

        $allOk = !empty($details) && collect($details)->every(fn ($d) => ($d['status'] ?? '') === 'ok');

        return [
            'status'  => $allOk ? 'ok' : 'partial',
            'details' => $details,
        ];
    }

    /**
     * Extract pb markers from XHTML/HTML and compute char_index using the
     * plaintext map produced by buildIndexedPlaintext.
     *
     * @return array<int, array{char_index:int,image?:string,page?:string}>
     */
    private function extractPbMarkersFromHtml(string $contents): array
    {
        [$shadow, $map] = $this->buildIndexedPlaintext($contents, false);
        $mapCount = count($map);

        $markers = [];
        $pattern = '/<pb\b[^>]*>/i';
        $offset = 0;

        while (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $tag = $match[0][0];
            $pos = $match[0][1];

            $mapIndex = 0;
            while ($mapIndex < $mapCount && $map[$mapIndex] < $pos) {
                $mapIndex++;
            }
            if ($mapIndex >= $mapCount) {
                $mapIndex = $mapCount > 0 ? $mapCount - 1 : 0;
            }

            $attrs = $this->parsePbAttributes($tag);
            $entry = ['char_index' => $mapIndex];
            if (!empty($attrs['facs'])) {
                $entry['image'] = $attrs['facs'];
            }
            if (!empty($attrs['pagination'])) {
                $entry['page'] = $attrs['pagination'];
            } elseif (!empty($attrs['n'])) {
                $entry['page'] = $attrs['n'];
            }

            $markers[] = $entry;
            $offset = $pos + strlen($tag);
        }

        return $markers;
    }

    /**
     * Extract facs/pagination/n attributes from a <pb> tag.
     */
    private function parsePbAttributes(string $tag): array
    {
        $attrs = [];
        if (preg_match('/\bfacs\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
            $attrs['facs'] = $m[1];
        }
        if (preg_match('/\bpagination\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
            $attrs['pagination'] = $m[1];
        }
        if (preg_match('/\bn\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
            $attrs['n'] = $m[1];
        }

        return $attrs;
    }

    private function candidatePaths(string $authorFolder, string $workFolder, Comparison $comparison, string $fileName, bool $preferPublished = false): array
    {
        $baseComparison = "uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}";
        $published      = "uploads/{$authorFolder}/{$workFolder}/{$comparison->folder}";

        $paths = [];

        $publishedPaths = [
            storage_path("app/public/{$published}/{$fileName}"),
            base_path("../variance/{$published}/{$fileName}"),
        ];

        $comparisonPaths = [
            storage_path("app/public/{$baseComparison}/{$fileName}"),
            base_path("../variance/{$baseComparison}/{$fileName}"),
        ];

        if ($preferPublished) {
            $paths = array_merge($publishedPaths, $comparisonPaths);
        } else {
            $paths = array_merge($comparisonPaths, $publishedPaths);
        }

        return array_values(array_unique($paths));
    }

    /** @return array<int, array{image:string,page:string,phrase:string,line:int}> */
    private function parseLignesFile(string $path): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', file_get_contents($path));
        if (!$rawLines) {
            return [];
        }

        if (str_starts_with($rawLines[0] ?? '', "\u{FEFF}")) {
            $rawLines[0] = ltrim($rawLines[0], "\u{FEFF}");
        }

        $oneLineRegex = '/^\s*(\d{1,4})\s+([0-9]{1,4}[a-z]?|[ivxlcdm]+)(?:\s+(.+))?$/iu';
        $hits = 0;
        foreach (array_slice($rawLines, 0, 40) as $sample) {
            if (preg_match($oneLineRegex, $sample ?? '')) {
                $hits++;
            }
        }

        $entries = [];

        if ($hits > 10) {
            foreach ($rawLines as $idx => $line) {
                if (!preg_match($oneLineRegex, $line ?? '', $match)) {
                    continue;
                }
                $entries[] = [
                    'image'  => ltrim($match[1], '0') ?: '0',
                    'page'   => trim($match[2]),
                    'phrase' => trim((string) ($match[3] ?? '')),
                    'line'   => $idx + 1,
                ];
            }

            return $entries;
        }

        $i = 0;
        $total = count($rawLines);
        while ($i < $total) {
            while ($i < $total && !$this->isImageLine($rawLines[$i] ?? '')) {
                $i++;
            }
            if ($i >= $total) {
                break;
            }
            $image = trim((string) $rawLines[$i]);
            $startLine = $i + 1;
            $i++;

            while ($i < $total && trim((string) ($rawLines[$i] ?? '')) === '') {
                $i++;
            }
            if ($i >= $total) {
                break;
            }

            $page = trim((string) $rawLines[$i]);
            if (!$this->isPageLine($page)) {
                $i++;
                continue;
            }
            $i++;

            while ($i < $total && trim((string) ($rawLines[$i] ?? '')) === '') {
                $i++;
            }
            if ($i >= $total) {
                break;
            }

            $phrase = rtrim((string) $rawLines[$i]);
            $i++;

            if (!preg_match('/^\d{1,4}$/', $image)) {
                continue;
            }

            $entries[] = [
                'image'  => ltrim($image, '0') ?: '0',
                'page'   => $page,
                'phrase' => $phrase,
                'line'   => $startLine,
            ];
        }

        return $entries;
    }

    private function isImageLine(?string $line): bool
    {
        return (bool) preg_match('/^\s*\d{1,4}\s*$/', (string) $line);
    }

    private function isPageLine(?string $line): bool
    {
        return (bool) preg_match('/^\s*(\d{1,4}[a-z]?|[ivxlcdm]+)\s*$/iu', (string) $line);
    }

    private function clearExistingMarkers(string $html): string
    {
        return preg_replace('/\s*<span\s+class="page-marker"[^>]*>\s*<span[^>]*>.*?<\/span>\s*<img[^>]*\/>\s*<\/span>\s*/is', ' ', $html);
    }

    /**
     * Remove the first page-marker matching the provided image code.
     *
     * @return array{0:string,1:bool} Updated HTML and whether a marker was removed.
     */
    private function removeExistingMarkerForImage(string $html, string $imageCode): array
    {
        $pattern = sprintf('/\s*<span\s+class="page-marker"[^>]*data-image-name=(["\'])%1$s\1[^>]*>\s*<span[^>]*>.*?<\/span>\s*<img[^>]*\/>\s*<\/span>\s*/is', preg_quote($imageCode, '/'));
        $count = 0;
        $result = preg_replace($pattern, '', $html, 1, $count);
        if ($result === null) {
            return [$html, false];
        }
        return [$result, $count > 0];
    }

    private function insertMarkers(
        string $html,
        array $entries,
        string $orientation,
        ?callable $progressCb = null,
        bool $replaceExisting = true,
        bool $clearExisting = true,
        bool $previewOnly = false,
        ?callable $capture = null
    ): array
    {
        if ($progressCb) {
            $progressCb('prepare', ['total' => count($entries)]);
        }

        [$shadow, $map, $fold] = $this->buildIndexedPlaintext($html);
        $posShadow = 0;
        $inserted  = 0;
        $misses    = [];
        $positions = [];

        if ($progressCb) { $progressCb('start', ['total' => count($entries)]); }

        $i = 0;
        foreach ($entries as $entry) {
            $i++;
            $image = $entry['image'] ?? '';
            $phrase = trim((string) ($entry['phrase'] ?? ''));
            $imageKey = str_pad((string) $image, 3, '0', STR_PAD_LEFT);
            $matched = false;
            $failureReason = null;

            if (!preg_match('/^\d+$/', (string) $image)) {
                $failureReason = 'image_non_numerique';
            } elseif ($phrase === '') {
                $failureReason = 'phrase_vide';
            } else {
                $variants = $this->phraseVariants($phrase);
                if (empty($variants)) {
                    $failureReason = 'phrase_vide';
                } else {
                    $attempts = ($replaceExisting && !$clearExisting) ? 2 : 1;
                    for ($attempt = 0; $attempt < $attempts; $attempt++) {
                        $match = null;
                        foreach ($variants as $variant) {
                            $foldedVariant = $this->foldString($variant);
                            if ($foldedVariant === '') {
                                continue;
                            }
                            $pattern = $this->buildFlexibleRegex($foldedVariant, true);
                            $match = $this->findMatchWindow($pattern, $fold, $posShadow);
                            if ($match) {
                                break;
                            }
                        }

                        if (!$match) {
                            $failureReason = 'phrase_introuvable';
                            break;
                        }

                        [$matchedText, $shadowOffset] = $match;
                        if (!array_key_exists($shadowOffset, $map)) {
                            $failureReason = 'mapping_introuvable';
                            break;
                        }

                        if ($previewOnly) {
                            $charIndex = $shadowOffset;
                            $positions[] = [
                                'entry'      => $entry,
                                'char_index' => $charIndex,
                                'matched'    => $matchedText,
                                'image_code' => $imageKey,
                            ];
                            if ($capture) {
                                $capture($entry, $charIndex, $matchedText, $shadow);
                            }
                            $posShadow = $shadowOffset + mb_strlen($matchedText ?? '', 'UTF-8');
                            $inserted++;
                            $matched = true;
                            break;
                        }

                        if ($replaceExisting && !$clearExisting && $attempt === 0) {
                            [$htmlCandidate, $wasRemoved] = $this->removeExistingMarkerForImage($html, $imageKey);
                            if ($wasRemoved) {
                                $html = $htmlCandidate;
                                [$shadow, $map, $fold] = $this->buildIndexedPlaintext($html);
                                $posShadow = 0;
                                continue;
                            }
                        }

                        $htmlOffset = $map[$shadowOffset];
                        $insertAt   = $this->moveBeforeOpeningChain($html, $htmlOffset);

                        $marker = sprintf(
                            self::MARKER_TEMPLATE,
                            $imageKey,
                            $this->formatPageLabel($entry['page']),
                            $orientation === 'left' ? '/img/settings/page_left.svg' : '/img/settings/page_right.svg'
                        );

                        $html = substr($html, 0, $insertAt) . $marker . substr($html, $insertAt);
                        $inserted++;

                        [$markerShadow, $markerMap, $markerFold] = $this->buildIndexedPlaintext($marker);
                        $markerLen = strlen($marker);
                        $markerChars = count($markerMap);

                        if ($markerChars) {
                            $prefix = mb_substr($shadow, 0, $shadowOffset, 'UTF-8');
                            $suffix = mb_substr($shadow, $shadowOffset, null, 'UTF-8');
                            $shadow = $prefix . $markerShadow . $suffix;
                            $fold   = mb_substr($fold, 0, $shadowOffset, 'UTF-8') . $markerFold . mb_substr($fold, $shadowOffset, null, 'UTF-8');

                            foreach ($markerMap as &$offsetRef) {
                                $offsetRef += $insertAt;
                            }
                            unset($offsetRef);

                            array_splice($map, $shadowOffset, 0, $markerMap);
                        }

                        $mapCount = count($map);
                        for ($iMap = $shadowOffset + $markerChars; $iMap < $mapCount; $iMap++) {
                            $map[$iMap] += $markerLen;
                        }

                        $posShadow = $shadowOffset + $markerChars + mb_strlen($matchedText ?? '', 'UTF-8');
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                $misses[] = ['entry' => $entry, 'reason' => $failureReason ?? 'phrase_introuvable'];
            }

            if ($progressCb) {
                $progressCb('progress', [
                    'processed' => $i,
                    'inserted'  => $inserted,
                    'missed'    => count($misses),
                ]);
            }
        }

        return [
            'html'     => $html,
            'inserted' => $inserted,
            'misses'   => $misses,
            'positions'=> $positions,
            'shadow'   => $shadow,
        ];
    }

    private function insertMarkersFromOffsets(
        string $html,
        array $markers,
        string $orientation,
        bool $clearExisting,
        bool $replaceExisting
    ): array {
        $normalized = array_values(array_filter($markers, static function ($marker) {
            return (isset($marker['char_index']) && is_numeric($marker['char_index'])) || !empty($marker['phrase']);
        }));

        usort($normalized, static function ($a, $b) {
            return (int)($a['char_index'] ?? 0) <=> (int)($b['char_index'] ?? 0);
        });

        if (!$clearExisting && $replaceExisting) {
            foreach ($normalized as $marker) {
                $imageCode = $this->normalizeImageCode($marker);
                if (!$imageCode) {
                    continue;
                }
                [$html] = $this->removeExistingMarkerForImage($html, $imageCode);
            }
        }

        [$shadow, $map, $fold] = $this->buildIndexedPlaintext($html);
        $inserted = 0;
        $misses   = [];
        $offsetShift = 0;

        foreach ($normalized as $marker) {
            $charIndex = (int) ($marker['char_index'] ?? -1);
            $resolvedIndex = $this->resolveMarkerIndex($marker, $shadow, $fold, $map, $charIndex);
            if ($resolvedIndex === null || !array_key_exists($resolvedIndex, $map)) {
                $misses[] = ['marker' => $marker, 'reason' => 'index_introuvable'];
                continue;
            }
            $charIndex = $resolvedIndex;

            $imageCode = $this->normalizeImageCode($marker);
            if (!$imageCode) {
                $misses[] = ['marker' => $marker, 'reason' => 'image_invalide'];
                continue;
            }

            $pageLabel = $this->formatPageLabel((string) ($marker['page'] ?? $imageCode));
            $icon = $orientation === 'left'
                ? '/img/settings/page_left.svg'
                : '/img/settings/page_right.svg';

            $markerHtml = sprintf(
                self::MARKER_TEMPLATE,
                $imageCode,
                $pageLabel,
                $icon
            );

            $htmlOffset = $map[$charIndex] + $offsetShift;
            $htmlOffset = $this->advanceToTextBoundary($html, $htmlOffset);
            $html = substr($html, 0, $htmlOffset) . $markerHtml . substr($html, $htmlOffset);
            $offsetShift += strlen($markerHtml);
            $inserted++;
        }

        return [
            'html'     => $html,
            'inserted' => $inserted,
            'misses'   => $misses,
        ];
    }

    private function normalizeImageCode(array $marker): ?string
    {
        $raw = null;
        if (isset($marker['image_code'])) {
            $raw = (string) $marker['image_code'];
        } elseif (isset($marker['image'])) {
            $raw = (string) $marker['image'];
        }
        if ($raw === null || $raw === '') {
            return null;
        }

        // Prefer the trailing numeric chunk (e.g., img_1pbp_003.jpg => 003)
        if (preg_match('/(\d{1,4})(?!.*\d)/', $raw, $m)) {
            $code = $m[1];
        } else {
            $code = preg_replace('/\D/', '', $raw);
        }

        if ($code === null || $code === '') {
            return null;
        }

        return str_pad($code, 3, '0', STR_PAD_LEFT);
    }

    private function resolveMarkerIndex(array $marker, string $shadow, string $fold, array $map, int $hint): ?int
    {
        $total = count($map);
        if ($total === 0) {
            return null;
        }

        $hint = max(0, $hint);
        if ($hint >= $total) {
            $hint = $total - 1;
        }

        $phrase = trim((string) ($marker['phrase'] ?? ''));
        if ($phrase === '') {
            return $hint;
        }

        $variants = $this->phraseVariants($phrase);
        if (empty($variants)) {
            return $hint;
        }

        $start = max(0, $hint - 400);
        $window = 1200;

        foreach ($variants as $variant) {
            $foldedVariant = $this->foldString($variant);
            if ($foldedVariant === '') {
                continue;
            }
            $pattern = $this->buildFlexibleRegex($foldedVariant, true);
            $match = $this->findMatchWindow($pattern, $fold, $start, $window);
            if ($match) {
                [, $offset] = $match;
                return $offset;
            }
        }

        return $hint;
    }

    private function advanceToTextBoundary(string $html, int $offset): int
    {
        $len = strlen($html);
        if ($offset < 0) {
            $offset = 0;
        } elseif ($offset > $len) {
            $offset = $len;
        }

        $offset = $this->advancePastOpenTag($html, $offset);

        while ($offset < $len) {
            $char = $html[$offset] ?? '';
            if ($char === '<') {
                $gt = strpos($html, '>', $offset);
                if ($gt === false) {
                    return $len;
                }
                $offset = $gt + 1;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $next = strpos($html, $char, $offset + 1);
                if ($next === false) {
                    return $len;
                }
                $offset = $next + 1;
                continue;
            }
            if ($char === '>') {
                $offset++;
                continue;
            }
            break;
        }

        return $offset;
    }

    private function advancePastOpenTag(string $html, int $offset): int
    {
        $lastLt = strrpos($html, '<', - (strlen($html) - $offset));
        if ($lastLt === false) {
            return $offset;
        }

        $lastGt = strrpos($html, '>', - (strlen($html) - $offset));
        if ($lastGt === false) {
            $lastGt = -1;
        }

        if ($lastLt > $lastGt) {
            $gt = strpos($html, '>', $lastLt);
            if ($gt !== false) {
                return $gt + 1;
            }
            return strlen($html);
        }

        return $offset;
    }

    private function ensureOriginalBackups(Comparison $comparison): void
    {
        foreach (['source', 'target'] as $role) {
            [$paths, $backups] = $this->comparisonRoleFileSet($comparison, $role);
            foreach ($paths as $idx => $path) {
                if (!is_file($path ?? '')) {
                    continue;
                }
                if (!empty($backups[$idx]) && is_file($backups[$idx])) {
                    continue;
                }
                $contents = @file_get_contents($path);
                if ($contents === false) {
                    continue;
                }
                if (stripos($contents, 'page-marker') !== false) {
                    continue;
                }
                $backupPath = $backups[$idx] ?? null;
                if (!$backupPath) {
                    continue;
                }
                File::ensureDirectoryExists(dirname($backupPath));
                File::put($backupPath, $contents);
            }
        }
    }

    public function restoreOriginalComparisonOutputs(Comparison $comparison, ?string $role = null): array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $role = $role ? strtolower($role) : null;
        $rolesToProcess = $role ? [$role] : ['source', 'target'];

        $summary = [];
        $snapshot = $this->getComparisonProgressSnapshot($comparison->id) ?? [];
        $rolesProgress = $snapshot['roles'] ?? [];

        foreach ($rolesToProcess as $currentRole) {
            [$paths, $backups] = $this->comparisonRoleFileSet($comparison, $currentRole);

            $backupContent = null;
            foreach ($backups as $backupPath) {
                if (is_file($backupPath ?? '')) {
                    $backupContent = file_get_contents($backupPath);
                    break;
                }
            }

            if ($backupContent === null) {
                $summary[$currentRole] = [
                    'status'   => 'missing',
                    'restored' => [],
                    'total'    => 0,
                ];
                $rolesProgress[$currentRole] = [
                    'status'   => 'missing',
                    'total'    => 0,
                    'inserted' => 0,
                    'missed'   => 0,
                ];
                continue;
            }

            $restored = [];
            foreach ($paths as $path) {
                if (!$path) continue;
                File::ensureDirectoryExists(dirname($path));
                File::put($path, $backupContent);
                $restored[] = $path;
            }

            $version = $currentRole === 'target'
                ? $comparison->targetVersion
                : $comparison->sourceVersion;

            $total = 0;
            if ($version) {
                $sidecar = $this->loadPaginationSidecar($version->id);
                if ($sidecar) {
                    $total = count($sidecar['markers'] ?? []);
                }
            }

            $summary[$currentRole] = [
                'status'   => 'restored',
                'restored' => $restored,
                'total'    => $total,
            ];

            $rolesProgress[$currentRole] = [
                'status'   => 'restored',
                'total'    => $total,
                'inserted' => 0,
                'missed'   => 0,
            ];
        }

        $this->finishComparisonProgress($comparison->id, [
            'status'        => 'restored',
            'roles'         => $rolesProgress,
            'summary'       => array_replace($snapshot['summary'] ?? [], $summary),
            'updated_at'    => time(),
        ]);

        return $summary;
    }

    public function cancelComparisonProgress(Comparison $comparison, ?string $role = null, string $reason = 'Annulé par l\'utilisateur'): array
    {
        $comparisonId = $comparison->id;
        $snapshot = $this->getComparisonProgressSnapshot($comparisonId) ?? [];
        $rolesProgress = $snapshot['roles'] ?? [];

        $targets = $role ? [strtolower($role)] : ['source', 'target'];
        foreach ($targets as $currentRole) {
            $existing = $rolesProgress[$currentRole] ?? [];
            $rolesProgress[$currentRole] = array_replace(
                $existing,
                [
                    'status'     => 'cancelled',
                    'reason'     => $reason,
                    'updated_at' => time(),
                ]
            );
        }

        $status = $this->summarizeComparisonStatus($rolesProgress, $snapshot['status'] ?? null);

        $payload = [
            'status'     => $status,
            'roles'      => $rolesProgress,
            'summary'    => $snapshot['summary'] ?? [],
            'updated_at' => time(),
        ];

        $this->finishComparisonProgress($comparisonId, $payload);

        return $this->getComparisonProgressSnapshot($comparisonId) ?? $payload;
    }

    private function comparisonRoleFileSet(Comparison $comparison, string $role): array
    {
        $version = $role === 'source' ? $comparison->sourceVersion : $comparison->targetVersion;
        if (!$version || !$version->work || !$version->work->author) {
            return [[], []];
        }

        $authorFolder = $version->work->author->folder;
        $workFolder   = $version->work->folder;
        if (!$authorFolder || !$workFolder) {
            return [[], []];
        }

        $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
        $paths    = $this->candidatePaths($authorFolder, $workFolder, $comparison, $fileName);
        $backupName = $role === 'source' ? self::ORIGINAL_SOURCE : self::ORIGINAL_TARGET;
        $backups = array_map(static function ($path) use ($backupName) {
            return $path ? dirname($path) . DIRECTORY_SEPARATOR . $backupName : null;
        }, $paths);

        return [$paths, $backups];
    }

    private function extractContextSnippet(string $shadow, int $charIndex, int $radius = 45): string
    {
        $start = max(0, $charIndex - $radius);
        $length = ($radius * 2);
        return mb_substr($shadow, $start, $length, 'UTF-8');
    }

    private function buildIndexedPlaintext(string $src, bool $needFold = true): array
    {
        $shadow = '';
        $fold   = $needFold ? '' : null;
        $map    = [];
        $length = strlen($src);
        $offset = 0;

        while ($offset < $length) {
            $char = $src[$offset] ?? '';

            if ($char === '<') {
                $gt = strpos($src, '>', $offset);
                if ($gt === false) {
                    break;
                }
                $offset = $gt + 1;
                continue;
            }

            if ($char === '&') {
                $semi = strpos($src, ';', $offset);
                if ($semi !== false && ($semi - $offset) <= 20) {
                    $entity  = substr($src, $offset, $semi - $offset + 1);
                    $decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($decoded !== '') {
                        $shadow .= $decoded;
                        $chars = preg_split('//u', $decoded, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($chars as $_) {
                            $map[] = $offset;
                        }
                        if ($needFold) {
                            $fold .= $this->foldString($decoded);
                        }
                        $offset = $semi + 1;
                        continue;
                    }
                }
            }

            if (!preg_match('/./us', $src, $match, 0, $offset)) {
                break;
            }

            $glyph    = $match[0];
            $glyphLen = strlen($glyph);

            $shadow .= $glyph;
            if ($needFold) {
                $fold   .= $this->foldString($glyph);
            }
            $map[] = $offset;
            $offset += $glyphLen;
        }

        return [$shadow, $map, $fold];
    }

    private function foldString(string $s): string
    {
        // Replace diacritics and special letters with single ASCII letters to make matching accent-insensitive.
        static $map = null;
        if ($map === null) {
            $from = 'ÀÁÂÃÄÅàáâãäåĀāĂăĄąÇçĆćČčĎďĐđÈÉÊËèéêëĒēĖėĘęÌÍÎÏìíîïĪīĮįŁłÑñŃńŇňÒÓÔÕÖØòóôõöøŌōŎŏŐőŔŕŘřŚśŠšŢţŤťÙÚÛÜùúûüŪūŮůŰűŲųÝýÿŸŹźŻżŽžŒœÆæß’‘ʼ`´᾿ʹ’ʼʼ"“”„«»•··–—‑‑−…';
            $to   = 'AAAAAAaaaaaaAaAaAaCcCcCcDdDdEEEEeeeeEeEeEeIIIIiiiiIiIiLlNnNnOOOOOOooooooOoOoOoRrRrSsSsTtTtUUUUuuuuUuUuUuYyyYZzZzZzOeOeAeaeSs\'\'\'\'\'\'\'\'"""""..-- --...';
            // Build associative map per Unicode char to single replacement char
            $map = [];
            $fromChars = preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY);
            $toChars   = preg_split('//u', $to,   -1, PREG_SPLIT_NO_EMPTY);
            $count = min(count($fromChars), count($toChars));
            for ($i = 0; $i < $count; $i++) {
                $map[$fromChars[$i]] = $toChars[$i];
            }
        }
        $out = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $out .= $map[$ch] ?? $ch;
        }
        return $out;
    }

    private function buildFlexibleRegex(string $phrase, bool $isFolded = false): string
    {
        $parts = [];
        $chars = preg_split('//u', $phrase ?? '', -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $ch) {
            if (preg_match('/\s/u', $ch)) {
                $parts[] = '\\s+';
                continue;
            }

            switch ($ch) {
                case "'":
                case '’':
                    $parts[] = "(?:'|’)";
                    break;
                case '-':
                case '–':
                case '—':
                case "\u{2010}":
                case "\u{2011}":
                case "\u{00AD}":
                case "\u{2212}":
                    $parts[] = '\\s*(?:-|–|—|\x{2010}|\x{2011}|\x{00AD}|\x{2212})\\s*';
                    break;
                case '…':
                    $parts[] = '(?:…|\\.{3})';
                    break;
                case '"':
                case '“':
                case '”':
                case '«':
                case '»':
                    $parts[] = '(?:"|“|”|«|»)';
                    break;
                default:
                    $parts[] = preg_quote($ch, '/');
            }
        }

        $pattern = implode('', $parts);
        return $isFolded
            ? '/' . $pattern . '/ius'
            : '/' . $pattern . '/ius';
    }

    private function limitPhrase(string $phrase): string
    {
        // Keep only the first N characters to avoid pathological regex costs
        // Prefer to cut at a word boundary.
        $max = 160; // tuning knob
        if (mb_strlen($phrase, 'UTF-8') <= $max) {
            return $phrase;
        }
        $cut = mb_substr($phrase, 0, $max, 'UTF-8');
        // Trim back to last space to avoid chopping in the middle of a token
        $pos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($pos !== false && $pos > 40) {
            $cut = mb_substr($cut, 0, $pos, 'UTF-8');
        }
        return $cut;
    }

    /** @return array<int, string> */
    private function phraseVariants(string $phrase): array
    {
        $variants = [];
        $base = $this->limitPhrase($phrase);
        $this->appendVariant($variants, $base);

        $collapsed = preg_replace('/\s+/u', ' ', $base);
        $this->appendVariant($variants, $collapsed);

        $clause = $this->clipLeadingClause($collapsed ?? '');
        $this->appendVariant($variants, $clause);

        $leading = $this->takeLeadingWords($collapsed ?? '', 8);
        $this->appendVariant($variants, $leading);

        return $variants;
    }

    private function appendVariant(array &$variants, ?string $candidate): void
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return;
        }
        if (mb_strlen($candidate, 'UTF-8') < 8) {
            return;
        }
        if (!in_array($candidate, $variants, true)) {
            $variants[] = $candidate;
        }
    }

    private function clipLeadingClause(string $phrase): ?string
    {
        if ($phrase === '') {
            return null;
        }

        if (preg_match('/^(.{12,}?)[,;:!?»].*$/u', $phrase, $matches)) {
            return rtrim($matches[1]);
        }

        if (preg_match('/^(.{12,}?)….*$/u', $phrase, $matches)) {
            return rtrim($matches[1]);
        }

        return null;
    }

    private function takeLeadingWords(string $phrase, int $maxWords): ?string
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return null;
        }

        $words = preg_split('/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $maxWords) {
            return null;
        }

        $slice = array_slice($words, 0, $maxWords);
        $candidate = implode(' ', $slice);

        return mb_strlen($candidate, 'UTF-8') >= 8 ? $candidate : null;
    }

    private function findMatch(string $pattern, string $subject, int $offset): ?array
    {
        if (!preg_match($pattern, $subject, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }

        return [$matches[0][0], $matches[0][1]];
    }

    private function findMatchWindow(string $pattern, string $subject, int $offset, int $window = 200000): ?array
    {
        $len = strlen($subject);
        if ($offset >= $len) {
            return null;
        }

        $slice = substr($subject, $offset, $window);
        if ($slice === '') {
            return null;
        }

        if (preg_match($pattern, $slice, $matches, PREG_OFFSET_CAPTURE)) {
            return [$matches[0][0], $offset + $matches[0][1]];
        }

        // Fallback once on the full tail if not found in window
        return $this->findMatch($pattern, $subject, $offset);
    }

    private function moveBeforeOpeningChain(string $html, int $idx): int
    {
        $cursor = $idx;
        $length = strlen($html);

        while ($cursor > 0) {
            $trim = $cursor;
            while ($trim > 0 && ctype_space($html[$trim - 1])) {
                $trim--;
            }

            if ($trim > 0 && $html[$trim - 1] === '>') {
                $start = strrpos(substr($html, 0, $trim - 1), '<');
                if ($start === false) {
                    break;
                }

                $tag = substr($html, $start, $trim - $start);
                if (str_starts_with($tag, '</') || str_starts_with($tag, '<!') || str_starts_with($tag, '<?')) {
                    break;
                }

                $cursor = $start;
                continue;
            }

            break;
        }

        return $cursor;
    }

    private function formatPageLabel(string $page): string
    {
        $safe = htmlspecialchars($page, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return str_replace('.', '.<br />', $safe);
    }

    /* ───────────────────── Progress helpers ───────────────────── */
    public function markQueued(int $versionId): void
    {
        $path = $this->progressFilePath($versionId);
        $this->clearCancellation($versionId);
        $existing = $this->getProgressSnapshot($versionId);
        $payload = [
            'status'             => 'queued',
            'stage'              => 'queued',
            'version_id'         => $versionId,
            'entries_total'      => $existing['entries_total'] ?? 0,
            'processed_total'    => $existing['processed_total'] ?? 0,
            'inserted_total'     => $existing['inserted_total'] ?? 0,
            'missed_total'       => $existing['missed_total'] ?? 0,
            'comparison_total'   => $existing['comparison_total'] ?? 0,
            'comparison_current' => $existing['comparison_current'] ?? 0,
            'updated_at'         => time(),
            'source'             => $existing['source'] ?? ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
            'target'             => $existing['target'] ?? ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
            'summary'            => $existing['summary'] ?? null,
        ];
        $this->writeProgressPayload($path, $payload);
        $this->progressVersionId = $versionId;
    }

    public function markFailed(int $versionId, string $message): void
    {
        $path = $this->progressFilePath($versionId);
        $error = mb_substr($message, 0, 1000, 'UTF-8');

        if ($this->progressPath === $path && !empty($this->progress)) {
            $data = $this->progress;
            $data['status'] = 'failed';
            $data['stage'] = 'failed';
            $data['error'] = $error;
            $data['updated_at'] = time();
        } else {
            $data = [
                'status' => 'failed',
                'stage'  => 'failed',
                'version_id' => $versionId,
                'entries_total' => $this->progress['entries_total'] ?? 0,
                'processed_total' => $this->progress['processed_total'] ?? 0,
                'inserted_total'  => $this->progress['inserted_total'] ?? 0,
                'missed_total'    => $this->progress['missed_total'] ?? 0,
                'comparison_total'   => $this->progress['comparison_total'] ?? 0,
                'comparison_current' => $this->progress['comparison_current'] ?? 0,
                'updated_at' => time(),
                'error' => $error,
                'source' => ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
                'target' => ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
            ];
        }

        $this->writeProgressPayload($path, $data);
        $this->clearCancellation($versionId);
    }

    public function markComparisonQueued(int $comparisonId, ?array $roles = null): void
    {
        $payload = [
            'status'        => 'queued',
            'comparison_id' => $comparisonId,
            'updated_at'    => time(),
        ];
        if ($roles !== null) {
            $formatted = [];
            foreach ($roles as $role => $info) {
                $roleKey = strtolower((string) $role);
                if ($roleKey === '') {
                    continue;
                }
                $info = is_array($info) ? $info : [];
                $entry = [
                    'status'     => 'queued',
                    'total'      => (int) ($info['total'] ?? 0),
                    'inserted'   => 0,
                    'missed'     => 0,
                ];
                if (isset($info['version_id'])) {
                    $entry['version_id'] = $info['version_id'];
                }
                if (array_key_exists('reason', $info)) {
                    $entry['reason'] = $info['reason'];
                }
                $formatted[$roleKey] = $entry;
            }
            if (!empty($formatted)) {
                $payload['roles'] = $formatted;
            }
        }
        $this->writeComparisonProgress($comparisonId, $payload);
    }

    public function getComparisonProgressSnapshot(int $comparisonId): ?array
    {
        $path = $this->comparisonProgressFilePath($comparisonId);
        if (!is_file($path)) {
            return null;
        }
        $json = @file_get_contents($path);
        if ($json === false || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function markComparisonFailed(int $comparisonId, string $message): void
    {
        $payload = [
            'status'     => 'failed',
            'error'      => mb_substr($message, 0, 1000, 'UTF-8'),
            'updated_at' => time(),
        ];
        $this->writeComparisonProgress($comparisonId, $payload);
    }

    private function comparisonProgressFilePath(int $comparisonId): string
    {
        $dir = storage_path('app/tmp/pager/comparisons');
        File::ensureDirectoryExists($dir);
        return $dir . '/' . $comparisonId . '.json';
    }

    private function writeComparisonProgress(int $comparisonId, array $payload): void
    {
        $existing = $this->getComparisonProgressSnapshot($comparisonId) ?? [];
        $merged   = array_replace_recursive($existing, $payload);
        $merged['comparison_id'] = $comparisonId;
        $merged['updated_at'] = $payload['updated_at'] ?? time();
        $this->writeProgressPayload($this->comparisonProgressFilePath($comparisonId), $merged);
    }

    private function updateComparisonRoleProgress(int $comparisonId, string $role, array $payload): void
    {
        $changes = [
            'roles' => [
                $role => array_replace_recursive(
                    $this->getComparisonProgressSnapshot($comparisonId)['roles'][$role] ?? [],
                    $payload,
                    ['updated_at' => time()]
                ),
            ],
            'updated_at' => time(),
        ];
        $this->writeComparisonProgress($comparisonId, $changes);
    }

    private function finishComparisonProgress(int $comparisonId, array $payload): void
    {
        $payload['status'] = $payload['status'] ?? 'done';
        $payload['updated_at'] = $payload['updated_at'] ?? time();
        $this->writeComparisonProgress($comparisonId, $payload);
    }

    private function summarizeComparisonStatus(array $rolesProgress, ?string $fallback = null): string
    {
        if (empty($rolesProgress)) {
            return $fallback ?? 'idle';
        }

        $statuses = [];
        foreach ($rolesProgress as $progress) {
            $status = strtolower((string) ($progress['status'] ?? ''));
            if ($status !== '') {
                $statuses[] = $status;
            }
        }

        if (empty($statuses)) {
            return $fallback ?? 'idle';
        }

        if (!empty(array_intersect($statuses, ['running', 'queued']))) {
            return 'running';
        }

        if (!empty(array_intersect($statuses, ['failed']))) {
            return 'failed';
        }

        if (!empty(array_intersect($statuses, ['cancelled']))) {
            if (empty(array_intersect($statuses, ['done', 'ok', 'restored']))) {
                return 'cancelled';
            }
        }

        if (!empty(array_intersect($statuses, ['done', 'ok']))) {
            return 'done';
        }

        if (!empty(array_intersect($statuses, ['restored']))) {
            return 'restored';
        }

        if (!empty(array_intersect($statuses, ['skipped']))) {
            return 'skipped';
        }

        if (!empty(array_intersect($statuses, ['cancelled']))) {
            return 'cancelled';
        }

        return $fallback ?? 'idle';
    }

    public function markCancelled(int $versionId, string $reason = 'Annulé par l\'utilisateur'): void
    {
        $path = $this->progressFilePath($versionId);
        $snapshot = $this->getProgressSnapshot($versionId) ?? [
            'entries_total'      => 0,
            'processed_total'    => 0,
            'inserted_total'     => 0,
            'missed_total'       => 0,
            'comparison_total'   => 0,
            'comparison_current' => 0,
            'source' => ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
            'target' => ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
        ];

        $payload = array_merge($snapshot, [
            'status'     => 'cancelled',
            'stage'      => 'cancelled',
            'version_id' => $versionId,
            'updated_at' => time(),
            'error'      => $reason,
        ]);

        $this->writeProgressPayload($path, $payload);
        File::put($this->cancelFlagPath($versionId), '1');
    }

    public function clearCancellation(int $versionId): void
    {
        $flag = $this->cancelFlagPath($versionId);
        if (is_file($flag)) {
            @unlink($flag);
        }
    }

    public function resetProgress(int $versionId): void
    {
        $path = $this->progressFilePath($versionId);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->clearCancellation($versionId);
    }

    public function isCancelled(int $versionId): bool
    {
        return is_file($this->cancelFlagPath($versionId));
    }

    private function initProgress(int $versionId, int $totalEntries, int $comparisonCount): void
    {
        $this->progressPath = $this->progressFilePath($versionId);
        $this->progressVersionId = $versionId;
        $this->progress = [
            'status' => 'running',
            'stage' => 'preparing',
            'version_id' => $versionId,
            'entries_total' => $totalEntries,
            'updated_at' => time(),
            'processed_total' => 0,
            'inserted_total'  => 0,
            'missed_total'    => 0,
            'comparison_total' => $comparisonCount,
            'comparison_current' => 0,
            'source' => ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
            'target' => ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0],
            'summary' => null,
        ];
        $this->writeProgress();
    }

    private function progressTick(string $role, string $event, array $data = []): void
    {
        if (!$this->progressPath) return;
        if ($event === 'prepare') {
            if (isset($data['total'])) {
                $this->progress['entries_total'] = max($this->progress['entries_total'] ?? 0, (int) $data['total']);
            }
            $this->progress['status'] = 'running';
            $this->progress['stage']  = 'preparing';
            $this->progress['updated_at'] = time();
            $this->writeProgress();
            return;
        }

        if (!isset($this->progress[$role])) {
            $this->progress[$role] = ['processed' => 0, 'inserted' => 0, 'missed' => 0, 'comparisons' => 0];
        }
        $p =& $this->progress[$role];
        if ($event === 'start') {
            $p['comparisons'] = ($p['comparisons'] ?? 0) + 1;
            if (isset($data['total'])) {
                $this->progress['entries_total'] = max($this->progress['entries_total'] ?? 0, (int) $data['total']);
            }
            $this->progress['status'] = 'running';
            $this->progress['stage'] = 'preparing';
            $totalComparisons = (int) ($this->progress['comparison_total'] ?? 0);
            $current = ($this->progress['comparison_current'] ?? 0) + 1;
            if ($totalComparisons > 0) {
                $current = min($current, $totalComparisons);
            }
            $this->progress['comparison_current'] = $current;
        } elseif ($event === 'progress') {
            $current = (int)($data['processed'] ?? 0);
            $limit   = $this->progress['entries_total'] ?? null;
            if (is_int($limit) && $limit > 0) {
                $current = min($current, $limit);
            }
            $p['processed'] = max($p['processed'] ?? 0, $current);
            $p['inserted']  = max($p['inserted'] ?? 0,  (int)($data['inserted'] ?? 0));
            $p['missed']    = max($p['missed'] ?? 0,    (int)($data['missed'] ?? 0));
            if ($current > 0) {
                $this->progress['stage'] = 'running';
            }
        }
        $sourceProcessed = (int)($this->progress['source']['processed'] ?? 0);
        $targetProcessed = (int)($this->progress['target']['processed'] ?? 0);
        $this->progress['processed_total'] = $sourceProcessed + $targetProcessed;
        $this->progress['inserted_total'] = (int)($this->progress['source']['inserted'] ?? 0) + (int)($this->progress['target']['inserted'] ?? 0);
        $this->progress['missed_total'] = (int)($this->progress['source']['missed'] ?? 0) + (int)($this->progress['target']['missed'] ?? 0);
        if ($this->progressVersionId && $this->isCancelled($this->progressVersionId)) {
            throw new PaginationCancelledException('Annulé par l\'utilisateur');
        }
        $this->progress['updated_at'] = time();
        $this->writeProgress();
    }

    private function finishProgress(array $summary): void
    {
        if (!$this->progressPath) return;
        $this->progress['status']  = 'done';
        $this->progress['stage']   = 'done';
        $this->progress['summary'] = $summary;
        $this->progress['comparison_current'] = $this->progress['comparison_total'] ?? $this->progress['comparison_current'] ?? 0;
        $this->progress['updated_at'] = time();
        $this->progress['summary'] = $summary;
        $this->progress['updated_at'] = time();
        if ($this->progressVersionId) {
            $sidecar = $this->getPaginationInfo($this->progressVersionId);
            if ($sidecar) {
                $this->progress['sidecar'] = [
                    'path'         => $sidecar['path'] ?? null,
                    'updated_at'   => $sidecar['updated_at'] ?? null,
                    'size'         => $sidecar['size'] ?? null,
                    'marker_count' => $sidecar['details']['marker_count'] ?? null,
                    'missed_count' => $sidecar['details']['missed_count'] ?? null,
                ];
            }
        }
        $this->writeProgress();
        if ($this->progressVersionId) {
            $this->clearCancellation($this->progressVersionId);
        }
    }

    private function writeProgress(): void
    {
        if (!$this->progressPath) return;
        $this->writeProgressPayload($this->progressPath, $this->progress);
    }

    private function progressFilePath(int $versionId): string
    {
        $dir = storage_path('app/tmp/pager');
        File::ensureDirectoryExists($dir);
        return $dir . '/' . $versionId . '.json';
    }

    private function cancelFlagPath(int $versionId): string
    {
        $dir = storage_path('app/tmp/pager');
        File::ensureDirectoryExists($dir);
        return $dir . '/' . $versionId . '.cancel';
    }

    private function writeProgressPayload(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $path . '.tmp';
        @file_put_contents($tmp, $json, LOCK_EX);
        @rename($tmp, $path);
    }

    public function getLignesInfo(int $versionId): ?array
    {
        $relative = $this->lignesRelativePath($versionId);
        if (!Storage::disk('local')->exists($relative)) {
            return null;
        }

        return [
            'path'       => $relative,
            'updated_at' => Storage::disk('local')->lastModified($relative),
            'size'       => Storage::disk('local')->size($relative),
            'line_count' => $this->countFileLines($relative),
        ];
    }

    public function getPaginationInfo(int $versionId): ?array
    {
        $relative = $this->paginationRelativePath($versionId);
        if (!Storage::disk('local')->exists($relative)) {
            return null;
        }

        $details = null;
        try {
            $contents = Storage::disk('local')->get($relative);
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $details = [
                'marker_count' => (int) ($decoded['marker_count'] ?? 0),
                'missed_count' => (int) ($decoded['missed_count'] ?? 0),
                'generated_at' => (int) ($decoded['generated_at'] ?? 0),
                'origin'       => $decoded['origin'] ?? null,
            ];
        } catch (\Throwable $e) {
            $details = null;
        }

        return [
            'path'        => $relative,
            'updated_at'  => Storage::disk('local')->lastModified($relative),
            'size'        => Storage::disk('local')->size($relative),
            'details'     => $details,
        ];
    }

    public function deleteLignesArtifacts(int $versionId): array
    {
        $disk = Storage::disk('local');

        $lignesRelative = $this->lignesRelativePath($versionId);
        $lignesRemoved = false;
        if ($disk->exists($lignesRelative)) {
            $lignesRemoved = $disk->delete($lignesRelative);
        }

        $paginationRelative = $this->paginationRelativePath($versionId);
        $paginationRemoved = false;
        if ($disk->exists($paginationRelative)) {
            $paginationRemoved = $disk->delete($paginationRelative);
        }

        $this->resetProgress($versionId);

        return [
            'lignes_removed' => $lignesRemoved,
            'pagination_removed' => $paginationRemoved,
        ];
    }

    public function hasLignesFile(int $versionId): bool
    {
        return Storage::disk('local')->exists($this->lignesRelativePath($versionId));
    }

    public function hasPaginationSidecar(int $versionId): bool
    {
        return Storage::disk('local')->exists($this->paginationRelativePath($versionId));
    }

    public function getStoredLignesAbsolutePath(int $versionId): ?string
    {
        $relative = $this->lignesRelativePath($versionId);
        if (!Storage::disk('local')->exists($relative)) {
            return null;
        }
        return Storage::disk('local')->path($relative);
    }

    public function lignesRelativePath(int $versionId): string
    {
        return "private/lignes/{$versionId}.txt";
    }

    private function countFileLines(string $relative): int
    {
        $disk = Storage::disk('local');
        $absolute = $disk->path($relative);
        if (!is_readable($absolute)) {
            return 0;
        }

        $handle = @fopen($absolute, 'rb');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 64 * 1024);
                if ($chunk === false) {
                    break;
                }
                $count += substr_count($chunk, "\n");
            }
        } finally {
            fclose($handle);
        }

        if ($count === 0 && filesize($absolute) > 0) {
            return 1;
        }

        return $count;
    }

    public function paginationRelativePath(int $versionId): string
    {
        return "private/pagination/{$versionId}.json";
    }

    public function countMarkersForComparison(Comparison $comparison): array
    {
        $comparison->loadMissing('sourceVersion.work.author', 'targetVersion.work.author');

        $result = [
            'source' => [
                'markers'          => 0,
                'lignes_available' => false,
                'lignes'           => null,
                'progress'         => null,
            ],
            'target' => [
                'markers'          => 0,
                'lignes_available' => false,
                'lignes'           => null,
                'progress'         => null,
            ],
        ];

        $sourceVersion = $comparison->sourceVersion;
        $targetVersion = $comparison->targetVersion;

        if ($sourceVersion && ($work = $sourceVersion->work) && ($author = $work->author)) {
            $result['source']['markers'] = $this->countMarkersForRole(
                $author->folder,
                $work->folder,
                $comparison,
                'source'
            );
            $result['source']['lignes_available'] = $this->hasLignesFile($sourceVersion->id) || $this->hasPaginationSidecar($sourceVersion->id);
            $result['source']['lignes']           = $this->getLignesInfo($sourceVersion->id);
            $result['source']['sidecar']          = $this->getPaginationInfo($sourceVersion->id);
            $result['source']['progress']         = $this->getProgressSnapshot($sourceVersion->id);
        }

        if ($targetVersion && ($work = $targetVersion->work) && ($author = $work->author)) {
            $result['target']['markers'] = $this->countMarkersForRole(
                $author->folder,
                $work->folder,
                $comparison,
                'target'
            );
            $result['target']['lignes_available'] = $this->hasLignesFile($targetVersion->id) || $this->hasPaginationSidecar($targetVersion->id);
            $result['target']['lignes']           = $this->getLignesInfo($targetVersion->id);
            $result['target']['sidecar']          = $this->getPaginationInfo($targetVersion->id);
            $result['target']['progress']         = $this->getProgressSnapshot($targetVersion->id);
        }

        return $result;
    }

    public function getProgressSnapshot(int $versionId): ?array
    {
        $path = storage_path('app/tmp/pager/' . $versionId . '.json');
        if (!is_file($path)) {
            return null;
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'status'        => $data['status'] ?? null,
            'stage'         => $data['stage'] ?? null,
            'updated_at'    => $data['updated_at'] ?? null,
            'entries_total' => $data['entries_total'] ?? null,
            'processed_total' => $data['processed_total'] ?? null,
            'inserted_total'  => $data['inserted_total'] ?? null,
            'missed_total'    => $data['missed_total'] ?? null,
            'comparison_total'   => $data['comparison_total'] ?? null,
            'comparison_current' => $data['comparison_current'] ?? null,
            'source'        => $data['source'] ?? null,
            'target'        => $data['target'] ?? null,
            'summary'       => $data['summary'] ?? null,
            'error'         => $data['error'] ?? null,
        ];
    }

}
