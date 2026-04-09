<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Integration\Parser;

use DiviToElementor\Parser\DiviNode;
use DiviToElementor\Parser\DiviParser;
use DiviToElementor\Parser\DiviShortcodeType;
use WP_UnitTestCase;

/**
 * Integration tests for DiviParser.
 *
 * Prerequisites:
 *   - WordPress test suite bootstrapped (wp-env or WP_TESTS_DIR defined).
 *   - run with: ./vendor/bin/phpunit tests/Integration/
 */
class DiviParserIntegrationTest extends WP_UnitTestCase
{
    private DiviParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DiviParser();
    }

    // -------------------------------------------------------------------------
    // AC-1 — parse() returns non-empty array for a post with Divi shortcodes
    // -------------------------------------------------------------------------

    public function testParseRealPostWithDiviContent(): void
    {
        $content = '[et_pb_section _builder_version="4.9"]'
            . '[et_pb_row _builder_version="4.9"]'
            . '[et_pb_column type="4_4" _builder_version="4.9"]'
            . '[et_pb_text _builder_version="4.9"]<p>Hello World</p>[/et_pb_text]'
            . '[/et_pb_column]'
            . '[/et_pb_row]'
            . '[/et_pb_section]';

        $postId = self::factory()->post->create(['post_content' => $content]);

        $nodes = $this->parser->parse($postId);

        $this->assertNotEmpty($nodes, 'parse() must return a non-empty array for a post with Divi content');
        foreach ($nodes as $node) {
            $this->assertInstanceOf(DiviNode::class, $node);
        }
    }

    // -------------------------------------------------------------------------
    // AC-6 — base64-encoded content is decoded before parsing
    // -------------------------------------------------------------------------

    public function testParseRealPostWithBase64Content(): void
    {
        $diviContent = '[et_pb_section][et_pb_row][/et_pb_row][/et_pb_section]';
        $encoded     = base64_encode($diviContent);

        $postId = self::factory()->post->create(['post_content' => $encoded]);

        $nodes = $this->parser->parse($postId);

        $this->assertNotEmpty($nodes, 'parse() must decode base64 content and return parsed nodes');
        $this->assertSame(DiviShortcodeType::Section, $nodes[0]->type);
    }

    // -------------------------------------------------------------------------
    // EC-03 — free HTML outside shortcodes is ignored
    // -------------------------------------------------------------------------

    public function testParsePostWithMixedHtmlAndShortcodes(): void
    {
        $content = '<p>Intro text</p>'
            . '[et_pb_section][et_pb_row][/et_pb_row][/et_pb_section]'
            . '<footer>Footer</footer>';

        $postId = self::factory()->post->create(['post_content' => $content]);

        $nodes = $this->parser->parse($postId);

        $this->assertCount(1, $nodes, 'Only the Divi section node must be returned; free HTML ignored');
        $this->assertSame(DiviShortcodeType::Section, $nodes[0]->type);
    }

    // -------------------------------------------------------------------------
    // AC-8 — Scenario Outline: 10 modules, correct supported/unsupported status
    // -------------------------------------------------------------------------

    public function testScenarioOutline10Modules(): void
    {
        $supported = [
            'et_pb_text',
            'et_pb_image',
            'et_pb_button',
            'et_pb_video',
            'et_pb_code',
        ];
        $unsupported = [
            'et_pb_fullwidth_slider',
            'et_pb_fullwidth_header',
            'et_pb_portfolio',
            'et_pb_shop',
            'et_pb_countdown_timer',
        ];

        foreach ($supported as $tag) {
            $postId = self::factory()->post->create([
                'post_content' => "[{$tag}][/{$tag}]",
            ]);
            $nodes = $this->parser->parse($postId);
            $this->assertNotEmpty($nodes, "Expected node for supported tag {$tag}");
            $this->assertSame(
                'supported',
                $nodes[0]->status,
                "Tag {$tag} must have status='supported'"
            );
        }

        foreach ($unsupported as $tag) {
            $postId = self::factory()->post->create([
                'post_content' => "[{$tag}][/{$tag}]",
            ]);
            $nodes = $this->parser->parse($postId);
            $this->assertNotEmpty($nodes, "Expected node for unsupported tag {$tag}");
            $this->assertSame(
                'unsupported',
                $nodes[0]->status,
                "Tag {$tag} must have status='unsupported'"
            );
        }
    }
}
