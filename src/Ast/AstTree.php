<?php

declare(strict_types=1);

namespace DiviToElementor\Ast;

class AstTree
{
    /** @var AstNode[] */
    private array $nodes;

    /**
     * @param AstNode[] $nodes  Noeuds racines de l'arbre AST.
     */
    public function __construct(array $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * Sérialise l'arbre en JSON.
     * Utilise JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR.
     *
     * @throws \JsonException Si la sérialisation échoue.
     */
    public function toJson(): string
    {
        return json_encode(
            ['nodes' => array_map(
                static fn(AstNode $node): array => $node->toArray(),
                $this->nodes
            )],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return AstNode[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
