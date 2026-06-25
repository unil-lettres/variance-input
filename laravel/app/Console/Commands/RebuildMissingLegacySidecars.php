<?php

namespace App\Console\Commands;

use App\Models\Comparison;
use App\Models\Version;
use App\Services\PageMarkerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RebuildMissingLegacySidecars extends Command
{
    protected $signature = 'legacy:rebuild-missing-sidecars
        {--write : Write selected sidecars and archive selected _lignes files}
        {--force : Regenerate versions that already have a sidecar}
        {--only=* : Limit to one or more version IDs or folders}
        {--skip-xhtml : Do not fall back to comparison XHTML markers}
        {--max-generic-misses=10 : Maximum misses allowed for generic lignes*.txt candidates}
        {--max-generic-miss-rate=0.05 : Maximum miss rate allowed for generic lignes*.txt candidates}';

    protected $description = 'Rebuild missing legacy pagination sidecars from _lignes files first, then comparison XHTML markers.';

    public function handle(PageMarkerService $pageMarkerService): int
    {
        $write = (bool) $this->option('write');
        $force = (bool) $this->option('force');
        $skipXhtml = (bool) $this->option('skip-xhtml');
        $filters = array_values(array_filter((array) $this->option('only'), static fn ($v) => trim((string) $v) !== ''));

        $query = Version::query()
            ->with('work.author')
            ->where('is_legacy', true)
            ->orderBy('work_id')
            ->orderBy('id');

        if ($filters !== []) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters as $filter) {
                    $q->orWhere('id', $filter)->orWhere('folder', $filter);
                }
            });
        }

        $versions = $query->get();
        if ($versions->isEmpty()) {
            $this->warn('No legacy versions matched.');
            return self::SUCCESS;
        }

        $this->info(($write ? 'Writing' : 'Dry-run').' legacy sidecars for '.$versions->count().' candidate version(s).');

        $stats = [
            'skipped_existing' => 0,
            'written_lignes' => 0,
            'written_xhtml' => 0,
            'dry_lignes' => 0,
            'dry_xhtml' => 0,
            'missing' => 0,
            'failed' => 0,
        ];

        foreach ($versions as $version) {
            $relative = $pageMarkerService->paginationRelativePath($version->id);
            $hasSidecar = Storage::disk('local')->exists($relative);
            if ($hasSidecar && !$force) {
                $stats['skipped_existing']++;
                continue;
            }

            $label = "#{$version->id} {$version->folder} ({$version->work?->author?->folder}/{$version->work?->folder})";
            $this->line('');
            $this->line($label);

            $lignesChoice = $this->chooseLignesCandidate(
                $version,
                $pageMarkerService,
                (int) $this->option('max-generic-misses'),
                (float) $this->option('max-generic-miss-rate'),
            );
            if ($lignesChoice !== null) {
                $summary = sprintf(
                    'selected _lignes: %s [%s] markers=%d misses=%d',
                    $lignesChoice['basename'],
                    $lignesChoice['kind'],
                    $lignesChoice['markers'],
                    $lignesChoice['misses'],
                );

                if ($write) {
                    $this->writeLignesSidecar($version, $lignesChoice['path'], $pageMarkerService);
                    $stats['written_lignes']++;
                    $this->info('  wrote '.$summary);
                } else {
                    $stats['dry_lignes']++;
                    $this->comment('  would write '.$summary);
                }

                continue;
            }

            if (!$skipXhtml) {
                $xhtmlChoice = $this->chooseXhtmlCandidate($version, $pageMarkerService);
                if ($xhtmlChoice !== null) {
                    $summary = sprintf(
                        'selected XHTML: comparison=%d role=%s markers=%d',
                        $xhtmlChoice['comparison_id'],
                        $xhtmlChoice['role'],
                        $xhtmlChoice['markers'],
                    );

                    if ($write) {
                        $comparison = Comparison::query()->findOrFail($xhtmlChoice['comparison_id']);
                        $pageMarkerService->createSidecarFromComparisonOutputs($comparison, $xhtmlChoice['role']);
                        $stats['written_xhtml']++;
                        $this->info('  wrote '.$summary);
                    } else {
                        $stats['dry_xhtml']++;
                        $this->comment('  would write '.$summary);
                    }

                    continue;
                }
            }

            $stats['missing']++;
            $this->warn('  no usable _lignes or XHTML markers found');
        }

        $this->line('');
        $this->table(
            ['metric', 'count'],
            collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all(),
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function chooseLignesCandidate(
        Version $version,
        PageMarkerService $pageMarkerService,
        int $maxGenericMisses,
        float $maxGenericMissRate,
    ): ?array {
        $candidates = $this->lignesCandidates($version);
        if ($candidates === []) {
            return null;
        }

        $evaluated = [];
        foreach ($candidates as $candidate) {
            $result = $this->evaluateLignesCandidate($version, $candidate, $pageMarkerService);
            if ($result === null || $result['markers'] <= 0) {
                continue;
            }

            if ($result['kind'] === 'generic') {
                $total = max(1, $result['markers'] + $result['misses']);
                $missRate = $result['misses'] / $total;
                if ($result['misses'] > $maxGenericMisses || $missRate > $maxGenericMissRate) {
                    continue;
                }
            }

            if ($result !== null && $result['markers'] > 0) {
                $evaluated[] = $result;
            }
        }

        if ($evaluated === []) {
            return null;
        }

        usort($evaluated, static function (array $a, array $b): int {
            return [$a['priority'], $a['misses'], -$a['markers'], $a['basename']]
                <=> [$b['priority'], $b['misses'], -$b['markers'], $b['basename']];
        });

        return $evaluated[0];
    }

    private function lignesCandidates(Version $version): array
    {
        $version->loadMissing('work.author');
        $paths = [];
        $stored = storage_path("app/private/lignes/{$version->id}.txt");
        if (is_file($stored)) {
            $paths[] = ['path' => $stored, 'kind' => 'stored', 'priority' => 0];
        }

        $legacyDir = base_path("../variance/uploads/{$version->work->author->folder}/{$version->work->folder}");
        $direct = "{$legacyDir}/{$version->folder}_lignes.txt";
        if (is_file($direct)) {
            $paths[] = ['path' => $direct, 'kind' => 'direct', 'priority' => 0];
        }

        foreach (glob("{$legacyDir}/lignes*.txt") ?: [] as $generic) {
            if (is_file($generic)) {
                $paths[] = ['path' => $generic, 'kind' => 'generic', 'priority' => 1];
            }
        }

        $seen = [];
        return array_values(array_filter($paths, static function (array $candidate) use (&$seen): bool {
            $real = realpath($candidate['path']) ?: $candidate['path'];
            if (isset($seen[$real])) {
                return false;
            }
            $seen[$real] = true;
            return true;
        }));
    }

    private function evaluateLignesCandidate(Version $version, array $candidate, PageMarkerService $pageMarkerService): ?array
    {
        $snapshot = $this->snapshotSidecar($version, $pageMarkerService);

        try {
            $result = $pageMarkerService->generatePaginationSidecar($version, $candidate['path']);
            $payload = $result['payload'] ?? [];

            return [
                'path' => $candidate['path'],
                'basename' => basename($candidate['path']),
                'kind' => $candidate['kind'],
                'priority' => $candidate['priority'],
                'markers' => (int) ($payload['marker_count'] ?? 0),
                'misses' => (int) ($payload['missed_count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->warn('  _lignes candidate failed: '.basename($candidate['path']).' - '.$e->getMessage());
            return null;
        } finally {
            $this->restoreSidecar($snapshot);
        }
    }

    private function writeLignesSidecar(Version $version, string $path, PageMarkerService $pageMarkerService): void
    {
        $pageMarkerService->generatePaginationSidecar($version, $path);
        Storage::disk('local')->put("lignes/{$version->id}.txt", (string) file_get_contents($path));
    }

    private function chooseXhtmlCandidate(Version $version, PageMarkerService $pageMarkerService): ?array
    {
        $comparisons = Comparison::query()
            ->where('source_id', $version->id)
            ->orWhere('target_id', $version->id)
            ->orderBy('id')
            ->get();

        $evaluated = [];
        foreach ($comparisons as $comparison) {
            $role = ((int) $comparison->source_id === (int) $version->id) ? 'source' : 'target';
            $result = $this->evaluateXhtmlCandidate($version, $comparison, $role, $pageMarkerService);
            if ($result !== null && $result['markers'] > 0) {
                $evaluated[] = $result;
            }
        }

        if ($evaluated === []) {
            return null;
        }

        usort($evaluated, static fn (array $a, array $b): int => [-$a['markers'], $a['comparison_id']] <=> [-$b['markers'], $b['comparison_id']]);

        return $evaluated[0];
    }

    private function evaluateXhtmlCandidate(Version $version, Comparison $comparison, string $role, PageMarkerService $pageMarkerService): ?array
    {
        $snapshot = $this->snapshotSidecar($version, $pageMarkerService);

        try {
            $result = $pageMarkerService->createSidecarFromComparisonOutputs($comparison, $role);
            $details = collect($result['details'] ?? []);
            $detail = $details->first(fn ($item) => ($item['role'] ?? null) === $role && ($item['status'] ?? null) === 'ok');
            if (!$detail) {
                return null;
            }

            return [
                'comparison_id' => $comparison->id,
                'role' => $role,
                'markers' => (int) ($detail['count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->warn("  XHTML candidate failed: comparison {$comparison->id} {$role} - {$e->getMessage()}");
            return null;
        } finally {
            $this->restoreSidecar($snapshot);
        }
    }

    private function snapshotSidecar(Version $version, PageMarkerService $pageMarkerService): array
    {
        $relative = $pageMarkerService->paginationRelativePath($version->id);
        $disk = Storage::disk('local');

        return [
            'relative' => $relative,
            'exists' => $disk->exists($relative),
            'contents' => $disk->exists($relative) ? $disk->get($relative) : null,
        ];
    }

    private function restoreSidecar(array $snapshot): void
    {
        $disk = Storage::disk('local');
        if ($snapshot['exists']) {
            $disk->put($snapshot['relative'], (string) $snapshot['contents']);
        } elseif ($disk->exists($snapshot['relative'])) {
            $disk->delete($snapshot['relative']);
        }
    }
}
