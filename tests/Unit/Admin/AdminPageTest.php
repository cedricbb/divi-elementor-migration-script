<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DiviToElementor\Admin\MigrationAdminPage;
use DiviToElementor\Report\ReportExporter;
use DiviToElementor\Report\ReportStore;
use PHPUnit\Framework\TestCase;

class AdminPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // AC-9 — render() appelle wp_die() si current_user_can('manage_options') est false
    public function testRenderDiesWithoutManageOptions(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('__')->returnArg(1);
        Functions\when('wp_die')->alias(static function (): never {
            throw new \RuntimeException('wp_die called');
        });

        $store    = $this->createMock(ReportStore::class);
        $exporter = $this->createMock(ReportExporter::class);
        $page     = new MigrationAdminPage($store, $exporter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die called');

        $page->render();
    }

    // AC-9b — handleExportJson() vérifie check_admin_referer ET current_user_can('manage_options')
    public function testExportJsonRequiresNonceAndCapability(): void
    {
        Functions\expect('check_admin_referer')->once()->with('divi_migration_export')->andReturn(1);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('__')->returnArg(1);
        Functions\when('wp_die')->alias(static function (): never {
            throw new \RuntimeException('wp_die called');
        });

        $store    = $this->createMock(ReportStore::class);
        $exporter = $this->createMock(ReportExporter::class);
        $page     = new MigrationAdminPage($store, $exporter);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die called');

        $page->handleExportJson();
    }
}
