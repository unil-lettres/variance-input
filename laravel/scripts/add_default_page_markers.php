#!/usr/bin/env php
<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__); // /path/.../laravel

$roots = [];

$varianceUploads = realpath($projectRoot . '/../variance/uploads');
if ($varianceUploads !== false) {
    $roots['variance/uploads'] = $varianceUploads;
}

$storageUploads = realpath($projectRoot . '/storage/app/public/uploads');
if ($storageUploads !== false) {
    $roots['storage/app/public/uploads'] = $storageUploads;
}

if (empty($roots)) {
    fwrite(STDERR, "No uploads directories found.\n");
    exit(1);
}

$totalUpdated = 0;
$totalSkipped = 0;

foreach ($roots as $label => $root) {
    foreach (glob($root . '/*', GLOB_ONLYDIR) as $authorDir) {
        $author = basename($authorDir);
        foreach (glob($authorDir . '/*', GLOB_ONLYDIR) as $workDir) {
            $work = basename($workDir);
            $comparisonRoot = $workDir . '/comparisons';
            if (!is_dir($comparisonRoot)) {
                continue;
            }

            foreach (glob($comparisonRoot . '/*', GLOB_ONLYDIR) as $comparisonDir) {
                $pair = findComparisonPair($comparisonDir);
                if ($pair === null) {
                    continue;
                }

                foreach ([
                    'source' => $pair[0],
                    'target' => $pair[1],
                ] as $role => $versionFolder) {
                    $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
                    $filePath = $comparisonDir . '/' . $fileName;

                    if (!is_file($filePath)) {
                        continue;
                    }

                    $html = file_get_contents($filePath);
                    if ($html === false) {
                        fwrite(STDERR, "Failed to read {$filePath}\n");
                        continue;
                    }

                    if (stripos($html, 'page-marker') !== false) {
                        $totalSkipped++;
                        continue;
                    }

                    $images = findImageNumbers($workDir, $versionFolder);
                    if (empty($images)) {
                        $totalSkipped++;
                        continue;
                    }

                    $firstImage = min($images);
                    $markerHtml = buildMarker($firstImage, $role);
                    $newHtml = $markerHtml . "\n" . ltrim($html);

                    if (file_put_contents($filePath, $newHtml) === false) {
                        fwrite(STDERR, "Failed to write {$filePath}\n");
                        continue;
                    }

                    $totalUpdated++;
                    $comparisonId = basename($comparisonDir);
                    echo "[{$label}] inserted default marker for {$role} in {$author}/{$work}/comparisons/{$comparisonId}/{$fileName}" . PHP_EOL;
                }
            }
        }
    }
}

echo "Done. Updated {$totalUpdated} files; skipped {$totalSkipped}." . PHP_EOL;

function findComparisonPair(string $comparisonDir): ?array
{
    $candidates = glob($comparisonDir . '/*.xml');
    foreach ($candidates as $path) {
        $base = pathinfo($path, PATHINFO_FILENAME);
        if (preg_match('/^([^-]+)-([^-]+)$/', $base, $matches)) {
            return [$matches[1], $matches[2]];
        }
    }

    // fallback: detect patterns like name-runX? remove suffix after last dash
    foreach ($candidates as $path) {
        $base = pathinfo($path, PATHINFO_FILENAME);
        $base = preg_replace('/\.(raw|clean)$/', '', $base);
        if (preg_match('/^([^-]+)-([^-]+)$/', $base, $matches)) {
            return [$matches[1], $matches[2]];
        }
    }

    return null;
}

function findImageNumbers(string $workDir, string $versionFolder): array
{
    $searchDirs = [];
    $searchDirs[] = $workDir . '/' . $versionFolder;

    $lower = strtolower($versionFolder);
    if ($lower !== $versionFolder) {
        $searchDirs[] = $workDir . '/' . $lower;
    }

    foreach ($searchDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        $glob = glob($dir . '/img_*_*.*');
        $numbers = [];
        foreach ($glob as $file) {
            $basename = basename($file);
            if (preg_match('/_(\d+)\.(?:jpg|jpeg|png)$/i', $basename, $match)) {
                $numbers[] = (int) $match[1];
            }
        }

        if (!empty($numbers)) {
            $numbers = array_values(array_unique($numbers));
            sort($numbers, SORT_NUMERIC);
            return $numbers;
        }
    }

    return [];
}

function buildMarker(int $imageNumber, string $role): string
{
    $orientation = $role === 'source' ? 'right' : 'left';
    $padded = str_pad((string) $imageNumber, 3, '0', STR_PAD_LEFT);
    return sprintf(
        '<span class="page-marker" data-image-name="%1$s"><span class="page-number">%1$s</span><img src="/img/settings/page_%2$s.svg" /></span>',
        $padded,
        $orientation
    );
}
