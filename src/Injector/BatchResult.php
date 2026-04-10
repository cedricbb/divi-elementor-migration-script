<?php

declare(strict_types=1);

namespace DiviToElementor\Injector;

use DiviToElementor\Report\PostReport;

readonly class BatchResult
{
    /**
     * @param InjectionResult[] $items
     * @param PostReport[]      $postReports  Rapports construits par ReportBuilder (défaut [] pour rétrocompatibilité)
     */
    public function __construct(
        private int   $processed,
        private int   $success,
        private int   $failed,
        private array $items,
        private array $postReports = [],
    ) {}

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getSuccess(): int
    {
        return $this->success;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    /** @return InjectionResult[] */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @return PostReport[] */
    public function getPostReports(): array
    {
        return $this->postReports;
    }
}
