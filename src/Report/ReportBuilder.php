<?php

declare(strict_types=1);

namespace DiviToElementor\Report;

use DiviToElementor\Ast\AstNode;
use DiviToElementor\Ast\AstTree;
use DiviToElementor\Injector\BatchResult;
use DiviToElementor\Injector\InjectionResult;

class ReportBuilder
{
    private const STRUCTURAL_TYPES = [
        'et_pb_section',
        'et_pb_row',
        'et_pb_row_inner',
        'et_pb_column',
        'et_pb_column_inner',
    ];

    public function buildForPost(int $post_id, InjectionResult $result, AstTree $ast): PostReport
    {
        $converted   = 0;
        $fallback    = 0;
        $unsupported = [];

        $this->walkNodes($ast->getNodes(), 0, 0, $converted, $fallback, $unsupported);

        $total = $converted + $fallback;

        if (!$result->isSuccess()) {
            $status = 'failed';
        } elseif ($fallback === 0) {
            $status = 'success';
        } elseif ($converted > 0) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        $coveragePercent = $total > 0 ? (int)round(($converted / $total) * 100) : 0;

        $editLink         = get_edit_post_link($post_id);
        $elementorEditUrl = $editLink !== null ? $editLink . '&action=elementor' : '';

        return new PostReport(
            post_id:             $post_id,
            status:              $status,
            widgets_converted:   $converted,
            widgets_fallback:    $fallback,
            coverage_percent:    $coveragePercent,
            unsupported_modules: $unsupported,
            elementor_edit_url:  $elementorEditUrl,
            migration_date:      (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );
    }

    public function buildGlobal(BatchResult $batch): GlobalReport
    {
        $postReports          = $batch->getPostReports();
        $total                = count($postReports);
        $success              = 0;
        $partial              = 0;
        $failed               = 0;
        $requiresManualReview = [];

        foreach ($postReports as $report) {
            match ($report->status) {
                'success' => $success++,
                'partial' => $partial++,
                default   => $failed++,
            };

            if ($report->widgets_fallback > 0) {
                $requiresManualReview[] = $report->post_id;
            }
        }

        return new GlobalReport(
            total:                 $total,
            success:               $success,
            partial:               $partial,
            failed:                $failed,
            items:                 $postReports,
            requires_manual_review: $requiresManualReview,
        );
    }

    /**
     * @param AstNode[] $nodes
     * @param int       $section_index  Index de section courant (0-based)
     * @param int       $column_index   Index de colonne courant dans la section (0-based)
     * @param int       &$converted
     * @param int       &$fallback
     * @param array     &$unsupported
     */
    private function walkNodes(
        array $nodes,
        int $section_index,
        int $column_index,
        int &$converted,
        int &$fallback,
        array &$unsupported,
    ): void {
        $currentSection = $section_index;
        $currentColumn  = $column_index;

        foreach ($nodes as $node) {
            if ($node->type === 'et_pb_section') {
                $this->walkNodes($node->children, $currentSection, 0, $converted, $fallback, $unsupported);
                $currentSection++;
            } elseif ($node->type === 'et_pb_row' || $node->type === 'et_pb_row_inner') {
                // Rows are transparent — process their children inline to keep column_index continuity
                foreach ($node->children as $child) {
                    if ($child->type === 'et_pb_column' || $child->type === 'et_pb_column_inner') {
                        $this->walkNodes($child->children, $section_index, $currentColumn, $converted, $fallback, $unsupported);
                        $currentColumn++;
                    } else {
                        $this->walkNodes([$child], $section_index, $currentColumn, $converted, $fallback, $unsupported);
                    }
                }
            } elseif ($node->type === 'et_pb_column' || $node->type === 'et_pb_column_inner') {
                $this->walkNodes($node->children, $section_index, $currentColumn, $converted, $fallback, $unsupported);
                $currentColumn++;
            } else {
                // Widget leaf
                if ($node->status === 'supported') {
                    $converted++;
                } else {
                    $fallback++;
                    $unsupported[] = [
                        'module'        => $node->type,
                        'section_index' => $section_index,
                        'column_index'  => $column_index,
                    ];
                }
            }
        }
    }
}
