<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Parser;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DiviToElementor\Parser\DiviNode;
use DiviToElementor\Parser\DiviParser;
use DiviToElementor\Parser\DiviShortcodeType;
use PHPUnit\Framework\TestCase;

class DiviParserTest extends TestCase
{
    private DiviParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->parser = new DiviParser();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // parse() — guard clauses
    // -------------------------------------------------------------------------

    public function testParseReturnsEmptyArrayWhenPostContentIsFalse(): void
    {
        Functions\when('get_post_field')->justReturn(false);

        $result = $this->parser->parse(1);

        $this->assertSame([], $result);
    }

    public function testParseReturnsEmptyArrayWhenPostContentIsEmpty(): void
    {
        Functions\when('get_post_field')->justReturn('');

        $result = $this->parser->parse(42);

        $this->assertSame([], $result);
    }

    public function testParseReturnsEmptyArrayWhenNoEtPbShortcodes(): void
    {
        Functions\when('get_post_field')->justReturn('<p>No Divi here</p>');

        $result = $this->parser->parse(99);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // parse() — happy path
    // -------------------------------------------------------------------------

    public function testParseReturnsSectionNodeWithChildren(): void
    {
        $content = '[et_pb_section][et_pb_row][/et_pb_row][/et_pb_section]';
        Functions\when('get_post_field')->justReturn($content);
        Functions\when('shortcode_parse_atts')->justReturn([]);

        $result = $this->parser->parse(10);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(DiviNode::class, $result[0]);
        $this->assertSame(DiviShortcodeType::Section, $result[0]->type);
        $this->assertCount(1, $result[0]->children);
        $this->assertSame(DiviShortcodeType::Row, $result[0]->children[0]->type);
    }

    // -------------------------------------------------------------------------
    // parseShortcodes() — attributes, HTML, unsupported, depth
    // -------------------------------------------------------------------------

    public function testParseShortcodesExtractsAttributes(): void
    {
        Functions\when('shortcode_parse_atts')
            ->justReturn(['src' => 'https://example.com/img.jpg', 'alt' => 'Photo', 'align' => 'center']);

        $content = '[et_pb_image src="https://example.com/img.jpg" alt="Photo" align="center"][/et_pb_image]';
        $nodes   = $this->parser->parseShortcodes($content);

        $this->assertCount(1, $nodes);
        $attrs = $nodes[0]->attributes;
        $this->assertSame('https://example.com/img.jpg', $attrs['src']);
        $this->assertSame('Photo', $attrs['alt']);
        $this->assertSame('center', $attrs['align']);
    }

    public function testParseShortcodesHandlesUnclosedShortcode(): void
    {
        Functions\when('shortcode_parse_atts')->justReturn(['_builder_version' => '4.9']);

        $content = '[et_pb_section _builder_version="4.9"][et_pb_row]texte sans fermeture';
        $nodes   = $this->parser->parseShortcodes($content);

        // The outermost et_pb_section has no closing tag → malformed
        $this->assertNotEmpty($nodes);
        $section = $nodes[0];
        $this->assertSame('malformed', $section->status);
        $this->assertSame([], $section->children);
    }

    public function testParseShortcodesIgnoresFreeHtml(): void
    {
        Functions\when('shortcode_parse_atts')->justReturn([]);

        $content = '<p>Intro</p>[et_pb_section][/et_pb_section]<footer>bas</footer>';
        $nodes   = $this->parser->parseShortcodes($content);

        $this->assertCount(1, $nodes);
        $this->assertSame(DiviShortcodeType::Section, $nodes[0]->type);
    }

    public function testParseShortcodesMarksUnsupportedModules(): void
    {
        Functions\when('shortcode_parse_atts')->justReturn([]);

        $content = '[et_pb_fullwidth_slider][/et_pb_fullwidth_slider]';
        $nodes   = $this->parser->parseShortcodes($content);

        $this->assertCount(1, $nodes);
        $this->assertSame('unsupported', $nodes[0]->status);
        $this->assertSame(DiviShortcodeType::FullwidthSlider, $nodes[0]->type);
    }

    public function testParseShortcodesStopsAtMaxDepth(): void
    {
        Functions\when('shortcode_parse_atts')->justReturn([]);

        // Build 11 levels of nesting: section > row > column > text > ... (11 deep)
        $open  = '';
        $close = '';
        $tags  = ['et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_text',
                  'et_pb_button', 'et_pb_image', 'et_pb_video', 'et_pb_code',
                  'et_pb_blurb', 'et_pb_divider', 'et_pb_gallery'];
        foreach ($tags as $tag) {
            $open  .= "[{$tag}]";
            $close  = "[/{$tag}]" . $close;
        }
        $content = $open . $close;

        $nodes = $this->parser->parseShortcodes($content);

        $this->assertCount(1, $nodes);
        // Walk down 10 levels — children at depth 10 must be []
        $node = $nodes[0];
        for ($i = 0; $i < 9; $i++) {
            $this->assertNotEmpty($node->children, "Expected children at depth {$i}");
            $node = $node->children[0];
        }
        $this->assertSame([], $node->children, 'Children must be empty at max depth (10)');
    }

    // -------------------------------------------------------------------------
    // decodeIfEncoded()
    // -------------------------------------------------------------------------

    public function testDecodeIfEncodedDecodesBase64Content(): void
    {
        $original = '[et_pb_section][et_pb_row][/et_pb_row][/et_pb_section]';
        $encoded  = base64_encode($original);

        $result = $this->parser->decodeIfEncoded($encoded);

        $this->assertSame($original, $result);
    }

    public function testDecodeIfEncodedReturnsOriginalWhenNotBase64(): void
    {
        $content = '[et_pb_section]Hello[/et_pb_section]';

        $result = $this->parser->decodeIfEncoded($content);

        $this->assertSame($content, $result);
    }

    public function testDecodeIfEncodedReturnsOriginalOnInvalidBase64(): void
    {
        // Passes base64 charset check but strict decode returns false (padded incorrectly)
        // or decoded result does not contain '[et_pb_'
        $notDivi = base64_encode('This is just plain text without any Divi shortcodes');

        $result = $this->parser->decodeIfEncoded($notDivi);

        // Decoded text does not contain '[et_pb_', so original encoded string is returned
        $this->assertSame($notDivi, $result);
    }

    public function testDecodeIfEncodedReturnsEmptyStringOnEmptyInput(): void
    {
        $result = $this->parser->decodeIfEncoded('');

        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // DiviNode::toArray() — guaranteed keys
    // -------------------------------------------------------------------------

    public function testNodeToArrayHasAllRequiredKeys(): void
    {
        $node = new DiviNode(
            DiviShortcodeType::Section,
            ['_builder_version' => '4.9'],
            [],
            '[et_pb_section _builder_version="4.9"][/et_pb_section]',
            'supported'
        );

        $arr = $node->toArray();

        foreach (['type', 'attributes', 'children', 'raw', 'status'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Key '{$key}' must be present in toArray()");
        }

        // Also verify for a malformed node — status must still be present
        $malformed = new DiviNode(
            DiviShortcodeType::Section,
            [],
            [],
            '[et_pb_section]',
            'malformed'
        );
        $malformedArr = $malformed->toArray();
        foreach (['type', 'attributes', 'children', 'raw', 'status'] as $key) {
            $this->assertArrayHasKey($key, $malformedArr, "Key '{$key}' must be present for malformed node");
        }
        $this->assertSame('malformed', $malformedArr['status']);
    }
}
