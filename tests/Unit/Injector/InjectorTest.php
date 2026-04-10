<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Injector;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DiviToElementor\Injector\Injector;
use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
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

    // AC-1 — inject() écrit _elementor_data, _elementor_edit_mode, _elementor_template_type
    public function testInjectWritesThreeMeta(): void
    {
        $postId = 42;
        $writtenKeys = [];

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_post_field')->justReturn('original content');
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('wp_slash')->returnArg(1);
        Functions\when('update_post_meta')->alias(function (int $id, string $key) use (&$writtenKeys): bool {
            $writtenKeys[] = $key;
            return true;
        });
        Functions\when('delete_post_meta')->justReturn(true);
        Functions\when('clean_post_cache')->justReturn(null);

        $result = $this->injector->inject($postId, ['type' => 'section']);

        $this->assertTrue($result->isSuccess());
        $this->assertContains('_elementor_data', $writtenKeys);
        $this->assertContains('_elementor_edit_mode', $writtenKeys);
        $this->assertContains('_elementor_template_type', $writtenKeys);
    }

    // AC-2 — backup() écrit _divi_migration_backup (JSON du post_content) et _divi_migration_date
    public function testBackupWritesBackupMeta(): void
    {
        $postId = 10;
        $originalContent = 'divi shortcode content';
        $writtenMeta = [];

        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_post_field')->justReturn($originalContent);
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('update_post_meta')->alias(function (int $id, string $key, $val) use (&$writtenMeta): bool {
            $writtenMeta[$key] = $val;
            return true;
        });

        $this->injector->backup($postId);

        $this->assertArrayHasKey('_divi_migration_backup', $writtenMeta);
        $this->assertArrayHasKey('_divi_migration_date', $writtenMeta);
        $this->assertSame(json_encode($originalContent), $writtenMeta['_divi_migration_backup']);
    }

    // AC-3 — backup() ne modifie pas _divi_migration_backup si la meta existe déjà (EC-1)
    public function testBackupDoesNotOverwriteExistingBackup(): void
    {
        $postId = 10;
        $writtenKeys = [];

        Functions\when('get_post_meta')->justReturn('{"existing":"backup"}');
        Functions\when('update_post_meta')->alias(function (int $id, string $key) use (&$writtenKeys): bool {
            $writtenKeys[] = $key;
            return true;
        });

        $this->injector->backup($postId);

        $this->assertNotContains('_divi_migration_backup', $writtenKeys);
        $this->assertNotContains('_divi_migration_date', $writtenKeys);
    }

    // AC-4 — inject() appelle clean_post_cache() et supprime _elementor_css
    public function testInjectInvalidatesCache(): void
    {
        $postId = 42;
        $deletedKeys = [];
        $cleanCacheCalled = false;

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_post_field')->justReturn('content');
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('wp_slash')->returnArg(1);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('delete_post_meta')->alias(function (int $id, string $key) use (&$deletedKeys): bool {
            $deletedKeys[] = $key;
            return true;
        });
        Functions\when('clean_post_cache')->alias(function (int $id) use (&$cleanCacheCalled): void {
            $cleanCacheCalled = true;
        });

        $this->injector->inject($postId, []);

        $this->assertContains('_elementor_css', $deletedKeys);
        $this->assertTrue($cleanCacheCalled);
    }

    // AC-5 — inject() retourne InjectionResult{success:false, error:'Unauthorized'} si current_user_can=false (EC-3)
    public function testInjectReturnsUnauthorizedWhenCapabilityMissing(): void
    {
        $postId = 42;
        $writeCalls = [];

        Functions\when('current_user_can')->justReturn(false);
        Functions\when('update_post_meta')->alias(function (int $id, string $key) use (&$writeCalls): bool {
            $writeCalls[] = $key;
            return true;
        });
        Functions\when('delete_post_meta')->alias(function (int $id, string $key) use (&$writeCalls): bool {
            $writeCalls[] = 'delete:' . $key;
            return true;
        });

        $result = $this->injector->inject($postId, []);

        $this->assertFalse($result->isSuccess());
        $this->assertSame($postId, $result->getPostId());
        $this->assertSame('Unauthorized', $result->getError());
        $this->assertEmpty($writeCalls, 'No WP write functions should be called when unauthorized');
    }
}
