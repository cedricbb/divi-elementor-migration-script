<?php

declare(strict_types=1);

namespace DiviToElementor\Ast;

readonly class StyleBag
{
    /**
     * @param ?string $background_color Ex: '#ffffff' ou null si absent
     * @param string  $padding          Ex: '10px 20px' — chaîne vide '' si les deux attributs absents
     * @param string  $margin           Ex: '0px' — chaîne vide '' si les deux attributs absents
     * @param ?string $text_align       'left'|'center'|'right'|'justify' ou null
     * @param ?string $font_size        Ex: '16px' ou null
     * @param ?string $color            Ex: '#333333' ou null
     */
    public function __construct(
        public ?string $background_color = null,
        public string  $padding          = '',
        public string  $margin           = '',
        public ?string $text_align       = null,
        public ?string $font_size        = null,
        public ?string $color            = null,
    ) {}
}
