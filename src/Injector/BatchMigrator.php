<?php

declare(strict_types=1);

namespace DiviToElementor\Injector;

use DiviToElementor\Ast\AstTree;
use DiviToElementor\Report\ReportBuilder;

class BatchMigrator
{
    public function __construct(
        private Injector        $injector,
        private \Closure        $elementorDataFetcher,
        private ?ReportBuilder  $reportBuilder = null,
        private ?\Closure       $astFetcher    = null,
    ) {}

    /**
     * Migre une liste de posts en lots.
     *
     * Contrats :
     * - Tronquer $postIds à $limit éléments
     * - try/catch par post : un échec ne stoppe pas le batch
     * - Retourner BatchResult avec compteurs processed/success/failed
     * - Si reportBuilder et astFetcher fournis : alimenter BatchResult::$postReports
     */
    public function migrate(array $postIds, int $limit): BatchResult
    {
        $postIds     = array_slice($postIds, 0, $limit);
        $items       = [];
        $postReports = [];

        foreach ($postIds as $postId) {
            try {
                $injectionResult = $this->injector->inject($postId, $this->getElementorData($postId));
                $items[]         = $injectionResult;

                if ($this->reportBuilder !== null && $this->astFetcher !== null) {
                    /** @var AstTree $ast */
                    $ast           = ($this->astFetcher)($postId);
                    $postReports[] = $this->reportBuilder->buildForPost($postId, $injectionResult, $ast);
                }
            } catch (\Throwable $e) {
                $items[] = new InjectionResult(false, $postId, $e->getMessage());
            }
        }

        $success = count(array_filter($items, static fn(InjectionResult $r) => $r->isSuccess()));
        $failed  = count($items) - $success;

        return new BatchResult(count($items), $success, $failed, $items, $postReports);
    }

    private function getElementorData(int $postId): array
    {
        return ($this->elementorDataFetcher)($postId);
    }
}
