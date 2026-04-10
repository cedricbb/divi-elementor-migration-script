<?php

declare(strict_types=1);

namespace DiviToElementor\Report;

class ReportExporter
{
    /**
     * @throws \JsonException
     */
    public function toJson(GlobalReport $report): string
    {
        return json_encode(
            $report->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
    }

    public function toCsv(GlobalReport $report): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['post_id', 'status', 'coverage_percent', 'widgets_fallback', 'date']);

        foreach ($report->items as $item) {
            fputcsv($handle, [
                $item->post_id,
                $item->status,
                $item->coverage_percent,
                $item->widgets_fallback,
                $item->migration_date,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string)$csv;
    }
}
