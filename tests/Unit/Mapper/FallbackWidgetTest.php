<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Mapper;

use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\ContentBag;
use DiviToElementor\Ast\StyleBag;
use DiviToElementor\Mapper\FallbackWidget;
use PHPUnit\Framework\TestCase;

class FallbackWidgetTest extends TestCase
{
    private function makeNode(string $type, ?string $rawShortcode = null): AstNode
    {
        return new AstNode(
            type: $type,
            status: 'unsupported',
            styles: new StyleBag(),
            data: new ContentBag(),
            children: [],
            raw_shortcode: $rawShortcode,
        );
    }

    public function testFallbackContainsCommentAndClass(): void
    {
        $node = $this->makeNode('et_pb_custom', '[et_pb_custom /]');
        $result = FallbackWidget::make($node);

        $this->assertSame('html', $result['widgetType']);
        $this->assertStringContainsString('divi-migration-unsupported', $result['settings']['html']);
        $this->assertStringContainsString('<!-- Module Divi non converti : et_pb_custom -->', $result['settings']['html']);
    }

    public function testFallbackWithNullRawShortcode(): void
    {
        $node = $this->makeNode('et_pb_unknown', null);
        $result = FallbackWidget::make($node);

        $this->assertSame('html', $result['widgetType']);
        $this->assertStringContainsString('divi-migration-unsupported', $result['settings']['html']);
        // No raw_shortcode, but no error
        $this->assertStringNotContainsString('null', $result['settings']['html']);
    }

    public function testRawShortcodeIsEscaped(): void
    {
        $node = $this->makeNode('et_pb_custom', '<script>alert("xss")</script>');
        $result = FallbackWidget::make($node);

        $this->assertStringNotContainsString('<script>', $result['settings']['html']);
        $this->assertStringContainsString('&lt;script&gt;', $result['settings']['html']);
    }
}
