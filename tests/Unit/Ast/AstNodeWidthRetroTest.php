<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Ast;

use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\ContentBag;
use DiviToElementor\Ast\StyleBag;
use PHPUnit\Framework\TestCase;

/**
 * Retro-coverage: AstNode::data->width remains ?int.
 * If this type changes to ?string, the (string) cast in ElementorMapper::mapColumn()
 * would become redundant but non-breaking — this test serves as an early-warning.
 */
class AstNodeWidthRetroTest extends TestCase
{
    public function testWidthIsNullableInt(): void
    {
        $bag = new ContentBag(width: 50);
        $this->assertSame(50, $bag->width);
        $this->assertIsInt($bag->width);
    }

    public function testWidthCanBeNull(): void
    {
        $bag = new ContentBag();
        $this->assertNull($bag->width);
    }

    public function testAstNodeExposesWidthViaDataProperty(): void
    {
        $node = new AstNode(
            type: 'et_pb_column',
            status: 'supported',
            styles: new StyleBag(),
            data: new ContentBag(width: 33),
        );
        $this->assertSame(33, $node->data->width);
    }
}
