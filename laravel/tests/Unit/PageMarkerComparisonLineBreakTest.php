<?php

namespace Tests\Unit;

use App\Services\PageMarkerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PageMarkerComparisonLineBreakTest extends TestCase
{
    public static function lineBreakTags(): array
    {
        return [
            'html' => ['<br>'],
            'xhtml' => ['<br/>'],
            'spaced xhtml' => ['<br />'],
            'uppercase' => ['<BR/>'],
            'attributes' => ['<br class="verse-break" />'],
        ];
    }

    #[DataProvider('lineBreakTags')]
    public function test_comparison_anchor_crosses_line_breaks_without_changing_the_xhtml(string $break): void
    {
        $prefix = '<a class="span_c">Préambule déjà &amp; cœur.</a><a class="span_r">';
        $text = 'Chant (on est absent)' . $break . $break . 'Ennemi, reviens vers nous.</a>';
        $html = $prefix . $text;

        $result = $this->insert($html, [[
            'char_index' => 900,
            'image_code' => '339',
            'page' => '339',
            'phrase' => 'Chant (on est absent) Ennemi, reviens vers nous.',
        ]]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame($prefix . $this->marker('339') . $text, $result['html']);
    }

    public function test_line_break_fallback_maps_decoded_entities_to_original_utf8_bytes(): void
    {
        $prefix = '<a>Été, cœur &amp; forêt.</a><a>';
        $text = '&#201;cho &amp; lune (d’absence)<br/>Reviens demain.</a>';

        $result = $this->insert($prefix . $text, [[
            'char_index' => 700,
            'image_code' => '001',
            'phrase' => 'Écho & lune (d’absence) Reviens demain.',
        ]]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame($prefix . $this->marker('001') . $text, $result['html']);
    }

    public function test_multiple_fallback_markers_keep_byte_positions_after_previous_insertions(): void
    {
        $first = 'Chant (un)<br/>Écho &amp; lune';
        $second = 'Chant (deux)<br/>Très loin dans la forêt';
        $html = '<a>' . $first . '</a><a>' . $second . '</a>';

        $result = $this->insert($html, [
            ['char_index' => 0, 'image_code' => '001', 'phrase' => 'Chant (un) Écho & lune'],
            ['char_index' => 30, 'image_code' => '002', 'phrase' => 'Chant (deux) Très loin dans la forêt'],
        ]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(
            '<a>' . $this->marker('001') . $first . '</a><a>' . $this->marker('002') . $second . '</a>',
            $result['html']
        );
    }

    public function test_lazy_fallback_uses_original_html_after_a_canonical_marker_has_been_inserted(): void
    {
        $first = 'Préface &amp; éclaircie.';
        $second = 'Chant (deux)<br/>Très loin dans la forêt';
        $third = 'Épilogue sans rupture.';
        $html = '<a>' . $first . '</a><a>' . $second . '</a><a>' . $third . '</a>';

        $result = $this->insert($html, [
            ['char_index' => 0, 'image_code' => '001', 'phrase' => 'Préface & éclaircie.'],
            ['char_index' => 25, 'image_code' => '002', 'phrase' => 'Chant (deux) Très loin dans la forêt'],
            ['char_index' => 65, 'image_code' => '003', 'phrase' => 'Épilogue sans rupture.'],
        ]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(3, $result['inserted']);
        $this->assertSame(
            '<a>' . $this->marker('001') . $first . '</a>'
                . '<a>' . $this->marker('002') . $second . '</a>'
                . '<a>' . $this->marker('003') . $third . '</a>',
            $result['html']
        );
    }

    public function test_legacy_anchor_with_glued_lines_still_matches(): void
    {
        $text = 'Chant (on est absent)<br/><br/>Ennemi, reviens vers nous.';

        $result = $this->insert('<a>' . $text . '</a>', [[
            'char_index' => 900,
            'image_code' => '339',
            'phrase' => 'Chant (on est absent)Ennemi, reviens vers nous.',
        ]]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame('<a>' . $this->marker('339') . $text . '</a>', $result['html']);
    }

    public function test_offset_only_sidecar_keeps_the_original_tag_stripped_character_index(): void
    {
        $prefix = '<a>Préface<br/><br/>Puis ';
        $result = $this->insert($prefix . 'épilogue.</a>', [[
            'char_index' => mb_strlen('PréfacePuis ', 'UTF-8'),
            'image_code' => '002',
        ]]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame($prefix . $this->marker('002') . 'épilogue.</a>', $result['html']);
    }

    public function test_fallback_preserves_words_split_by_italics_superscript_and_comparison_anchors(): void
    {
        $text = 'Chant (absent)<br/>Inter<em>na</em>tional 1<sup>er</sup> rang et pa</a><a>role';

        $result = $this->insert('<a>' . $text . '</a>', [[
            'char_index' => 0,
            'image_code' => '001',
            'phrase' => 'Chant (absent) International 1er rang et parole',
        ]]);

        $this->assertSame([], $result['misses']);
        $this->assertSame(1, $result['inserted']);
        $this->assertSame('<a>' . $this->marker('001') . $text . '</a>', $result['html']);
    }

    public function test_inline_tags_do_not_create_whitespace_to_match_a_different_phrase(): void
    {
        $html = '<a>Chant (absent)<em>Ennemi</em> qui revient</a>';

        $result = $this->insert($html, [[
            'char_index' => 0,
            'image_code' => '001',
            'phrase' => 'Chant (absent) Ennemi qui revient',
        ]]);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame($html, $result['html']);
        $this->assertCount(1, $result['misses']);
        $this->assertSame('index_introuvable', $result['misses'][0]['reason']);
    }

    public function test_missing_phrase_is_not_inserted_at_a_stale_offset(): void
    {
        $html = '<a>Chant (absent)<br/>Ennemi qui revient</a>';

        $result = $this->insert($html, [[
            'char_index' => 5,
            'image_code' => '001',
            'phrase' => 'Texte complètement absent de cette comparaison',
        ]]);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame($html, $result['html']);
        $this->assertCount(1, $result['misses']);
        $this->assertSame('index_introuvable', $result['misses'][0]['reason']);
    }

    public function test_replacing_a_recovered_marker_is_idempotent(): void
    {
        $html = '<a>Chant (on est absent)<br/><br/>Ennemi qui revient</a>';
        $markers = [[
            'char_index' => 0,
            'image_code' => '339',
            'phrase' => 'Chant (on est absent) Ennemi qui revient',
        ]];

        $first = $this->insert($html, $markers);
        $second = $this->insert($first['html'], $markers);

        $this->assertSame([], $first['misses']);
        $this->assertSame([], $second['misses']);
        $this->assertSame(1, $second['inserted']);
        $this->assertSame($first['html'], $second['html']);
        $this->assertSame(1, substr_count($second['html'], 'data-image-name="339"'));
    }

    public function test_tei_pb_offsets_still_count_text_and_entities_but_not_tags(): void
    {
        $xml = '<TEI><teiHeader>Header excluded.</teiHeader><text><body><p>'
            . 'Écho &amp; Inter<emph>na</emph>tional 1<sup>er</sup><lb/>'
            . '<pb n="12" facs="img_fixture_012.jpg"/>Suite du texte.'
            . '</p></body></text></TEI>';

        $method = new ReflectionMethod(PageMarkerService::class, 'extractPbMarkers');
        $markers = $method->invoke(new PageMarkerService(), $xml);

        $this->assertCount(1, $markers);
        $this->assertSame(mb_strlen('Écho & International 1er', 'UTF-8'), $markers[0]['char_index']);
        $this->assertSame('Suite du texte.', $markers[0]['phrase']);
        $this->assertSame('12', $markers[0]['page']);
        $this->assertSame('img_fixture_012.jpg', $markers[0]['image']);
    }

    private function insert(string $html, array $markers): array
    {
        $method = new ReflectionMethod(PageMarkerService::class, 'insertMarkersFromOffsets');

        return $method->invoke(new PageMarkerService(), $html, $markers, 'right', false, true);
    }

    private function marker(string $imageCode): string
    {
        return '<span class="page-marker" data-image-name="' . $imageCode . '">'
            . '<span class="page-number">' . $imageCode . '</span>'
            . '<img src="/img/settings/page_right.svg" /></span>';
    }
}
