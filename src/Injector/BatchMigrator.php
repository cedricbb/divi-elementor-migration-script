<?php

declare(strict_types=1);

namespace DiviToElementor\Injector;

class BatchMigrator
{
    public function __construct(
        private Injector $injector,
        private \Closure $elementorDataFetcher,
    ) {}

    /**
     * Migre une liste de posts en lots.
     *
     * Contrats :
     * - Tronquer $postIds à $limit éléments
     * - try/catch par post : un échec ne stoppe pas le batch
     * - Retourner BatchResult avec compteurs processed/success/failed
     */
    public function migrate(array $postIds, int $limit): BatchResult
    {
        $postIds = array_slice($postIds, 0, $limit);
        $items = [];

        foreach ($postIds as $postId) {
            try {
                $items[] = $this->injector->inject($postId, $this->getElementorData($postId));
            } catch (\Throwable $e) {
                $items[] = new InjectionResult(false, $postId, $e->getMessage());
            }
        }

        $success = count(array_filter($items, static fn(InjectionResult $r) => $r->isSuccess()));
        $failed  = count($items) - $success;

        return new BatchResult(count($items), $success, $failed, $items);
    }

    private function getElementorData(int $postId): array
    {
        return ($this->elementorDataFetcher)($postId);
    }
}
