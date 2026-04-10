<?php

declare(strict_types=1);

namespace DiviToElementor\Report;

class ReportStore
{
    public function save(PostReport $report): void
    {
        update_option(
            "divi_migration_report_{$report->post_id}",
            json_encode($report->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    public function saveGlobal(GlobalReport $report): void
    {
        update_option(
            'divi_migration_report_global',
            json_encode($report->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    public function load(int $post_id): ?PostReport
    {
        $raw = get_option("divi_migration_report_{$post_id}", null);

        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }

        $data = json_decode((string)$raw, true);

        if (!is_array($data)) {
            return null;
        }

        return PostReport::fromArray($data);
    }

    public function loadGlobal(): ?GlobalReport
    {
        $raw = get_option('divi_migration_report_global', null);

        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }

        $data = json_decode((string)$raw, true);

        if (!is_array($data)) {
            return null;
        }

        return GlobalReport::fromArray($data);
    }
}
