<?php

namespace App\Console\Commands;

use App\Models\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BackfillVersionTeiFromTxt extends Command
{
    protected $signature = 'variance:backfill-version-tei-from-txt
        {--apply : Write generated TEI-XML files into Laravel storage}
        {--version-id=* : Limit to one or more version IDs}';

    protected $description = 'Backfill missing version TEI-XML files from existing TXT files.';

    public function handle(): int
    {
        $versionIds = collect((array) $this->option('version-id'))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        $versions = Version::query()
            ->with('work.author')
            ->when($versionIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $versionIds->all()))
            ->orderBy('id')
            ->get();

        if ($versions->isEmpty()) {
            $this->components->warn('Aucune version trouvée pour cette sélection.');
            return self::SUCCESS;
        }

        $rows = [];
        $written = 0;

        foreach ($versions as $version) {
            $txtRelative = "uploads/versions/{$version->folder}.txt";
            $xmlRelative = "uploads/versions/{$version->folder}.xml";
            $txtPath = storage_path("app/public/{$txtRelative}");
            $xmlPath = storage_path("app/public/{$xmlRelative}");

            $status = 'missing-txt';
            $action = 'skip';
            $chars = '—';

            if (is_file($txtPath)) {
                $status = is_file($xmlPath) ? 'xml-exists' : 'txt-only';
                $txt = $this->readFileAsUtf8($txtPath);

                if (!$this->isLikelyTextContent($txt)) {
                    $status = 'invalid-txt';
                } else {
                    $chars = mb_strlen($txt, 'UTF-8');
                    $tei = $this->buildLegacyTxt2TeiXml(
                        $txt,
                        $version->work?->title ?: $version->name,
                        $this->resolveXmlIdentifierNumber($version),
                        $version->work?->author?->name ?: '',
                        $version->name
                    );

                    if ($status === 'txt-only' && $this->option('apply')) {
                        Storage::disk('public')->put($xmlRelative, $tei);
                        Cache::forget("versions:index:work:{$version->work_id}");
                        $action = 'written';
                        $written++;
                    }
                }
            }

            $rows[] = [
                $version->id,
                $version->folder,
                $version->name,
                $status,
                $action,
                $chars,
            ];
        }

        $this->table(
            ['ID', 'Dossier', 'Version', 'Statut', 'Action', 'Car.'],
            $rows
        );

        $txtOnly = collect($rows)->where(3, 'txt-only')->count();
        $xmlExists = collect($rows)->where(3, 'xml-exists')->count();
        $missingTxt = collect($rows)->where(3, 'missing-txt')->count();
        $invalidTxt = collect($rows)->where(3, 'invalid-txt')->count();

        $this->newLine();
        $this->components->info(sprintf(
            'Résumé : %d TXT sans XML, %d XML déjà présents, %d TXT absents, %d TXT invalides.',
            $txtOnly,
            $xmlExists,
            $missingTxt,
            $invalidTxt
        ));

        if ($this->option('apply')) {
            $this->components->info(sprintf('%d fichier(s) TEI-XML généré(s).', $written));
        } else {
            $this->components->warn('Mode dry-run : aucune écriture. Utilisez --apply pour générer les TEI-XML manquants.');
        }

        return self::SUCCESS;
    }

    private function resolveXmlIdentifierNumber(Version $version): int
    {
        if (preg_match('/^(\d+)/', $version->folder, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return max(1, (int) $version->id);
    }

    private function readFileAsUtf8(string $absPath): string
    {
        $bytes = file_get_contents($absPath);
        if ($bytes === false) {
            throw new \RuntimeException("Impossible de lire le fichier source : {$absPath}");
        }

        $enc = mb_detect_encoding(
            $bytes,
            ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'],
            true
        ) ?: 'Windows-1252';

        $utf8 = $this->convertToUtf8($bytes, $enc);
        $utf8 = $this->preferMacRomanIfCleaner($bytes, $enc, $utf8);

        if (!mb_check_encoding($utf8, 'UTF-8')) {
            $utf8 = $this->convertToUtf8($bytes, 'Macintosh');
        }

        return str_replace(["\r\n", "\r"], "\n", $utf8);
    }

    private function convertToUtf8(string $bytes, string $sourceEncoding): string
    {
        $source = trim($sourceEncoding) !== '' ? $sourceEncoding : 'Windows-1252';

        if (strcasecmp($source, 'Macintosh') === 0 && function_exists('iconv')) {
            $converted = @iconv('MACINTOSH', 'UTF-8//IGNORE', $bytes);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return mb_convert_encoding($bytes, 'UTF-8', $source);
    }

    private function preferMacRomanIfCleaner(string $bytes, string $detectedEncoding, string $decoded): string
    {
        $normalized = strtoupper(trim($detectedEncoding));
        if (!in_array($normalized, ['WINDOWS-1252', 'ISO-8859-1', 'ASCII'], true)) {
            return $decoded;
        }

        $macDecoded = $this->convertToUtf8($bytes, 'Macintosh');
        if ($macDecoded === '' || !mb_check_encoding($macDecoded, 'UTF-8')) {
            return $decoded;
        }

        $decodedScore = $this->decodedTextNoiseScore($decoded);
        $macScore = $this->decodedTextNoiseScore($macDecoded);

        return ($macScore + 3) < $decodedScore ? $macDecoded : $decoded;
    }

    private function decodedTextNoiseScore(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        $score = 0;
        $score += preg_match_all('/[\x{0080}-\x{009F}]/u', $content) * 10;
        $score += preg_match_all('/[\x{FFFD}]/u', $content) * 6;
        $score += preg_match_all('/[\x{00D5}\x{0152}\x{0153}\x{02C6}\x{0160}\x{2039}\x{203A}\x{0178}\x{017E}\x{2122}]/u', $content) * 2;

        return $score;
    }

    private function isLikelyTextContent(string $content): bool
    {
        if ($content === '' || str_contains($content, "\0")) {
            return false;
        }

        $sample = mb_substr($content, 0, 12000, 'UTF-8');
        $len = mb_strlen($sample, 'UTF-8');
        if ($len === 0) {
            return false;
        }

        $controls = 0;
        $chars = preg_split('//u', $sample, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $ord = mb_ord($ch, 'UTF-8');
            if ($ord === false) {
                continue;
            }
            if ($ord < 32 && !in_array($ord, [9, 10, 13], true)) {
                $controls++;
            }
        }

        return ($controls / max(1, $len)) < 0.02;
    }

    private function buildLegacyTxt2TeiXml(
        string $txt,
        string $title,
        int $versionNumber,
        string $author = '',
        string $versionName = ''
    ): string {
        $txt = $this->normalizeTxt2TeiCharacters($txt);
        $txt = $this->collapseTxt2TeiSpacesAndTabs($txt);

        $escapedText = htmlspecialchars($txt, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<TEI xml:id=\"v{$versionNumber}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n"
            . $this->buildTeiHeaderXml($title, $author, $versionName)
            . "  <text>\n"
            . "    <body>\n"
            . "      <p>{$escapedText}</p>\n"
            . "    </body>\n"
            . "  </text>\n"
            . "</TEI>\n";
    }

    private function buildTeiHeaderXml(
        string $title,
        string $author = '',
        string $versionName = '',
        string $editor = '',
        string $publisher = '',
        string $publicationDate = '',
        string $sourceDate = ''
    ): string {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $lines = [
            "  <teiHeader>",
            "    <fileDesc>",
            "      <titleStmt>",
        ];

        if ($title !== '') {
            $lines[] = "        <title>{$escape($title)}</title>";
        }
        if ($versionName !== '') {
            $lines[] = "        <title type=\"version\">{$escape($versionName)}</title>";
        }
        if ($author !== '') {
            $lines[] = "        <author>{$escape($author)}</author>";
        }
        if ($editor !== '') {
            $lines[] = "        <editor>{$escape($editor)}</editor>";
        }
        $lines[] = "      </titleStmt>";

        if ($publisher !== '' || $publicationDate !== '') {
            $lines[] = "      <publicationStmt>";
            if ($publisher !== '') {
                $lines[] = "        <publisher>{$escape($publisher)}</publisher>";
            }
            if ($publicationDate !== '') {
                $lines[] = "        <date>{$escape($publicationDate)}</date>";
            }
            $lines[] = "      </publicationStmt>";
        }

        $lines[] = "      <sourceDesc>";
        $lines[] = "        <bibl>";
        if ($author !== '') {
            $lines[] = "          <author>{$escape($author)}</author>";
        }
        if ($title !== '') {
            $lines[] = "          <title>{$escape($title)}</title>";
        }
        if ($versionName !== '') {
            $lines[] = "          <title type=\"version\">{$escape($versionName)}</title>";
        }
        if ($publisher !== '') {
            $lines[] = "          <publisher>{$escape($publisher)}</publisher>";
        }
        if ($sourceDate !== '') {
            $lines[] = "          <date>{$escape($sourceDate)}</date>";
        }
        $lines[] = "        </bibl>";
        $lines[] = "      </sourceDesc>";
        $lines[] = "    </fileDesc>";
        $lines[] = "  </teiHeader>";

        return implode("\n", $lines) . "\n";
    }

    private function normalizeTxt2TeiCharacters(string $txt): string
    {
        return str_replace(
            [
                "\u{2013}",
                "\u{2212}",
                "\u{2010}",
                "\u{2011}",
                "\u{00AD}",
                "\u{2026}",
                "\u{201C}",
                "\u{201D}",
                "\u{201E}",
                "\u{201F}",
                "\u{2018}",
                "\u{2019}",
                "\u{201A}",
                "\u{2032}",
                "\u{2033}",
                "\u{00A0}",
                "\u{202F}",
            ],
            [
                '-',
                '-',
                '-',
                '-',
                '',
                '...',
                '"',
                '"',
                '"',
                '"',
                "'",
                "'",
                "'",
                "'",
                '"',
                ' ',
                ' ',
            ],
            $txt
        );
    }

    private function collapseTxt2TeiSpacesAndTabs(string $txt): string
    {
        $txt = preg_replace("/\t+/u", ' ', $txt) ?? $txt;
        $lines = preg_split("/\n/u", $txt) ?: [$txt];
        $normalizedLines = array_map(function (string $line): string {
            $line = preg_replace('/[ ]{3,}/u', '  ', $line) ?? $line;
            return rtrim($line, ' ');
        }, $lines);

        $txt = implode("\n", $normalizedLines);
        $txt = preg_replace("/\n{3,}/u", "\n\n", $txt) ?? $txt;

        return trim($txt);
    }
}
