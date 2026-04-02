<?php

namespace App\Console\Commands;

use App\Models\Work;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillLegacyWorkShortTitles extends Command
{
    protected $signature = 'variance:backfill-legacy-work-short-titles
        {--apply-safe : Persist only proposals classified as safe}
        {--work=* : Limit the analysis to one or more work IDs}';

    protected $description = 'Propose or apply legacy work short_title values inferred from version folder hierarchies.';

    public function handle(): int
    {
        $workIds = collect((array) $this->option('work'))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        $works = Work::query()
            ->with(['versions:id,work_id,folder'])
            ->where('is_legacy', true)
            ->when($workIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $workIds->all()))
            ->orderBy('id')
            ->get();

        if ($works->isEmpty()) {
            $this->components->warn('Aucune œuvre legacy trouvée pour cette sélection.');
            return self::SUCCESS;
        }

        $rows = [];
        $safeUpdates = 0;

        foreach ($works as $work) {
            $analysis = $this->analyzeWork($work);
            $action = 'skip';

            if ($analysis['status'] === 'safe' && $this->option('apply-safe')) {
                $current = $this->normalizeShortTitle($work->short_title);
                $proposed = $analysis['proposed_short_title'];

                if ($current !== $proposed) {
                    $work->short_title = $proposed;
                    $work->save();
                    $action = 'updated';
                    $safeUpdates++;
                } else {
                    $action = 'unchanged';
                }
            }

            $rows[] = [
                $work->id,
                $work->title,
                $work->short_title ?? '∅',
                $analysis['proposed_short_title'] ?? '—',
                $analysis['status'],
                $action,
            ];
        }

        $this->table(
            ['ID', 'Titre', 'Actuel', 'Proposé', 'Statut', 'Action'],
            $rows
        );

        foreach ($works as $work) {
            $analysis = $this->analyzeWork($work);
            $this->line('');
            $this->line(sprintf(
                '<options=bold>%d.</> %s',
                $work->id,
                $work->title
            ));
            $this->line('  Dossier œuvre : ' . ($work->folder ?: '—'));
            $this->line('  short_title actuel : ' . ($work->short_title ?: '∅'));
            $this->line('  Proposition : ' . ($analysis['proposed_short_title'] ?? '—') . ' [' . $analysis['status'] . ']');
            $this->line('  Candidats : ' . ($analysis['candidate_summary'] ?: '—'));
            $this->line('  Versions : ' . ($analysis['version_summary'] ?: '—'));
            if (!empty($analysis['notes'])) {
                foreach ($analysis['notes'] as $note) {
                    $this->line('  Note : ' . $note);
                }
            }
        }

        $safeCount = collect($rows)->where(4, 'safe')->count();
        $reviewCount = collect($rows)->where(4, 'review')->count();
        $skipCount = collect($rows)->where(4, 'skip')->count();

        $this->newLine();
        $this->components->info(sprintf(
            'Résumé : %d safe, %d review, %d skip.',
            $safeCount,
            $reviewCount,
            $skipCount
        ));

        if ($this->option('apply-safe')) {
            $this->components->info(sprintf('%d œuvre(s) safe mise(s) à jour.', $safeUpdates));
        } else {
            $this->components->warn('Mode dry-run : aucune modification enregistrée. Utilisez --apply-safe pour écrire uniquement les cas safe.');
        }

        return self::SUCCESS;
    }

    private function analyzeWork(Work $work): array
    {
        $current = $this->normalizeShortTitle($work->short_title);
        if ($current !== null) {
            return [
                'status' => 'skip',
                'proposed_short_title' => $current,
                'candidate_summary' => 'short_title déjà renseigné',
                'version_summary' => $this->summarizeVersionFolders($work->versions),
                'notes' => ['Aucune proposition : le champ est déjà rempli.'],
            ];
        }

        $candidates = [];
        $notes = [];

        foreach ($work->versions as $version) {
            $folder = (string) $version->folder;
            $code = $this->extractShortTitleCandidate($folder);
            if ($code === null) {
                $notes[] = "Version {$folder} ignorée (aucun code exploitable).";
                continue;
            }

            $candidates[$code] ??= [];
            $candidates[$code][] = $folder;
        }

        if (empty($candidates)) {
            return [
                'status' => 'review',
                'proposed_short_title' => null,
                'candidate_summary' => 'aucun candidat',
                'version_summary' => $this->summarizeVersionFolders($work->versions),
                'notes' => ['Aucun code stable n’a pu être déduit des dossiers de versions.'],
            ];
        }

        if (count($candidates) === 1) {
            $code = array_key_first($candidates);

            return [
                'status' => 'safe',
                'proposed_short_title' => $code,
                'candidate_summary' => $this->summarizeCandidates($candidates),
                'version_summary' => $this->summarizeVersionFolders($work->versions),
                'notes' => [],
            ];
        }

        return [
            'status' => 'review',
            'proposed_short_title' => null,
            'candidate_summary' => $this->summarizeCandidates($candidates),
            'version_summary' => $this->summarizeVersionFolders($work->versions),
            'notes' => ['Plusieurs codes concurrents ont été trouvés.'],
        ];
    }

    private function extractShortTitleCandidate(string $versionFolder): ?string
    {
        $normalized = strtolower(trim($versionFolder));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^0[a-z]/', $normalized)) {
            return null;
        }

        $normalized = preg_replace('/^\d+_?/', '', $normalized) ?? $normalized;

        if (!preg_match('/^([a-z]+)/', $normalized, $matches)) {
            return null;
        }

        return $this->normalizeShortTitle($matches[1]);
    }

    private function normalizeShortTitle(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^[a-z][a-z0-9]*$/', $normalized) ? $normalized : null;
    }

    private function summarizeCandidates(array $candidates): string
    {
        return collect($candidates)
            ->map(fn (array $folders, string $code) => $code . ' ← ' . implode(', ', $folders))
            ->implode(' | ');
    }

    private function summarizeVersionFolders(Collection $versions): string
    {
        return $versions
            ->pluck('folder')
            ->filter(fn ($folder) => is_string($folder) && trim($folder) !== '')
            ->implode(', ');
    }
}
