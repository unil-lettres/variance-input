<?php

namespace Tests\Unit;

use App\Support\Txt2TeiInlineMarkup;
use PHPUnit\Framework\TestCase;

class Txt2TeiInlineMarkupTest extends TestCase
{
    public function test_converts_legacy_italic_and_superscript_markers(): void
    {
        $result = Txt2TeiInlineMarkup::escapeWithLegacyInlineMarkup(
            'Un \mot\ et le 1^er^ rang & suite'
        );

        $this->assertSame(
            'Un <emph>mot</emph> et le 1<sup>er</sup> rang &amp; suite',
            $result
        );
    }

    public function test_preserves_unpaired_legacy_markers_as_text(): void
    {
        $result = Txt2TeiInlineMarkup::escapeWithLegacyInlineMarkup(
            'Un \mot et le 1^er rang'
        );

        $this->assertSame(
            'Un \mot et le 1^er rang',
            $result
        );
    }

    public function test_allows_nested_italic_and_superscript_markers(): void
    {
        $result = Txt2TeiInlineMarkup::escapeWithLegacyInlineMarkup(
            'Un \mot ^rare^\ ici'
        );

        $this->assertSame(
            'Un <emph>mot <sup>rare</sup></emph> ici',
            $result
        );
    }

    public function test_crossed_legacy_markers_do_not_generate_invalid_xml(): void
    {
        $result = Txt2TeiInlineMarkup::escapeWithLegacyInlineMarkup(
            'Un \mot ^rare\ vraiment^ ici'
        );

        $this->assertSame(
            'Un \mot <sup>rare\ vraiment</sup> ici',
            $result
        );
    }
}
