<?php

namespace App\Support;

final class Txt2TeiInlineMarkup
{
    public static function escapeWithItalicMarkup(string $text): string
    {
        $markerCount = substr_count($text, '\\');
        $markersToConvert = $markerCount - ($markerCount % 2);
        $convertedMarkers = 0;
        $escaped = '';
        $buffer = '';
        $insideItalic = false;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char !== '\\' || $convertedMarkers >= $markersToConvert) {
                $buffer .= $char;
                continue;
            }

            $escaped .= self::escapeXml($buffer);
            $buffer = '';
            $escaped .= $insideItalic ? '</emph>' : '<emph>';
            $insideItalic = !$insideItalic;
            $convertedMarkers++;
        }

        return $escaped . self::escapeXml($buffer);
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
