<?php

declare(strict_types=1);

namespace DiviToElementor\Report;

readonly class PostReport
{
    /**
     * @param array<int, array{module: string, section_index: int, column_index: int}> $unsupported_modules
     */
    public function __construct(
        public int    $post_id,
        public string $status,
        public int    $widgets_converted,
        public int    $widgets_fallback,
        public int    $coverage_percent,
        public array  $unsupported_modules,
        public string $elementor_edit_url,
        public string $migration_date,
    ) {}

    public function toArray(): array
    {
        return [
            'post_id'             => $this->post_id,
            'status'              => $this->status,
            'widgets_converted'   => $this->widgets_converted,
            'widgets_fallback'    => $this->widgets_fallback,
            'coverage_percent'    => $this->coverage_percent,
            'unsupported_modules' => $this->unsupported_modules,
            'elementor_edit_url'  => $this->elementor_edit_url,
            'migration_date'      => $this->migration_date,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            post_id:             (int)($data['post_id'] ?? 0),
            status:              (string)($data['status'] ?? 'failed'),
            widgets_converted:   (int)($data['widgets_converted'] ?? 0),
            widgets_fallback:    (int)($data['widgets_fallback'] ?? 0),
            coverage_percent:    (int)($data['coverage_percent'] ?? 0),
            unsupported_modules: (array)($data['unsupported_modules'] ?? []),
            elementor_edit_url:  (string)($data['elementor_edit_url'] ?? ''),
            migration_date:      (string)($data['migration_date'] ?? ''),
        );
    }
}
