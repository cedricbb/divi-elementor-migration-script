<?php

declare(strict_types=1);

namespace DiviToElementor\Ast;

readonly class ContentBag
{
    /**
     * @param ?string $content Contenu texte HTML pour et_pb_text, et_pb_button, etc.
     * @param ?string $src     URL source pour et_pb_image, et_pb_video
     * @param ?string $alt     Texte alternatif pour et_pb_image
     * @param ?int    $width   Largeur normalisée en % pour Column/ColumnInner (ex: 50)
     */
    public function __construct(
        public ?string $content = null,
        public ?string $src     = null,
        public ?string $alt     = null,
        public ?int    $width   = null,
    ) {}
}
