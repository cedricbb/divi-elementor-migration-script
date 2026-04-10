<?php

declare(strict_types=1);

namespace DiviToElementor\Injector;

readonly class BatchResult
{
    /**
     * @param InjectionResult[] $items
     */
    public function __construct(
        private int $processed,
        private int $success,
        private int $failed,
        private array $items,
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
}
