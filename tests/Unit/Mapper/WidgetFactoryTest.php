<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Mapper;

use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\ContentBag;
use DiviToElementor\Ast\StyleBag;
use DiviToElementor\Mapper\WidgetFactory;
use PHPUnit\Framework\TestCase;

class WidgetFactoryTest extends TestCase
{
    private WidgetFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WidgetFactory();
    }

    private function makeNode(
        string $type,
        string $status = 'supported',
        ?string $content = null,
        ?string $src = null,
        ?string $alt = null,
        ?string $color = null,
        ?string $rawShortcode = null,
    ): AstNode {
        return new AstNode(
            type: $type,
            status: $status,
            styles: new StyleBag(color: $color),
            data: new ContentBag(content: $content, src: $src, alt: $alt),
            children: [],
            raw_shortcode: $rawShortcode,
        );
    }

    public function testTextEditorMapping(): void
    {
        $node = $this->makeNode('et_pb_text', content: '<p>Hello</p>');
        $result = $this->factory->make('et_pb_text', $node);

        $this->assertSame('text-editor', $result['widgetType']);
        $this->assertSame('<p>Hello</p>', $result['settings']['editor']);
    }

    public function testTextEditorHasEditorKey(): void
    {
        $node = $this->makeNode('et_pb_text', content: 'test');
        $result = $this->factory->make('et_pb_text', $node);

        $this->assertArrayHasKey('editor', $result['settings']);
    }

    public function testHeadingMapping(): void
    {
        $node = $this->makeNode('et_pb_heading', content: 'My Title');
        $result = $this->factory->make('et_pb_heading', $node);

        $this->assertSame('heading', $result['widgetType']);
        $this->assertSame('My Title', $result['settings']['title']);
    }

    public function testImageMapping(): void
    {
        $node = $this->makeNode('et_pb_image', src: 'https://example.com/img.png', alt: 'Alt text');
        $result = $this->factory->make('et_pb_image', $node);

        $this->assertSame('image', $result['widgetType']);
        $this->assertSame('https://example.com/img.png', $result['settings']['image']['url']);
        $this->assertSame('Alt text', $result['settings']['image']['alt']);
    }

    public function testImageHasUrlAndAlt(): void
    {
        $node = $this->makeNode('et_pb_image', src: 'http://example.com/x.jpg', alt: 'desc');
        $result = $this->factory->make('et_pb_image', $node);

        $this->assertArrayHasKey('url', $result['settings']['image']);
        $this->assertArrayHasKey('alt', $result['settings']['image']);
    }

    public function testButtonMapping(): void
    {
        $node = $this->makeNode('et_pb_button', content: 'Click me');
        $result = $this->factory->make('et_pb_button', $node);

        $this->assertSame('button', $result['widgetType']);
        $this->assertSame('Click me', $result['settings']['text']);
        $this->assertSame('', $result['settings']['link']['url']);
    }

    public function testButtonHasTextAndLink(): void
    {
        $node = $this->makeNode('et_pb_button', content: 'Go');
        $result = $this->factory->make('et_pb_button', $node);

        $this->assertArrayHasKey('text', $result['settings']);
        $this->assertArrayHasKey('link', $result['settings']);
    }

    public function testDividerMapping(): void
    {
        $node = $this->makeNode('et_pb_divider');
        $result = $this->factory->make('et_pb_divider', $node);

        $this->assertSame('divider', $result['widgetType']);
        $this->assertSame([], $result['settings']);
    }

    public function testVideoYoutubeMapping(): void
    {
        $node = $this->makeNode('et_pb_video', src: 'https://www.youtube.com/watch?v=abc123');
        $result = $this->factory->make('et_pb_video', $node);

        $this->assertSame('video', $result['widgetType']);
        $this->assertArrayHasKey('youtube_url', $result['settings']);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $result['settings']['youtube_url']);
    }

    public function testVideoVimeoMapping(): void
    {
        $node = $this->makeNode('et_pb_video', src: 'https://vimeo.com/123456');
        $result = $this->factory->make('et_pb_video', $node);

        $this->assertSame('video', $result['widgetType']);
        $this->assertArrayHasKey('vimeo_url', $result['settings']);
    }

    public function testVideoMp4Mapping(): void
    {
        $node = $this->makeNode('et_pb_video', src: 'https://example.com/video.mp4');
        $result = $this->factory->make('et_pb_video', $node);

        $this->assertSame('video', $result['widgetType']);
        $this->assertArrayHasKey('mp4', $result['settings']);
        $this->assertSame('https://example.com/video.mp4', $result['settings']['mp4']['url']);
    }

    public function testCodeToHtmlMapping(): void
    {
        $node = $this->makeNode('et_pb_code', content: '<pre>code</pre>');
        $result = $this->factory->make('et_pb_code', $node);

        $this->assertSame('html', $result['widgetType']);
        $this->assertSame('<pre>code</pre>', $result['settings']['html']);
    }

    public function testIconMapping(): void
    {
        $node = $this->makeNode('et_pb_icon', color: '#ff0000');
        $result = $this->factory->make('et_pb_icon', $node);

        $this->assertSame('icon', $result['widgetType']);
        $this->assertSame('', $result['settings']['selected_icon']);
        $this->assertSame('#ff0000', $result['settings']['primary_color']);
    }

    public function testUnknownTypeReturnsFallback(): void
    {
        $node = $this->makeNode('et_pb_unknown', rawShortcode: '[et_pb_unknown /]');
        $result = $this->factory->make('et_pb_unknown', $node);

        $this->assertSame('html', $result['widgetType']);
        $this->assertStringContainsString('divi-migration-unsupported', $result['settings']['html']);
    }

    public function testUnsupportedStatusReturnsFallback(): void
    {
        // EC-7: even a known type with status=unsupported goes to fallback
        $node = $this->makeNode('et_pb_text', status: 'unsupported', content: 'hi', rawShortcode: '[et_pb_text]hi[/et_pb_text]');
        $result = $this->factory->make('et_pb_text', $node);

        $this->assertSame('html', $result['widgetType']);
    }

    public function testNoResidualDiviKeys(): void
    {
        $types = ['et_pb_text', 'et_pb_heading', 'et_pb_image', 'et_pb_button', 'et_pb_divider', 'et_pb_code', 'et_pb_icon'];
        foreach ($types as $type) {
            $node = $this->makeNode($type, content: 'test', src: 'http://x.com/img.jpg', alt: 'alt', color: '#000');
            $result = $this->factory->make($type, $node);
            foreach (array_keys($result['settings']) as $key) {
                $this->assertStringStartsNotWith('et_pb_', $key, "Key '$key' starts with et_pb_ for type '$type'");
                $this->assertStringStartsNotWith('divi_', $key, "Key '$key' starts with divi_ for type '$type'");
            }
        }
    }

    public function testNullContentBecomesEmptyString(): void
    {
        $node = $this->makeNode('et_pb_text', content: null);
        $result = $this->factory->make('et_pb_text', $node);

        $this->assertSame('', $result['settings']['editor']);
    }

    public function testVideoWithNullSrcDefaultsToYoutubeEmpty(): void
    {
        // EC-6
        $node = $this->makeNode('et_pb_video', src: null);
        $result = $this->factory->make('et_pb_video', $node);

        $this->assertSame('video', $result['widgetType']);
        $this->assertArrayHasKey('youtube_url', $result['settings']);
        $this->assertSame('', $result['settings']['youtube_url']);
    }
}
