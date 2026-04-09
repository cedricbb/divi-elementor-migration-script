<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Ast;

use DiviToElementor\Ast\AstBuilder;
use DiviToElementor\Parser\DiviNode;
use DiviToElementor\Parser\DiviShortcodeType;
use PHPUnit\Framework\TestCase;

class StyleBagTest extends TestCase
{
    private AstBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AstBuilder();
    }

    // AC-3
    public function testExtractStylesReturnsCorrectBag(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Section,
            attributes: [
                'background_color' => '#ffffff',
                'padding_top'      => '10px',
                'padding_bottom'   => '20px',
                'margin_top'       => '5px',
                'margin_bottom'    => '5px',
                'text_align'       => 'center',
                'font_size'        => '16px',
                'color'            => '#333333',
            ],
            children: [],
            raw: '[et_pb_section][/et_pb_section]',
            status: 'supported',
        );

        $bag = $this->builder->extractStyles($node);

        $this->assertSame('#ffffff', $bag->background_color);
        $this->assertSame('10px 20px', $bag->padding);
        $this->assertSame('5px 5px', $bag->margin);
        $this->assertSame('center', $bag->text_align);
        $this->assertSame('16px', $bag->font_size);
        $this->assertSame('#333333', $bag->color);
    }

    // AC-7 — extractStyles missing attributes
    public function testExtractStylesWithMissingAttributesDoesNotThrow(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Section,
            attributes: [],
            children: [],
            raw: '[et_pb_section][/et_pb_section]',
            status: 'supported',
        );

        $bag = $this->builder->extractStyles($node);
        $this->assertNull($bag->background_color);
        $this->assertSame('', $bag->padding);
        $this->assertSame('', $bag->margin);
        $this->assertNull($bag->text_align);
        $this->assertNull($bag->font_size);
        $this->assertNull($bag->color);
    }

    // EC-3
    public function testExtractStylesIgnoresNonScalarValues(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Section,
            attributes: ['background_color' => ['nested' => 'array']],
            children: [],
            raw: '[et_pb_section][/et_pb_section]',
            status: 'supported',
        );

        $bag = $this->builder->extractStyles($node);
        $this->assertNull($bag->background_color);
    }
}
