<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Report;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DiviToElementor\Report\PostReport;
use DiviToElementor\Report\ReportStore;
use PHPUnit\Framework\TestCase;

class ReportStoreTest extends TestCase
{
    private ReportStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->store = new ReportStore();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makePostReport(): PostReport
    {
        return new PostReport(42, 'success', 5, 0, 100, [], 'http://example.com/?p=42', '2026-04-10T00:00:00+00:00');
    }

    // AC-4 — save() persiste sous la clé divi_migration_report_{post_id} via update_option()
    public function testSaveCallsUpdateOptionWithCorrectKey(): void
    {
        $savedKey   = null;
        $savedValue = null;

        Functions\when('update_option')->alias(function (string $key, string $value) use (&$savedKey, &$savedValue): bool {
            $savedKey   = $key;
            $savedValue = $value;
            return true;
        });

        $report = $this->makePostReport();
        $this->store->save($report);

        $this->assertSame('divi_migration_report_42', $savedKey);
        $decoded = json_decode($savedValue, true);
        $this->assertIsArray($decoded);
        $this->assertSame(42, $decoded['post_id']);
    }

    // AC-5 — load() retourne null si option absente (get_option retourne false)
    public function testLoadReturnsNullWhenOptionMissing(): void
    {
        Functions\when('get_option')->justReturn(false);

        $result = $this->store->load(42);

        $this->assertNull($result);
    }

    // AC-5b — load() retourne null si JSON corrompu
    public function testLoadReturnsNullOnCorruptJson(): void
    {
        Functions\when('get_option')->justReturn('not valid json{{{');

        $result = $this->store->load(42);

        $this->assertNull($result);
    }
}
