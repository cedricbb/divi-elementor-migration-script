<?php

declare(strict_types=1);

namespace DiviToElementor\Mapper;

use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\AstTree;

class ElementorMapper
{
    public function __construct(
        private WidgetFactory $widgetFactory,
        private IdGenerator   $idGenerator,
    ) {}

    /**
     * Point d'entrée principal.
     * Transforme l'AstTree en tableau PHP sérialisable en JSON (_elementor_data).
     *
     * @param  AstTree $ast  Arbre AST Divi normalisé
     * @return array         Tableau de sections Elementor (prêt pour json_encode)
     */
    public function map(AstTree $ast): array
    {
        $sections = [];
        foreach ($ast->getNodes() as $node) {
            if ($node->type === 'et_pb_section') {
                $sections[] = $this->mapSection($node);
            }
        }
        return $sections;
    }

    /**
     * Mappe un nœud de type 'et_pb_section' en structure section Elementor.
     * Descend dans les Row pour extraire directement les colonnes (EC-1).
     *
     * @param  AstNode $node  Nœud AST de type 'et_pb_section'
     * @return array
     */
    public function mapSection(AstNode $node): array
    {
        $columns = [];
        foreach ($node->children as $child) {
            if ($child->type === 'et_pb_row' || $child->type === 'et_pb_row_inner') {
                // EC-1: Row est transparente — on récupère ses colonnes directement
                foreach ($child->children as $columnNode) {
                    $columns[] = $this->mapColumn($columnNode);
                }
            } elseif ($child->type === 'et_pb_column' || $child->type === 'et_pb_column_inner') {
                $columns[] = $this->mapColumn($child);
            }
        }

        return [
            'id'       => $this->idGenerator->generate(),
            'elType'   => 'section',
            'settings' => [
                'background_color' => $node->styles->background_color ?? '',
            ],
            'elements' => $columns,
        ];
    }

    /**
     * Mappe un nœud de type 'et_pb_column'|'et_pb_column_inner' en structure column Elementor.
     *
     * @param  AstNode $node  Nœud AST de type 'et_pb_column'
     * @return array
     */
    public function mapColumn(AstNode $node): array
    {
        $widgets = [];
        foreach ($node->children as $child) {
            $widgets[] = $this->mapWidget($child);
        }

        return [
            'id'       => $this->idGenerator->generate(),
            'elType'   => 'column',
            'settings' => [
                '_column_size' => (string) ($node->data->width ?? 100),
            ],
            'elements' => $widgets,
        ];
    }

    /**
     * Mappe un nœud widget (feuille) en structure widget Elementor via WidgetFactory.
     *
     * @param  AstNode $node  Nœud AST de type widget (ex: 'et_pb_text')
     * @return array
     */
    public function mapWidget(AstNode $node): array
    {
        $widget = $this->widgetFactory->make($node->type, $node);

        return [
            'id'         => $this->idGenerator->generate(),
            'elType'     => 'widget',
            'widgetType' => $widget['widgetType'],
            'settings'   => $widget['settings'],
            'elements'   => [],
        ];
    }
}
