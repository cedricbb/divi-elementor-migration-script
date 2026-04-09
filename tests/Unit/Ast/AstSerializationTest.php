<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Ast;

use DiviToElementor\Ast\AstBuilder;
use DiviToElementor\Ast\AstTree;
use DiviToElementor\Parser\DiviNode;
use DiviToElementor\Parser\DiviShortcodeType;
use PHPUnit\Framework\TestCase;

class AstSerializationTest extends TestCase
{
    private AstBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AstBuilder();
    }

    // AC-6
    public function testToJsonIsDeserializable(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Section,
            attributes: ['background_color' => '#ffffff', 'padding_top' => '10px', 'padding_bottom' => '10px'],
            children: [],
            raw: '[et_pb_section][/et_pb_section]',
            status: 'supported',
        );

        $tree = $this->builder->build([$node]);
        $json = $tree->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('nodes', $decoded);
        $this->assertCount(1, $decoded['nodes']);
        $this->assertSame('et_pb_section', $decoded['nodes'][0]['type']);
        $this->assertSame('supported', $decoded['nodes'][0]['status']);
        $this->assertSame('#ffffff', $decoded['nodes'][0]['styles']['background_color']);
        $this->assertSame('10px 10px', $decoded['nodes'][0]['styles']['padding']);
    }

    // EC-4
    public function testEmptyTreeSerializesToValidJson(): void
    {
        $tree = $this->builder->build([]);
        $json = $tree->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame(['nodes' => []], $decoded);
    }

    // EC-5
    public function testToJsonPreservesUtf8Content(): void
    {
        $node = new DiviNode(
            type: DiviShortcodeType::Text,
            attributes: ['content' => 'Héllo wörld 🌍 ñoño'],
            children: [],
            raw: '[et_pb_text][/et_pb_text]',
            status: 'supported',
        );

        $tree = $this->builder->build([$node]);
        $json = $tree->toJson();

        $decoded = json_decode($json, true);
        $this->assertSame('Héllo wörld 🌍 ñoño', $decoded['nodes'][0]['data']['content']);
        // JSON_UNESCAPED_UNICODE: characters are not escaped
        $this->assertStringContainsString('Héllo wörld 🌍 ñoño', $json);
    }
}
