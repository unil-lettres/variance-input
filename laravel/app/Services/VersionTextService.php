<?php

namespace App\Services;

use App\Support\Txt2TeiInlineMarkup;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class VersionTextService
{
    /** Detect + convert arbitrary bytes to UTF-8 LF. */
    public function readFileAsUtf8(string $absPath, ?string $hint = null, bool $normalizeLineEndings = true): string
    {
        $bytes = file_get_contents($absPath);
        if ($bytes === false) {
            throw new \RuntimeException("Impossible de lire le fichier source : {$absPath}");
        }

        $hintEncoding = $this->normalizeSourceEncodingHint($hint);
        $enc = $hintEncoding;
        if ($enc === null) {
            $enc = mb_detect_encoding(
                $bytes,
                ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'],
                true
            ) ?: null;
        }
        $enc ??= 'Windows-1252';

        $utf8 = $this->convertToUtf8($bytes, $enc);

        // For unknown/no-BOM legacy files, prefer Mac Roman when decoded text quality is better.
        if ($hintEncoding === null) {
            $utf8 = $this->preferMacRomanIfCleaner($bytes, $enc, $utf8);
        }

        // Last-chance fallback for files reported as unknown/no BOM:
        // many old Mac exports are Mac Roman.
        if (! mb_check_encoding($utf8, 'UTF-8')) {
            $utf8 = $this->convertToUtf8($bytes, 'Macintosh');
        }

        return $normalizeLineEndings
            ? str_replace(["\r\n", "\r"], "\n", $utf8)
            : $utf8;
    }

    public function normalizeSourceEncodingHint(?string $hint): ?string
    {
        if (! $hint) {
            return null;
        }

        $h = strtoupper(trim($hint));
        if ($h === '') {
            return null;
        }

        if (str_contains($h, 'UTF-8')) {
            return 'UTF-8';
        }
        if (str_contains($h, 'MAC') && str_contains($h, 'ROMAN')) {
            return 'Macintosh';
        }
        if (str_contains($h, 'MACINTOSH')) {
            return 'Macintosh';
        }
        if (str_contains($h, 'WINDOWS-1252') || str_contains($h, 'CP1252')) {
            return 'Windows-1252';
        }
        if (str_contains($h, 'ISO-8859')) {
            return 'ISO-8859-1';
        }
        if (str_contains($h, 'ASCII')) {
            return 'ASCII';
        }

        return null;
    }

    /**
     * Heuristic guard to reject obvious binary uploads while accepting legacy encodings.
     */
    public function isLikelyTextContent(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        if (strpos($content, "\0") !== false) {
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
            // Allow tab/newline/carriage return; count other C0 controls.
            if ($ord < 32 && ! in_array($ord, [9, 10, 13], true)) {
                $controls++;
            }
        }

        // If >2% control chars, likely binary.
        return ($controls / max(1, $len)) < 0.02;
    }

    public function buildLegacyTxt2TeiXml(
        string $txt,
        string $title,
        int $versionNumber,
        string $author = '',
        string $versionName = ''
    ): string {
        $txt = $this->normalizeTxt2TeiCharacters($txt);
        $txt = $this->collapseTxt2TeiSpacesAndTabs($txt);

        $escapedText = Txt2TeiInlineMarkup::escapeWithLegacyInlineMarkup($txt);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<TEI xml:id=\"v{$versionNumber}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n"
            .$this->buildTeiHeaderXml($title, $author, $versionName)
            ."  <text>\n"
            ."    <body>\n"
            ."      <p>{$escapedText}</p>\n"
            ."    </body>\n"
            ."  </text>\n"
            ."</TEI>\n";
    }

    /**
     * Build the canonical editable TXT representation from a version TEI.
     *
     * The TEI remains the editorial source of truth after editor saves. The
     * TXT is a derived artifact consumed by the version reader and by legacy
     * workflows. Inline editorial markup is converted back to the legacy text
     * markers understood by the application: \...\ for italics and ^...^ for
     * superscript. Pagination markers (<pb>) are intentionally omitted because
     * the reader consumes them through the sidecar.
     */
    public function buildLegacyTxtFromTeiXml(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new \InvalidArgumentException('TEI XML invalide.');
        }

        $xpath = new DOMXPath($dom);
        $body = $xpath->query('//*[local-name()="body"]')->item(0);
        if (! $body instanceof DOMNode) {
            throw new \InvalidArgumentException('TEI XML sans élément body.');
        }

        $txt = $this->renderTeiChildrenAsLegacyTxt($body);
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);
        $txt = preg_replace("/[ \t]+\n/u", "\n", $txt) ?? $txt;
        $txt = preg_replace("/\n[ \t]+/u", "\n", $txt) ?? $txt;
        $txt = preg_replace("/\n{3,}/u", "\n\n", $txt) ?? $txt;

        return trim($txt);
    }

    private function renderTeiChildrenAsLegacyTxt(DOMNode $node): string
    {
        $txt = '';
        foreach ($node->childNodes as $child) {
            $txt .= $this->renderTeiNodeAsLegacyTxt($child);
        }

        return $txt;
    }

    private function renderTeiNodeAsLegacyTxt(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return $node->nodeValue ?? '';
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->localName);
        if (in_array($name, ['teiheader', 'pb'], true)) {
            return '';
        }

        if (in_array($name, ['lb', 'br'], true)) {
            return "\n";
        }

        $inner = $this->renderTeiChildrenAsLegacyTxt($node);

        if (in_array($name, ['emph', 'em', 'hi'], true)) {
            $rend = strtolower((string) $node->getAttribute('rend'));
            $style = strtolower((string) $node->getAttribute('style'));
            if ($name !== 'hi'
                || str_contains($rend, 'italic')
                || str_contains($rend, 'italique')
                || str_contains($style, 'italic')) {
                return '\\'.$inner.'\\';
            }
        }

        if ($name === 'sup') {
            return '^'.$inner.'^';
        }

        if (in_array($name, ['p', 'head', 'l', 'item'], true)) {
            return $this->withTrailingNewline($inner);
        }

        if (in_array($name, ['div', 'lg', 'list'], true)) {
            return $this->withTrailingNewline($inner);
        }

        return $inner;
    }

    private function withTrailingNewline(string $txt): string
    {
        if ($txt === '' || str_ends_with($txt, "\n")) {
            return $txt;
        }

        return $txt."\n";
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
        if (! in_array($normalized, ['WINDOWS-1252', 'ISO-8859-1', 'ASCII'], true)) {
            return $decoded;
        }

        $macDecoded = $this->convertToUtf8($bytes, 'Macintosh');
        if ($macDecoded === '' || ! mb_check_encoding($macDecoded, 'UTF-8')) {
            return $decoded;
        }

        $decodedScore = $this->decodedTextNoiseScore($decoded);
        $macScore = $this->decodedTextNoiseScore($macDecoded);

        // Pick Mac Roman when it clearly removes control/mojibake noise.
        return ($macScore + 3) < $decodedScore ? $macDecoded : $decoded;
    }

    private function decodedTextNoiseScore(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        $score = 0;
        $score += preg_match_all('/[\x{0080}-\x{009F}]/u', $content) * 10; // C1 controls
        $score += preg_match_all('/[\x{FFFD}]/u', $content) * 6; // replacement chars
        $score += preg_match_all('/[\x{00D5}\x{0152}\x{0153}\x{02C6}\x{0160}\x{2039}\x{203A}\x{0178}\x{017E}\x{2122}]/u', $content) * 2;

        return $score;
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
            '  <teiHeader>',
            '    <fileDesc>',
            '      <titleStmt>',
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
        $lines[] = '      </titleStmt>';

        if ($publisher !== '' || $publicationDate !== '') {
            $lines[] = '      <publicationStmt>';
            if ($publisher !== '') {
                $lines[] = "        <publisher>{$escape($publisher)}</publisher>";
            }
            if ($publicationDate !== '') {
                $lines[] = "        <date>{$escape($publicationDate)}</date>";
            }
            $lines[] = '      </publicationStmt>';
        }

        $lines[] = '      <sourceDesc>';
        $lines[] = '        <bibl>';
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
        $lines[] = '        </bibl>';
        $lines[] = '      </sourceDesc>';
        $lines[] = '    </fileDesc>';
        $lines[] = '  </teiHeader>';

        return implode("\n", $lines)."\n";
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
                "\u{02BC}",
                "\u{00B4}",
                "\u{02C8}",
                "\u{00A0}",
                "\u{2002}",
                "\u{2003}",
                "\u{2009}",
                "\u{202F}",
                "\u{200B}",
                "\u{FEFF}",
                "\r\n",
                "\r",
            ],
            [
                "\u{2014}",
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
                "'",
                ' ',
                ' ',
                ' ',
                ' ',
                ' ',
                '',
                '',
                "\n",
                "\n",
            ],
            $txt
        );
    }

    private function collapseTxt2TeiSpacesAndTabs(string $txt): string
    {
        $txt = str_replace("\t", ' ', $txt);

        return preg_replace('/ {2,}/', ' ', $txt) ?? $txt;
    }
}
