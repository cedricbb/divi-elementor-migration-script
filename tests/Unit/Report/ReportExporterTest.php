<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Report;

use DiviToElementor\Report\GlobalReport;
use DiviToElementor\Report\PostReport;
use DiviToElementor\Report\ReportExporter;
use PHPUnit\Framework\TestCase;

class ReportExporterTest extends TestCase
{
    private ReportExporter $exporter;
    private GlobalReport   $globalReport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new ReportExporter();
        $postReport     = new PostReport(1, 'success', 5, 0, 100, [], 'http://example.com/1', '2026-04-10T00:00:00+00:00');
        $this->globalReport = new GlobalReport(1, 1, 0, 0, [$postReport], []);
    }

    // AC-6 — toJson() retourne un JSON valide désérialisable sans perte
    public function testToJsonIsValidAndLossless(): void
    {
        $json    = $this->exporter->toJson($this->globalReport);
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded, 'JSON must be valid and decodable');
        $this->assertSame(1, $decoded['total']);
        $this->assertSame(1, $decoded['success']);
        $this->assertSame(0, $decoded['partial']);
        $this->assertSame(0, $decoded['failed']);
        $this->assertCount(1, $decoded['items']);
        $this->assertSame(1, $decoded['items'][0]['post_id']);
        $this->assertSame('success', $decoded['items'][0]['status']);
    }

    // AC-7 — toCsv() retourne CSV avec headers post_id,status,coverage_percent,widgets_fallback,date
    public function testToCsvHeaders(): void
    {
        $csv   = $this->exporter->toCsv($this->globalReport);
        $lines = preg_split('/\r?\n/', trim($csv));

        $this->assertSame('post_id,status,coverage_percent,widgets_fallback,date', $lines[0]);
        $this->assertCount(2, $lines, 'Should have header line + 1 data row');
    }
}
