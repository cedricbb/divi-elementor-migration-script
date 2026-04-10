<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Injector;

use DiviToElementor\Injector\BatchMigrator;
use DiviToElementor\Injector\InjectionResult;
use DiviToElementor\Injector\Injector;
use PHPUnit\Framework\TestCase;

class BatchMigratorTest extends TestCase
{
    // AC-6 — migrate([10,11,12,99], limit=10) → BatchResult{processed:4, success:3, failed:1} sans exception (EC-5)
    public function testMigrateContinuesOnError(): void
    {
        $injectorMock = $this->createMock(Injector::class);
        $injectorMock->method('inject')
            ->willReturnCallback(function (int $postId, array $data): InjectionResult {
                if ($postId === 99) {
                    throw new \RuntimeException('Post not found');
                }
                return new InjectionResult(true, $postId, null);
            });

        $fetcher = static fn(int $id): array => [];
        $batchMigrator = new BatchMigrator($injectorMock, \Closure::fromCallable($fetcher));

        $result = $batchMigrator->migrate([10, 11, 12, 99], 10);

        $this->assertSame(4, $result->getProcessed());
        $this->assertSame(3, $result->getSuccess());
        $this->assertSame(1, $result->getFailed());
        $this->assertCount(4, $result->getItems());
    }

    // AC-7 — migrate([1,2,3,4,5], limit:2) → seuls posts 1 et 2 traités, processed=2 (EC-6)
    public function testMigrateRespectsLimit(): void
    {
        $callCount = 0;
        $injectorMock = $this->createMock(Injector::class);
        $injectorMock->method('inject')
            ->willReturnCallback(function (int $postId, array $data) use (&$callCount): InjectionResult {
                $callCount++;
                return new InjectionResult(true, $postId, null);
            });

        $fetcher = static fn(int $id): array => [];
        $batchMigrator = new BatchMigrator($injectorMock, \Closure::fromCallable($fetcher));

        $result = $batchMigrator->migrate([1, 2, 3, 4, 5], 2);

        $this->assertSame(2, $result->getProcessed());
        $this->assertSame(2, $callCount, 'Injector should be called exactly 2 times');
    }
}
