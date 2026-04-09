<?php

declare(strict_types=1);

namespace DiviToElementor\Ast;

use DiviToElementor\Parser\DiviNode;
use DiviToElementor\Parser\DiviShortcodeType;

class AstBuilder
{
    private const COLUMN_WIDTH_MAP = [
        '4_4' => 100,
        '1_2' => 50,
        '1_3' => 33,
        '2_3' => 66,
        '1_4' => 25,
        '3_4' => 75,
    ];

    /**
     * Construit un AstTree depuis un tableau de DiviNode parsés.
     *
     * @param  DiviNode[] $parsedNodes
     * @return AstTree
     * @throws \LogicException Si un noeud unsupported a un raw vide.
     */
    public function build(array $parsedNodes): AstTree
    {
        $astNodes = array_map(
            fn(DiviNode $node): AstNode => $this->buildNode($node),
            $parsedNodes
        );

        return new AstTree($astNodes);
    }

    private function buildNode(DiviNode $node): AstNode
    {
        $isSupported = $node->type->isSupported();
        $status = $isSupported ? 'supported' : 'unsupported';

        if (!$isSupported && $node->raw === '') {
            throw new \LogicException(
                sprintf(
                    'AstBuilder: raw_shortcode cannot be empty for unsupported node of type %s',
                    $node->type->value
                )
            );
        }

        $rawShortcode = $isSupported ? null : $node->raw;

        $isColumn = in_array($node->type, [DiviShortcodeType::Column, DiviShortcodeType::ColumnInner], true);

        if ($isColumn) {
            $astNode = $this->normalizeColumn($node);
            // Re-build with recursed children
            $children = array_map(
                fn(DiviNode $child): AstNode => $this->buildNode($child),
                $node->children
            );
            return new AstNode(
                type: $astNode->type,
                status: $astNode->status,
                styles: $astNode->styles,
                data: $astNode->data,
                children: $children,
                raw_shortcode: $astNode->raw_shortcode,
            );
        }

        $styles  = $this->extractStyles($node);
        $content = $this->extractContent($node);
        $children = array_map(
            fn(DiviNode $child): AstNode => $this->buildNode($child),
            $node->children
        );

        return new AstNode(
            type: $node->type->value,
            status: $status,
            styles: $styles,
            data: $content,
            children: $children,
            raw_shortcode: $rawShortcode,
        );
    }

    /**
     * Normalise un noeud Column ou ColumnInner.
     *
     * @param  DiviNode $node
     * @return AstNode
     */
    public function normalizeColumn(DiviNode $node): AstNode
    {
        $isSupported = $node->type->isSupported();
        $status = $isSupported ? 'supported' : 'unsupported';
        $rawShortcode = $isSupported ? null : $node->raw;

        $typeAttr = $node->attributes['type'] ?? null;
        $width = (is_string($typeAttr) && isset(self::COLUMN_WIDTH_MAP[$typeAttr]))
            ? self::COLUMN_WIDTH_MAP[$typeAttr]
            : null;

        $styles = $this->extractStyles($node);

        return new AstNode(
            type: $node->type->value,
            status: $status,
            styles: $styles,
            data: new ContentBag(width: $width),
            children: [],
            raw_shortcode: $rawShortcode,
        );
    }

    /**
     * Extrait les styles d'un DiviNode et retourne un StyleBag.
     *
     * @param  DiviNode $node
     * @return StyleBag
     */
    public function extractStyles(DiviNode $node): StyleBag
    {
        $attrs = $node->attributes;

        $backgroundColor = $this->scalarOrNull($attrs['background_color'] ?? null);
        $textAlign       = $this->scalarOrNull($attrs['text_align'] ?? null);
        $fontSize        = $this->scalarOrNull($attrs['font_size'] ?? null);
        $color           = $this->scalarOrNull($attrs['color'] ?? null);

        $padding = $this->concatTopBottom(
            $this->scalarOrNull($attrs['padding_top'] ?? null),
            $this->scalarOrNull($attrs['padding_bottom'] ?? null)
        );

        $margin = $this->concatTopBottom(
            $this->scalarOrNull($attrs['margin_top'] ?? null),
            $this->scalarOrNull($attrs['margin_bottom'] ?? null)
        );

        return new StyleBag(
            background_color: $backgroundColor,
            padding: $padding,
            margin: $margin,
            text_align: $textAlign,
            font_size: $fontSize,
            color: $color,
        );
    }

    /**
     * Extrait le contenu d'un DiviNode et retourne un ContentBag.
     *
     * @param  DiviNode $node
     * @return ContentBag
     */
    public function extractContent(DiviNode $node): ContentBag
    {
        $attrs = $node->attributes;

        return match ($node->type) {
            DiviShortcodeType::Text       => new ContentBag(content: $this->scalarOrNull($attrs['content'] ?? null)),
            DiviShortcodeType::Button     => new ContentBag(content: $this->scalarOrNull($attrs['button_text'] ?? null)),
            DiviShortcodeType::CallToAction => new ContentBag(content: $this->scalarOrNull($attrs['content'] ?? null)),
            DiviShortcodeType::Code       => new ContentBag(content: $this->scalarOrNull($attrs['content'] ?? null)),
            DiviShortcodeType::Image      => new ContentBag(
                src: $this->scalarOrNull($attrs['src'] ?? null),
                alt: $this->scalarOrNull($attrs['alt'] ?? null),
            ),
            DiviShortcodeType::Video      => new ContentBag(src: $this->scalarOrNull($attrs['src'] ?? null)),
            default                       => new ContentBag(),
        };
    }

    private function scalarOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        return (string) $value;
    }

    private function concatTopBottom(?string $top, ?string $bottom): string
    {
        if ($top !== null && $bottom !== null) {
            return $top . ' ' . $bottom;
        }
        if ($top !== null) {
            return $top;
        }
        if ($bottom !== null) {
            return $bottom;
        }
        return '';
    }
}
