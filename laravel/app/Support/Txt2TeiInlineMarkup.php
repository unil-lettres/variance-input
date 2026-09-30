<?php

namespace App\Support;

final class Txt2TeiInlineMarkup
{
    public static function escapeWithItalicMarkup(string $text): string
    {
        return self::escapeWithLegacyInlineMarkup($text);
    }

    public static function escapeWithLegacyInlineMarkup(string $text): string
    {
        $parts = [];
        $stack = [];
        $buffer = '';
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if (! self::isMarker($char)) {
                $buffer .= $char;
                continue;
            }

            if ($buffer !== '') {
                $parts[] = ['type' => 'text', 'value' => $buffer];
            }
            $buffer = '';

            $markerPartIndex = count($parts);
            $parts[] = [
                'type' => 'marker',
                'marker' => $char,
                'convert' => false,
                'tag' => null,
            ];

            $top = array_key_last($stack);
            if ($top !== null && $stack[$top]['marker'] === $char) {
                $openingPartIndex = $stack[$top]['part_index'];
                array_pop($stack);

                $parts[$openingPartIndex]['convert'] = true;
                $parts[$openingPartIndex]['tag'] = 'open';
                $parts[$markerPartIndex]['convert'] = true;
                $parts[$markerPartIndex]['tag'] = 'close';
                continue;
            }

            $crossedStackIndex = null;
            foreach ($stack as $stackIndex => $entry) {
                if ($entry['marker'] === $char) {
                    $crossedStackIndex = $stackIndex;
                    break;
                }
            }

            if ($crossedStackIndex !== null) {
                array_splice($stack, $crossedStackIndex, 1);
                continue;
            }

            $stack[] = [
                'marker' => $char,
                'part_index' => $markerPartIndex,
            ];
        }

        if ($buffer !== '') {
            $parts[] = ['type' => 'text', 'value' => $buffer];
        }

        return implode('', array_map(self::renderPart(...), $parts));
    }

    private static function isMarker(string $char): bool
    {
        return $char === '\\' || $char === '^';
    }

    /**
     * @param array{type:string,value?:string,marker?:string,convert?:bool,tag?:string|null} $part
     */
    private static function renderPart(array $part): string
    {
        if ($part['type'] === 'text') {
            return self::escapeXml($part['value'] ?? '');
        }

        $marker = $part['marker'] ?? '';
        if (($part['convert'] ?? false) !== true) {
            return self::escapeXml($marker);
        }

        $tags = [
            '\\' => ['open' => '<emph>', 'close' => '</emph>'],
            '^' => ['open' => '<sup>', 'close' => '</sup>'],
        ];

        return $tags[$marker][$part['tag']];
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
