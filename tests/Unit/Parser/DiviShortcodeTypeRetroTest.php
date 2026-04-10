<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Parser;

use DiviToElementor\Parser\DiviShortcodeType;
use PHPUnit\Framework\TestCase;

/**
 * Retro-coverage: DiviShortcodeType::isSupported() stable contract.
 * If new types are added to isSupported(), WidgetFactory must be updated accordingly.
 * This test locks the current set of supported types as a regression gate.
 */
class DiviShortcodeTypeRetroTest extends TestCase
{
    /** Types that WidgetFactory has an explicit mapping for */
    private const WIDGET_FACTORY_MAPPED = [
        'et_pb_text',
        'et_pb_heading',
        'et_pb_image',
        'et_pb_button',
        'et_pb_divider',
        'et_pb_video',
        'et_pb_code',
        'et_pb_icon',
    ];

    public function testKnownUnsupportedTypesAreNotSupported(): void
    {
        $unsupported = [
            'et_pb_fullwidth_slider',
            'et_pb_fullwidth_header',
            'et_pb_portfolio',
            'et_pb_shop',
            'et_pb_countdown_timer',
            'et_pb_pricing_table',
        ];

        foreach ($unsupported as $tag) {
            $type = DiviShortcodeType::fromTag($tag);
            $this->assertFalse(
                $type->isSupported(),
                "Expected '$tag' to be unsupported but isSupported() returned true"
            );
        }
    }

    public function testStructuralTypesAreSupported(): void
    {
        $structural = ['et_pb_section', 'et_pb_row', 'et_pb_column'];
        foreach ($structural as $tag) {
            $type = DiviShortcodeType::fromTag($tag);
            $this->assertTrue($type->isSupported(), "'$tag' should be supported");
        }
    }

    public function testUnknownTagReturnsUnknownCase(): void
    {
        $type = DiviShortcodeType::fromTag('et_pb_nonexistent');
        $this->assertSame(DiviShortcodeType::Unknown, $type);
        $this->assertFalse($type->isSupported());
    }

    public function testWidgetFactoryMappedTypesAreRecognized(): void
    {
        // Regression gate: if a type is removed from the enum, this test will catch it
        foreach (self::WIDGET_FACTORY_MAPPED as $tag) {
            $type = DiviShortcodeType::fromTag($tag);
            $this->assertNotSame(
                DiviShortcodeType::Unknown,
                $type,
                "'$tag' must remain a recognized DiviShortcodeType"
            );
        }
    }
}
