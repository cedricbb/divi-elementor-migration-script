<?php

declare(strict_types=1);

namespace DiviToElementor\Ast;

readonly class AstNode
{
    /**
     * @param string    $type          Valeur string du DiviShortcodeType (ex: 'et_pb_text')
     * @param string    $status        'supported' | 'unsupported'
     * @param StyleBag  $styles        Styles normalisés
     * @param ContentBag $data         Données de contenu normalisées
     * @param AstNode[] $children      Enfants récursifs
     * @param ?string   $raw_shortcode Présent et non vide si status='unsupported', null sinon
     */
    public function __construct(
        public string    $type,
        public string    $status,
        public StyleBag  $styles,
        public ContentBag $data,
        public array     $children      = [],
        public ?string   $raw_shortcode = null,
    ) {}

    public function toArray(): array
    {
        return [
            'type'         => $this->type,
            'status'       => $this->status,
            'styles'       => [
                'background_color' => $this->styles->background_color,
                'padding'          => $this->styles->padding,
                'margin'           => $this->styles->margin,
                'text_align'       => $this->styles->text_align,
                'font_size'        => $this->styles->font_size,
                'color'            => $this->styles->color,
            ],
            'data'         => [
                'content' => $this->data->content,
                'src'     => $this->data->src,
                'alt'     => $this->data->alt,
                'width'   => $this->data->width,
            ],
            'children'     => array_map(
                static fn(AstNode $child): array => $child->toArray(),
                $this->children
            ),
            'raw_shortcode' => $this->raw_shortcode,
        ];
    }
}
