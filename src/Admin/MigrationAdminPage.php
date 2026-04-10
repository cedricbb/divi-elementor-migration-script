<?php

declare(strict_types=1);

namespace DiviToElementor\Admin;

use DiviToElementor\Report\GlobalReport;
use DiviToElementor\Report\PostReport;
use DiviToElementor\Report\ReportExporter;
use DiviToElementor\Report\ReportStore;

class MigrationAdminPage
{
    public function __construct(
        private ReportStore    $store,
        private ReportExporter $exporter,
    ) {}

    /**
     * Enregistre la page dans le menu admin WordPress.
     * Hook : add_action('admin_menu', [$this, 'register'])
     */
    public function register(): void
    {
        add_menu_page(
            __('Migration Report', 'divi-to-elementor'),
            __('Migration Report', 'divi-to-elementor'),
            'manage_options',
            'divi-migration-report',
            [$this, 'render']
        );
        add_action('admin_post_divi_migration_export_json', [$this, 'handleExportJson']);
        add_action('admin_post_divi_migration_export_csv', [$this, 'handleExportCsv']);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'divi-to-elementor'));
        }

        $report = $this->store->loadGlobal();

        $filterStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
        if (!in_array($filterStatus, ['success', 'partial', 'failed', ''], true)) {
            $filterStatus = '';
        }

        $items = $report ? $report->items : [];

        if ($filterStatus !== '') {
            $items = array_values(array_filter($items, static fn(PostReport $r) => $r->status === $filterStatus));
        }

        $page    = max(1, (int)(isset($_GET['paged']) ? $_GET['paged'] : 1));
        $perPage = 50;
        $items   = array_slice($items, ($page - 1) * $perPage, $perPage);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Divi Migration Report', 'divi-to-elementor') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('divi_migration_export');
        echo '<input type="hidden" name="action" value="divi_migration_export_json">';
        echo '<button type="submit">' . esc_html__('Download JSON', 'divi-to-elementor') . '</button>';
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('divi_migration_export');
        echo '<input type="hidden" name="action" value="divi_migration_export_csv">';
        echo '<button type="submit">' . esc_html__('Download CSV', 'divi-to-elementor') . '</button>';
        echo '</form>';
        echo '<table class="widefat"><thead><tr>';
        echo '<th>' . esc_html__('Post ID', 'divi-to-elementor') . '</th>';
        echo '<th>' . esc_html__('Status', 'divi-to-elementor') . '</th>';
        echo '<th>' . esc_html__('Coverage', 'divi-to-elementor') . '</th>';
        echo '<th>' . esc_html__('Fallback', 'divi-to-elementor') . '</th>';
        echo '<th>' . esc_html__('Date', 'divi-to-elementor') . '</th>';
        echo '<th>' . esc_html__('Edit', 'divi-to-elementor') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html((string)$item->post_id) . '</td>';
            echo '<td>' . esc_html($item->status) . '</td>';
            echo '<td>' . esc_html((string)$item->coverage_percent) . '%</td>';
            echo '<td>' . esc_html((string)$item->widgets_fallback) . '</td>';
            echo '<td>' . esc_html($item->migration_date) . '</td>';
            echo '<td><a href="' . esc_url($item->elementor_edit_url) . '">' . esc_html__('Edit', 'divi-to-elementor') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function handleExportJson(): void
    {
        check_admin_referer('divi_migration_export');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'divi-to-elementor'), 403);
        }

        $report = $this->store->loadGlobal() ?? new GlobalReport(0, 0, 0, 0, [], []);
        $json   = $this->exporter->toJson($report);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="migration-report.json"');
        echo $json;
        exit;
    }

    public function handleExportCsv(): void
    {
        check_admin_referer('divi_migration_export');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'divi-to-elementor'), 403);
        }

        $report = $this->store->loadGlobal() ?? new GlobalReport(0, 0, 0, 0, [], []);
        $csv    = $this->exporter->toCsv($report);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="migration-report.csv"');
        echo $csv;
        exit;
    }
}
