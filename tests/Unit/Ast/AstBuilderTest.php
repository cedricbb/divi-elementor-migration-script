<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Ast;

use DiviToElementor\Ast\AstBuilder;
use DiviToElementor\Ast\AstTree;
use DiviToElementor\Ast\AstNode;
use DiviToElementor\Parser\DiviNode;
use DiviToElementor\Parser\DiviShortcodeType;
use PHPUnit\Framework\TestCase;

class AstBuilderTest extends TestCase
{
    private AstBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AstBuilder();
    }

    // AC-1
    public function testBuildReturnsCorrectHierarchy(): void
    {
        $column = new DiviNode(
            type: DiviShortcodeType::Column,
            attributes: ['type' => '1_2'],
            children: [],
            raw: '[et_pb_column type="1_2"][/et_pb_column]',
            status: 'supported',
        );
        $row = new DiviNode(
            type: DiviShortcodeType::Row,
            attributes: [],
            children: [$column],
            raw: '[et_pb_row][/et_pb_row]',
            status: 'supported',
        );
        $section = new DiviNode(
            type: DiviShortcodeType::Section,
            attributes: ['background_color' => '#ffffff'],
            children: [$row],
            raw: '[et_pb_section][/et_pb_section]',
            status: 'supported',
        );

        $tree = $this->builder->build([$section]);

        $this->assertInstanceOf(AstTree::class, $tree);
        $nodes = $tree->getNodes();
        $this->assertCount(1, $nodes);
        $this->assertSame('et_pb_section', $nodes[0]->type);
        $this->assertCount(1, $nodes[0]->children);
        $this->assertSame('et_pb_row', $nodes[0]->children[0]->type);
        $this->assertCount(1, $nodes[0]->children[0]->children);
        $this->assertSame('et_pb_column', $nodes[0]->children[0]->children[0]->type);
    }

    // AC-2 — 6 scenarios
    /**
     * @dataProvider columnWidthProvider
     */
    public function testNormalizeColumnWidthConversion(string $typeAttr, int $expectedWidth): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Column,
            attributes: ['type' => $typeAttr],
            children: [],
            raw: '[et_pb_column type="' . $typeAttr . '"][/et_pb_column]',
            status: 'supported',
        );

        $astNode = $this->builder->normalizeColumn($node);

        $this->assertSame($expectedWidth, $astNode->data->width);
    }

    public static function columnWidthProvider(): array
    {
        return [
            ['4_4', 100],
            ['1_2', 50],
            ['1_3', 33],
            ['2_3', 66],
            ['1_4', 25],
            ['3_4', 75],
        ];
    }

    // AC-4
    public function testExtractContentForTextAndImage(): void
    {
        $textNode = new DiviNode(
            type: DiviShortcodeType::Text,
            attributes: ['content' => 'Hello World'],
            children: [],
            raw: '[et_pb_text content="Hello World"][/et_pb_text]',
            status: 'supported',
        );
        $imageNode = new DiviNode(
            type: DiviShortcodeType::Image,
            attributes: ['src' => 'https://example.com/img.jpg', 'alt' => 'A photo'],
            children: [],
            raw: '[et_pb_image src="https://example.com/img.jpg" alt="A photo"][/et_pb_image]',
            status: 'supported',
        );

        $textContent = $this->builder->extractContent($textNode);
        $imageContent = $this->builder->extractContent($imageNode);

        $this->assertSame('Hello World', $textContent->content);
        $this->assertNull($textContent->src);
        $this->assertSame('https://example.com/img.jpg', $imageContent->src);
        $this->assertSame('A photo', $imageContent->alt);
        $this->assertNull($imageContent->content);
    }

    // AC-5
    public function testUnsupportedNodeHasRawShortcode(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::FullwidthSlider,
            attributes: [],
            children: [],
            raw: '[et_pb_fullwidth_slider][/et_pb_fullwidth_slider]',
            status: 'unsupported',
        );

        $tree = $this->builder->build([$node]);
        $nodes = $tree->getNodes();

        $this->assertSame('unsupported', $nodes[0]->status);
        $this->assertNotEmpty($nodes[0]->raw_shortcode);
        $this->assertSame('[et_pb_fullwidth_slider][/et_pb_fullwidth_slider]', $nodes[0]->raw_shortcode);
    }

    // AC-7 — extractContent missing attributes
    public function testExtractContentWithMissingAttributesDoesNotThrow(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Text,
            attributes: [],
            children: [],
            raw: '[et_pb_text][/et_pb_text]',
            status: 'supported',
        );

        $content = $this->builder->extractContent($node);
        $this->assertNull($content->content);
    }

    // EC-1
    public function testNormalizeColumnWithUnknownWidthReturnsNullWidth(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Column,
            attributes: ['type' => '1_1'],
            children: [],
            raw: '[et_pb_column type="1_1"][/et_pb_column]',
            status: 'supported',
        );

        $astNode = $this->builder->normalizeColumn($node);
        $this->assertNull($astNode->data->width);
    }

    public function testNormalizeColumnWithMissingTypeReturnsNullWidth(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Column,
            attributes: [],
            children: [],
            raw: '[et_pb_column][/et_pb_column]',
            status: 'supported',
        );

        $astNode = $this->builder->normalizeColumn($node);
        $this->assertNull($astNode->data->width);
    }

    // EC-2
    public function testBuildThrowsLogicExceptionForUnsupportedNodeWithEmptyRaw(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/AstBuilder: raw_shortcode cannot be empty for unsupported node of type/');

        $node = new DiviNode(
            type: DiviShortcodeType::FullwidthSlider,
            attributes: [],
            children: [],
            raw: '',
            status: 'unsupported',
        );

        $this->builder->build([$node]);
    }
}
