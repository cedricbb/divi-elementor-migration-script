<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Injector;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DiviToElementor\Injector\Injector;
use PHPUnit\Framework\TestCase;

class RollbackTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->injector = new Injector();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // AC-8 — rollback() supprime _elementor_data, _elementor_edit_mode, _elementor_template_type, restaure post_content
    public function testRollbackRestoresContent(): void
    {
        $postId = 42;
        $originalContent = 'original divi shortcode content';
        $backupJson = json_encode($originalContent);

        $updatePostArgs = null;
        $deletedKeys = [];
        $cleanCacheCalled = false;

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post_meta')->justReturn($backupJson);
        Functions\when('wp_update_post')->alias(function (array $args) use (&$updatePostArgs): int {
            $updatePostArgs = $args;
            return $args['ID'];
        });
        Functions\when('delete_post_meta')->alias(function (int $id, string $key) use (&$deletedKeys): bool {
            $deletedKeys[] = $key;
            return true;
        });
        Functions\when('clean_post_cache')->alias(function (int $id) use (&$cleanCacheCalled): void {
            $cleanCacheCalled = true;
        });

        $result = $this->injector->rollback($postId);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($postId, $result->getPostId());
        $this->assertNull($result->getError());

        $this->assertNotNull($updatePostArgs, 'wp_update_post must be called');
        $this->assertSame($originalContent, $updatePostArgs['post_content']);

        $this->assertContains('_elementor_data', $deletedKeys);
        $this->assertContains('_elementor_edit_mode', $deletedKeys);
        $this->assertContains('_elementor_template_type', $deletedKeys);

        $this->assertTrue($cleanCacheCalled);
    }

    // AC-9 — rollback() retourne RollbackResult{success:false, error:'Aucune sauvegarde disponible'} si meta absente (EC-2)
    public function testRollbackFailsWhenNoBackup(): void
    {
        $postId = 42;
        $writeCalls = [];

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_update_post')->alias(function (array $args) use (&$writeCalls): int {
            $writeCalls[] = 'wp_update_post';
            return $args['ID'];
        });
        Functions\when('delete_post_meta')->alias(function (int $id, string $key) use (&$writeCalls): bool {
            $writeCalls[] = 'delete:' . $key;
            return true;
        });

        $result = $this->injector->rollback($postId);

        $this->assertFalse($result->isSuccess());
        $this->assertSame($postId, $result->getPostId());
        $this->assertSame('Aucune sauvegarde disponible', $result->getError());
        $this->assertEmpty($writeCalls, 'No WP write functions should be called when no backup exists');
    }
}
