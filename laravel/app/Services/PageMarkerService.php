<?php

namespace App\Services;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class PageMarkerService
{
    private const MARKER_TEMPLATE = '<span class="page-marker" data-image-name="%s"><span class="page-number">%s</span><img src="%s" /></span>';

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

        $version->loadMissing('work.author');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder   = $version->work->folder ?? null;

        if (!$authorFolder || !$workFolder) {
            throw new \RuntimeException('Version incomplète : dossier auteur/œuvre introuvable.');
        }

        $summary = [
            'source' => ['comparisons' => 0, 'processed' => 0, 'inserted' => 0, 'missed' => 0, 'skipped' => 0, 'details' => []],
            'target' => ['comparisons' => 0, 'processed' => 0, 'inserted' => 0, 'missed' => 0, 'skipped' => 0, 'details' => []],
        ];

        $comparisons = Comparison::where('source_id', $version->id)
            ->orWhere('target_id', $version->id)
            ->get();

        foreach ($comparisons as $comparison) {
            if ($comparison->source_id === $version->id) {
                $summary['source']['comparisons']++;
                $result = $this->applyToComparison($entries, $authorFolder, $workFolder, $comparison, 'source', $clear);
                $this->mergeDetail($summary['source'], $result);
            }

            if ($comparison->target_id === $version->id) {
                $summary['target']['comparisons']++;
                $result = $this->applyToComparison($entries, $authorFolder, $workFolder, $comparison, 'target', $clear);
                $this->mergeDetail($summary['target'], $result);
            }
        }

        return $summary;
    }

    /** Count page markers currently present for a version across all comparisons. */
    public function countMarkers(Version $version): array
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work->author->folder ?? null;
        $workFolder   = $version->work->folder ?? null;

        if (!$authorFolder || !$workFolder) {
            return [
                'total'  => 0,
                'source' => ['comparisons' => 0, 'markers' => 0],
                'target' => ['comparisons' => 0, 'markers' => 0],
            ];
        }

        $summary = [
            'total'  => 0,
            'source' => ['comparisons' => 0, 'markers' => 0],
            'target' => ['comparisons' => 0, 'markers' => 0],
        ];

        $comparisons = Comparison::where('source_id', $version->id)
            ->orWhere('target_id', $version->id)
            ->get();

        foreach ($comparisons as $comparison) {
            if ($comparison->source_id === $version->id) {
                $summary['source']['comparisons']++;
                $markers = $this->countMarkersForRole($authorFolder, $workFolder, $comparison, 'source');
                $summary['source']['markers'] = max($summary['source']['markers'], $markers);
                $summary['total'] = max($summary['total'], $markers);
            }

            if ($comparison->target_id === $version->id) {
                $summary['target']['comparisons']++;
                $markers = $this->countMarkersForRole($authorFolder, $workFolder, $comparison, 'target');
                $summary['target']['markers'] = max($summary['target']['markers'], $markers);
                $summary['total'] = max($summary['total'], $markers);
            }
        }

        return $summary;
    }

    /* ───────────────────────── INTERNALS ───────────────────────── */

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

    private function applyToComparison(array $entries, string $authorFolder, string $workFolder, Comparison $comparison, string $role, bool $clearExisting): array
    {
        $fileName = $role === 'source' ? 'source.xhtml' : 'target.xhtml';
        $paths    = $this->candidatePaths($authorFolder, $workFolder, $comparison, $fileName);

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
        $result      = $this->insertMarkers($html, $entries, $orientation);

        foreach ($existing as $path) {
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

        $oneLineRegex = '/^\s*(\d{1,4})\s+([0-9]{1,4}[a-z]?|[ivxlcdm]+)\s+(.+)$/iu';
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
                    'phrase' => trim($match[3]),
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
        return preg_replace('/<span\s+class="page-marker"[^>]*?>.*?<\/span>/is', '', $html);
    }

    private function insertMarkers(string $html, array $entries, string $orientation): array
    {
        [$shadow, $map] = $this->buildIndexedPlaintext($html);
        $posShadow = 0;
        $inserted  = 0;
        $misses    = [];

        foreach ($entries as $entry) {
            $image = $entry['image'];
            if (!preg_match('/^\d+$/', $image)) {
                $misses[] = ['entry' => $entry, 'reason' => 'image_non_numerique'];
                continue;
            }

            $phrase = trim((string) $entry['phrase']);
            if ($phrase === '') {
                $misses[] = ['entry' => $entry, 'reason' => 'phrase_vide'];
                continue;
            }
            $pattern = $this->buildFlexibleRegex($phrase);

            $match = $this->findMatch($pattern, $shadow, $posShadow);
            if (!$match) {
                $collapsed = preg_replace('/\s+/u', ' ', $phrase ?? '');
                $match = $this->findMatch($this->buildFlexibleRegex($collapsed), $shadow, $posShadow);
            }

            if (!$match) {
                $misses[] = ['entry' => $entry, 'reason' => 'phrase_introuvable'];
                continue;
            }

            [$matchedText, $shadowOffset] = $match;
            if (!array_key_exists($shadowOffset, $map)) {
                $misses[] = ['entry' => $entry, 'reason' => 'mapping_introuvable'];
                continue;
            }

            $htmlOffset = $map[$shadowOffset];
            $insertAt   = $this->moveBeforeOpeningChain($html, $htmlOffset);

            $marker = sprintf(
                self::MARKER_TEMPLATE,
                str_pad($image, 3, '0', STR_PAD_LEFT),
                $this->formatPageLabel($entry['page']),
                $orientation === 'left' ? '/img/settings/page_left.svg' : '/img/settings/page_right.svg'
            );

            $html = substr($html, 0, $insertAt) . $marker . substr($html, $insertAt);
            $inserted++;

            [$shadow, $map] = $this->buildIndexedPlaintext($html);
            $posShadow = $this->shadowIndexForHtmlOffset($map, $insertAt + strlen($marker));
        }

        return [
            'html'     => $html,
            'inserted' => $inserted,
            'misses'   => $misses,
        ];
    }

    private function buildIndexedPlaintext(string $src): array
    {
        $shadow = '';
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
            $map[] = $offset;
            $offset += $glyphLen;
        }

        return [$shadow, $map];
    }

    private function buildFlexibleRegex(string $phrase): string
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
        return '/' . $pattern . '/ius';
    }

    private function findMatch(string $pattern, string $subject, int $offset): ?array
    {
        if (!preg_match($pattern, $subject, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }

        return [$matches[0][0], $matches[0][1]];
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

    private function shadowIndexForHtmlOffset(array $map, int $offset): int
    {
        $low = 0;
        $high = count($map) - 1;
        $answer = count($map);

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($map[$mid] >= $offset) {
                $answer = $mid;
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return $answer;
    }
}
