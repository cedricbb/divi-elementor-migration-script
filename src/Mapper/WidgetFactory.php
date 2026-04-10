<?php

declare(strict_types=1);

namespace DiviToElementor\Mapper;

use DiviToElementor\Ast\AstNode;

class WidgetFactory
{
    /**
     * Fabrique un tableau settings Elementor à partir d'un nœud AST.
     * Si le statut est 'unsupported' ou le type est inconnu → délègue à FallbackWidget::make().
     *
     * @param  string  $type  Valeur du type AST (ex: 'et_pb_text')
     * @param  AstNode $node  Nœud AST source
     * @return array          ['widgetType'=>string, 'settings'=>array<string,mixed>]
     */
    public function make(string $type, AstNode $node): array
    {
        // EC-7: status unsupported → fallback immédiat, sans inspecter le type
        if ($node->status === 'unsupported') {
            return FallbackWidget::make($node);
        }

        return match ($type) {
            'et_pb_text'    => $this->makeTextEditor($node),
            'et_pb_heading' => $this->makeHeading($node),
            'et_pb_image'   => $this->makeImage($node),
            'et_pb_button'  => $this->makeButton($node),
            'et_pb_divider' => $this->makeDivider(),
            'et_pb_video'   => $this->makeVideo($node),
            'et_pb_code'    => $this->makeCode($node),
            'et_pb_icon'    => $this->makeIcon($node),
            default         => FallbackWidget::make($node),
        };
    }

    private function makeTextEditor(AstNode $node): array
    {
        return [
            'widgetType' => 'text-editor',
            'settings'   => [
                'editor' => (string) ($node->data->content ?? ''),
            ],
        ];
    }

    private function makeHeading(AstNode $node): array
    {
        return [
            'widgetType' => 'heading',
            'settings'   => [
                'title' => (string) ($node->data->content ?? ''),
            ],
        ];
    }

    private function makeImage(AstNode $node): array
    {
        return [
            'widgetType' => 'image',
            'settings'   => [
                'image' => [
                    'url' => (string) ($node->data->src ?? ''),
                    'alt' => (string) ($node->data->alt ?? ''),
                ],
            ],
        ];
    }

    private function makeButton(AstNode $node): array
    {
        return [
            'widgetType' => 'button',
            'settings'   => [
                'text' => (string) ($node->data->content ?? ''),
                'link' => ['url' => ''],
            ],
        ];
    }

    private function makeDivider(): array
    {
        return [
            'widgetType' => 'divider',
            'settings'   => [],
        ];
    }

    private function makeVideo(AstNode $node): array
    {
        $src = $node->data->src;

        // EC-6: src null → youtube_url vide par défaut
        if ($src === null) {
            return [
                'widgetType' => 'video',
                'settings'   => ['youtube_url' => ''],
            ];
        }

        if (str_contains($src, 'youtube') || str_contains($src, 'youtu.be')) {
            return [
                'widgetType' => 'video',
                'settings'   => ['youtube_url' => $src],
            ];
        }

        if (str_contains($src, 'vimeo')) {
            return [
                'widgetType' => 'video',
                'settings'   => ['vimeo_url' => $src],
            ];
        }

        return [
            'widgetType' => 'video',
            'settings'   => ['mp4' => ['url' => $src]],
        ];
    }

    private function makeCode(AstNode $node): array
    {
        return [
            'widgetType' => 'html',
            'settings'   => [
                'html' => (string) ($node->data->content ?? ''),
            ],
        ];
    }

    private function makeIcon(AstNode $node): array
    {
        return [
            'widgetType' => 'icon',
            'settings'   => [
                'selected_icon' => '',
                'primary_color' => (string) ($node->styles->color ?? ''),
            ],
        ];
    }
}
