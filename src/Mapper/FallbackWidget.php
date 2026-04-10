<?php

declare(strict_types=1);

namespace DiviToElementor\Mapper;

use DiviToElementor\Ast\AstNode;

class FallbackWidget
{
    /**
     * Génère un widget html de secours pour les types non supportés.
     *
     * @param  AstNode $node  Nœud AST non supporté
     * @return array          ['widgetType'=>'html', 'settings'=>['html'=>string]]
     */
    public static function make(AstNode $node): array
    {
        $rawShortcode = $node->raw_shortcode !== null
            ? htmlspecialchars($node->raw_shortcode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : '';

        $html = '<div class="divi-migration-unsupported">'
            . "\n<!-- Module Divi non converti : {$node->type} -->\n"
            . $rawShortcode
            . "\n</div>";

        return [
            'widgetType' => 'html',
            'settings'   => [
                'html' => $html,
            ],
        ];
    }
}
