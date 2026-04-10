<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Report;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\AstTree;
use DiviToElementor\Ast\ContentBag;
use DiviToElementor\Ast\StyleBag;
use DiviToElementor\Injector\BatchResult;
use DiviToElementor\Injector\InjectionResult;
use DiviToElementor\Report\GlobalReport;
use DiviToElementor\Report\PostReport;
use DiviToElementor\Report\ReportBuilder;
use PHPUnit\Framework\TestCase;

class ReportBuilderTest extends TestCase
{
    private ReportBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->builder = new ReportBuilder();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeNode(string $type, string $status, array $children = []): AstNode
    {
        return new AstNode($type, $status, new StyleBag(), new ContentBag(), $children);
    }

    // AC-1 — coverage_percent = round((converted/(converted+fallback))*100)
    public function testCoveragePercent(): void
    {
        Functions\when('get_edit_post_link')->justReturn('http://example.com/?p=1');

        // 10 supported, 2 unsupported → round(10/12*100) = 83
        $section = $this->makeNode('et_pb_section', 'supported', [
            $this->makeNode('et_pb_column', 'supported', array_merge(
                array_fill(0, 10, $this->makeNode('et_pb_text', 'supported')),
                array_fill(0, 2, $this->makeNode('et_pb_code', 'unsupported'))
            )),
        ]);
        $ast    = new AstTree([$section]);
        $result = new InjectionResult(true, 1, null);

        $report = $this->builder->buildForPost(1, $result, $ast);

        $this->assertSame(10, $report->widgets_converted);
        $this->assertSame(2, $report->widgets_fallback);
        $this->assertSame(83, $report->coverage_percent);
    }

    // AC-1b — coverage_percent = 0 si aucun widget (pas de division par zéro)
    public function testCoveragePercentZeroWidgets(): void
    {
        Functions\when('get_edit_post_link')->justReturn('http://example.com/?p=1');

        $ast    = new AstTree([]);
        $result = new InjectionResult(true, 1, null);

        $report = $this->builder->buildForPost(1, $result, $ast);

        $this->assertSame(0, $report->widgets_converted);
        $this->assertSame(0, $report->widgets_fallback);
        $this->assertSame(0, $report->coverage_percent);
        $this->assertSame('success', $report->status);
    }

    // AC-2 — status='partial' si widgets_fallback > 0 && widgets_converted > 0
    public function testStatusPartial(): void
    {
        Functions\when('get_edit_post_link')->justReturn('http://example.com/?p=1');

        $section = $this->makeNode('et_pb_section', 'supported', [
            $this->makeNode('et_pb_column', 'supported', [
                $this->makeNode('et_pb_text', 'supported'),
                $this->makeNode('et_pb_code', 'unsupported'),
            ]),
        ]);
        $ast    = new AstTree([$section]);
        $result = new InjectionResult(true, 1, null);

        $report = $this->builder->buildForPost(1, $result, $ast);

        $this->assertSame('partial', $report->status);
    }

    // AC-2b — status='failed' si InjectionResult::isSuccess() === false
    public function testStatusFailedOnInjectionError(): void
    {
        Functions\when('get_edit_post_link')->justReturn('http://example.com/?p=1');

        $section = $this->makeNode('et_pb_section', 'supported', [
            $this->makeNode('et_pb_column', 'supported', [
                $this->makeNode('et_pb_text', 'supported'),
            ]),
        ]);
        $ast    = new AstTree([$section]);
        $result = new InjectionResult(false, 1, 'Injection failed');

        $report = $this->builder->buildForPost(1, $result, $ast);

        $this->assertSame('failed', $report->status);
    }

    // AC-3 — liste des modules non supportés avec section_index et column_index
    public function testUnsupportedModulesPosition(): void
    {
        Functions\when('get_edit_post_link')->justReturn('http://example.com/?p=1');

        // Section 0, Column 0 → et_pb_code unsupported
        $section0 = $this->makeNode('et_pb_section', 'supported', [
            $this->makeNode('et_pb_column', 'supported', [
                $this->makeNode('et_pb_text', 'supported'),
                $this->makeNode('et_pb_code', 'unsupported'),
            ]),
        ]);
        // Section 1, Column 0 → supported ; Column 1 → et_pb_icon unsupported
        $section1 = $this->makeNode('et_pb_section', 'supported', [
            $this->makeNode('et_pb_column', 'supported', [
                $this->makeNode('et_pb_text', 'supported'),
            ]),
            $this->makeNode('et_pb_column', 'supported', [
                $this->makeNode('et_pb_icon', 'unsupported'),
            ]),
        ]);
        $ast    = new AstTree([$section0, $section1]);
        $result = new InjectionResult(true, 1, null);

        $report = $this->builder->buildForPost(1, $result, $ast);

        $this->assertCount(2, $report->unsupported_modules);

        $this->assertSame('et_pb_code', $report->unsupported_modules[0]['module']);
        $this->assertSame(0, $report->unsupported_modules[0]['section_index']);
        $this->assertSame(0, $report->unsupported_modules[0]['column_index']);

        $this->assertSame('et_pb_icon', $report->unsupported_modules[1]['module']);
        $this->assertSame(1, $report->unsupported_modules[1]['section_index']);
        $this->assertSame(1, $report->unsupported_modules[1]['column_index']);
    }

    // AC-8 — GlobalReport::requires_manual_review liste les post_id avec widgets_fallback > 0
    public function testRequiresManualReview(): void
    {
        $report1 = new PostReport(1, 'success', 5, 0, 100, [], 'http://example.com/1', '2026-04-10T00:00:00+00:00');
        $report2 = new PostReport(2, 'partial', 3, 2, 60, [], 'http://example.com/2', '2026-04-10T00:00:00+00:00');
        $report3 = new PostReport(3, 'failed', 0, 4, 0, [], 'http://example.com/3', '2026-04-10T00:00:00+00:00');

        $batch = new BatchResult(3, 1, 2, [], [$report1, $report2, $report3]);

        $global = $this->builder->buildGlobal($batch);

        $this->assertSame(3, $global->total);
        $this->assertSame(1, $global->success);
        $this->assertSame(1, $global->partial);
        $this->assertSame(1, $global->failed);
        $this->assertSame([2, 3], $global->requires_manual_review);
    }
}
