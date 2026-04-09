<?php

declare(strict_types=1);

namespace DiviToElementor\Parser;

class DiviNode
{
    /**
     * @param DiviShortcodeType $type       Shortcode type resolved from tag name.
     * @param array<string,string> $attributes Key/value shortcode attributes.
     * @param array<int,DiviNode>  $children   Nested child nodes.
     * @param string               $raw        Full raw shortcode string (tag + attributes + inner content).
     * @param string               $status     One of: 'supported', 'unsupported', 'malformed'.
     */
    public function __construct(
        public readonly DiviShortcodeType $type,
        public readonly array             $attributes,
        public readonly array             $children,
        public readonly string            $raw,
        public readonly string            $status,
    ) {
    }

    /**
     * Returns a plain-array representation.
     * All 5 keys (type, attributes, children, raw, status) are always present.
     *
     * @return array{type: string, attributes: array<string,string>, children: array<int,array>, raw: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'type'       => $this->type->value,
            'attributes' => $this->attributes,
            'children'   => array_map(
                static fn(DiviNode $child): array => $child->toArray(),
                $this->children
            ),
            'raw'        => $this->raw,
            'status'     => $this->status,
        ];
    }
}
