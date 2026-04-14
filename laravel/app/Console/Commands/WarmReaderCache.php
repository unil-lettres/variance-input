<?php

namespace App\Console\Commands;

use App\Http\Controllers\VersionController;
use App\Models\Version;
use Illuminate\Console\Command;

class WarmReaderCache extends Command
{
    protected $signature = 'variance:warm-reader-cache
        {--legacy-only : Warm only legacy versions}
        {--version-id=* : Limit to one or more version IDs}
        {--force : Clear existing reader cache artifacts before warming}
        {--encoding= : Optional encoding hint (UTF-8, Windows-1252, ISO-8859-1, Mac Roman)}';

    protected $description = 'Preheat persisted reader datasets for versions so reader pages load from cache.';

    public function handle(VersionController $controller): int
    {
        $versionIds = collect((array) $this->option('version-id'))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        $versions = Version::query()
            ->with('work.author')
            ->when($this->option('legacy-only'), fn ($query) => $query->where('is_legacy', true))
            ->when($versionIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $versionIds->all()))
            ->orderBy('id')
            ->get();

        if ($versions->isEmpty()) {
            $this->components->warn('Aucune version trouvée pour cette sélection.');
            return self::SUCCESS;
        }

        $encoding = $this->option('encoding');
        $force = (bool) $this->option('force');
        $rows = [];
        $errors = 0;

        $this->components->info(sprintf(
            'Préparation du cache lecteur pour %d version(s)%s.',
            $versions->count(),
            $force ? ' avec invalidation préalable' : ''
        ));

        $progress = $this->output->createProgressBar($versions->count());
        $progress->start();

        foreach ($versions as $version) {
            try {
                if ($force) {
                    $controller->clearReaderCache($version);
                }

                $dataset = $controller->warmReaderCache($version, $encoding ?: null, null);
                $pageCount = is_array($dataset['page_plans'] ?? null) ? count($dataset['page_plans']) : 0;
                $rows[] = [
                    $version->id,
                    $version->folder,
                    $dataset['text_source'] ?? '—',
                    isset($dataset['text_source_options']) && is_array($dataset['text_source_options'])
                        ? implode(', ', array_values(array_filter(array_map(
                            static fn (array $entry) => (string) ($entry['value'] ?? ''),
                            $dataset['text_source_options']
                        ))))
                        : '—',
                    $pageCount,
                    $dataset['pagination']['available'] ?? false ? 'oui' : 'non',
                    'ok',
                ];
            } catch (\Throwable $e) {
                $errors++;
                $rows[] = [
                    $version->id,
                    $version->folder,
                    '—',
                    '—',
                    '—',
                    '—',
                    'erreur: ' . $e->getMessage(),
                ];
            } finally {
                $progress->advance();
            }
        }

        $progress->finish();
        $this->newLine(2);

        $this->table(
            ['ID', 'Dossier', 'Source active', 'Sources chauffées', 'Pages', 'Pagination', 'Résultat'],
            $rows
        );

        if ($errors > 0) {
            $this->components->error(sprintf(
                'Warm-up terminé avec %d erreur(s).',
                $errors
            ));
            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Warm-up lecteur terminé pour %d version(s).',
            $versions->count()
        ));

        return self::SUCCESS;
    }
}
