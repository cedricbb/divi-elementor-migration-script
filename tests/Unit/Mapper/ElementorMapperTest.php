<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Mapper;

use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\AstTree;
use DiviToElementor\Ast\ContentBag;
use DiviToElementor\Ast\StyleBag;
use DiviToElementor\Mapper\ElementorMapper;
use DiviToElementor\Mapper\IdGenerator;
use DiviToElementor\Mapper\WidgetFactory;
use PHPUnit\Framework\TestCase;

class ElementorMapperTest extends TestCase
{
    private ElementorMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ElementorMapper(new WidgetFactory(), new IdGenerator());
    }

    private function makeNode(
        string $type,
        string $status = 'supported',
        array $children = [],
        ?string $bgColor = null,
        ?string $content = null,
        ?int $width = null,
    ): AstNode {
        return new AstNode(
            type: $type,
            status: $status,
            styles: new StyleBag(background_color: $bgColor),
            data: new ContentBag(content: $content, width: $width),
            children: $children,
        );
    }

    private function makeFullTree(): AstTree
    {
        $widget = $this->makeNode('et_pb_text', content: '<p>Hello</p>');
        $column = $this->makeNode('et_pb_column', children: [$widget], width: 100);
        $row    = $this->makeNode('et_pb_row', children: [$column]);
        $section = $this->makeNode('et_pb_section', children: [$row], bgColor: '#ffffff');
        return new AstTree([$section]);
    }

    public function testMapReturnsSectionsAtRoot(): void
    {
        $ast = $this->makeFullTree();
        $result = $this->mapper->map($ast);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $item) {
            $this->assertSame('section', $item['elType']);
        }
    }

    public function testMapFlattenRowsIntoSection(): void
    {
        // EC-1: Rows are transparent — columns are direct children of section elements
        $ast = $this->makeFullTree();
        $result = $this->mapper->map($ast);

        $section = $result[0];
        // All elements in section should be columns, not rows
        foreach ($section['elements'] as $element) {
            $this->assertSame('column', $element['elType']);
        }
    }

    public function testMapColumnWithNoChildren(): void
    {
        // EC-2
        $column = $this->makeNode('et_pb_column', children: []);
        $row    = $this->makeNode('et_pb_row', children: [$column]);
        $section = $this->makeNode('et_pb_section', children: [$row]);
        $ast = new AstTree([$section]);

        $result = $this->mapper->map($ast);
        $col = $result[0]['elements'][0];

        $this->assertSame('column', $col['elType']);
        $this->assertSame([], $col['elements']);
    }

    public function testColumnSizeIsString(): void
    {
        $column = $this->makeNode('et_pb_column', width: 50);
        $result = $this->mapper->mapColumn($column);

        $this->assertIsString($result['settings']['_column_size']);
        $this->assertSame('50', $result['settings']['_column_size']);
    }

    public function testColumnSizeDefaultsTo100WhenWidthIsNull(): void
    {
        $column = $this->makeNode('et_pb_column', width: null);
        $result = $this->mapper->mapColumn($column);

        $this->assertSame('100', $result['settings']['_column_size']);
    }

    public function testAllIdsAreUniqueInFullTree(): void
    {
        $ast = $this->makeFullTree();
        $result = $this->mapper->map($ast);

        $ids = $this->collectIds($result);
        $this->assertCount(count($ids), array_unique($ids), 'Duplicate IDs found in output');
    }

    public function testSectionContainsBackgroundColor(): void
    {
        $ast = $this->makeFullTree();
        $result = $this->mapper->map($ast);

        $this->assertSame('#ffffff', $result[0]['settings']['background_color']);
    }

    public function testMapSectionStructure(): void
    {
        $widget = $this->makeNode('et_pb_text', content: 'hi');
        $column = $this->makeNode('et_pb_column', children: [$widget]);
        $row    = $this->makeNode('et_pb_row', children: [$column]);
        $section = $this->makeNode('et_pb_section', children: [$row]);
        $ast = new AstTree([$section]);

        $result = $this->mapper->map($ast);
        $s = $result[0];

        $this->assertArrayHasKey('id', $s);
        $this->assertArrayHasKey('elType', $s);
        $this->assertArrayHasKey('settings', $s);
        $this->assertArrayHasKey('elements', $s);

        $col = $s['elements'][0];
        $this->assertSame('column', $col['elType']);

        $w = $col['elements'][0];
        $this->assertSame('widget', $w['elType']);
        $this->assertSame([], $w['elements']);
    }

    private function collectIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (isset($node['id'])) {
                $ids[] = $node['id'];
            }
            if (!empty($node['elements'])) {
                $ids = array_merge($ids, $this->collectIds($node['elements']));
            }
        }
        return $ids;
    }
}
