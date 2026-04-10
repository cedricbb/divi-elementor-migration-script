<?php

declare(strict_types=1);

namespace DiviToElementor\Injector;

class Injector
{
    /**
     * Sauvegarde atomique du post_content Divi avant toute écriture Elementor.
     * Contrat no-overwrite : si la meta existe déjà, sortie immédiate.
     */
    public function backup(int $post_id): void
    {
        $existing = get_post_meta($post_id, '_divi_migration_backup', true);
        if (!empty($existing)) {
            return;
        }

        $content = get_post_field('post_content', $post_id);
        update_post_meta($post_id, '_divi_migration_backup', wp_json_encode($content));
        update_post_meta($post_id, '_divi_migration_date', date('c'));
    }

    /**
     * Injecte les données Elementor dans les meta WordPress du post.
     *
     * Ordre strict :
     * 1. Sécurité (current_user_can)
     * 2. Backup
     * 3. Encodage wp_slash(wp_json_encode($data))
     * 4. Écriture meta
     * 5. Invalidation cache
     */
    public function inject(int $post_id, array $elementorData): InjectionResult
    {
        if ($post_id <= 0) {
            return new InjectionResult(false, $post_id, 'Invalid post_id');
        }

        if (!current_user_can('edit_posts')) {
            return new InjectionResult(false, $post_id, 'Unauthorized');
        }

        try {
            $this->backup($post_id);

            $encoded = wp_json_encode($elementorData);
            if ($encoded === false) {
                return new InjectionResult(false, $post_id, 'JSON encode failed');
            }
            $json = wp_slash($encoded);

            update_post_meta($post_id, '_elementor_data', $json);
            update_post_meta($post_id, '_elementor_edit_mode', 'builder');
            update_post_meta($post_id, '_elementor_template_type', 'wp-page');
            update_post_meta($post_id, '_divi_migration_date_last', date('c'));

            delete_post_meta($post_id, '_elementor_css');
            clean_post_cache($post_id);

            return new InjectionResult(true, $post_id, null);
        } catch (\Throwable $e) {
            return new InjectionResult(false, $post_id, $e->getMessage());
        }
    }

    /**
     * Restaure le post_content Divi original depuis la sauvegarde.
     *
     * Ordre strict :
     * 1. Sécurité (current_user_can)
     * 2. Présence backup
     * 3. Restauration + nettoyage meta Elementor
     */
    public function rollback(int $post_id): RollbackResult
    {
        if (!current_user_can('edit_posts')) {
            return new RollbackResult(false, $post_id, 'Unauthorized');
        }

        $backup = get_post_meta($post_id, '_divi_migration_backup', true);
        if (empty($backup)) {
            return new RollbackResult(false, $post_id, 'Aucune sauvegarde disponible');
        }

        $originalContent = json_decode($backup, true);

        wp_update_post(['ID' => $post_id, 'post_content' => $originalContent]);
        delete_post_meta($post_id, '_elementor_data');
        delete_post_meta($post_id, '_elementor_edit_mode');
        delete_post_meta($post_id, '_elementor_template_type');
        clean_post_cache($post_id);

        return new RollbackResult(true, $post_id, null);
    }
}
