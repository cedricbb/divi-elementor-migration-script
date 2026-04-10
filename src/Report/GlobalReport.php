<?php

declare(strict_types=1);

namespace DiviToElementor\Report;

readonly class GlobalReport
{
    /**
     * @param PostReport[] $items
     * @param int[]        $requires_manual_review
     */
    public function __construct(
        public int   $total,
        public int   $success,
        public int   $partial,
        public int   $failed,
        public array $items,
        public array $requires_manual_review,
    ) {}

    public function toArray(): array
    {
        return [
            'total'                  => $this->total,
            'success'                => $this->success,
            'partial'                => $this->partial,
            'failed'                 => $this->failed,
            'items'                  => array_map(static fn(PostReport $r) => $r->toArray(), $this->items),
            'requires_manual_review' => $this->requires_manual_review,
        ];
    }

    public static function fromArray(array $data): self
    {
        $items = array_map(
            static fn(array $item) => PostReport::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            total:                 (int)($data['total'] ?? 0),
            success:               (int)($data['success'] ?? 0),
            partial:               (int)($data['partial'] ?? 0),
            failed:                (int)($data['failed'] ?? 0),
            items:                 $items,
            requires_manual_review: (array)($data['requires_manual_review'] ?? []),
        );
    }
}
