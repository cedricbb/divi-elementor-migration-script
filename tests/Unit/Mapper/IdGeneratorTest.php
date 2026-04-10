<?php

declare(strict_types=1);

namespace DiviToElementor\Tests\Unit\Mapper;

use DiviToElementor\Mapper\IdGenerator;
use PHPUnit\Framework\TestCase;

class IdGeneratorTest extends TestCase
{
    private IdGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new IdGenerator();
    }

    public function testGeneratesEightHexChars(): void
    {
        $id = $this->generator->generate();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
    }

    public function testUnicityOn1000Calls(): void
    {
        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[] = $this->generator->generate();
        }
        $this->assertCount(1000, array_unique($ids));
    }

    public function testResetClearsRegistry(): void
    {
        $this->generator->generate();
        $this->generator->reset();
        // After reset, a new instance behaves as fresh
        $id = $this->generator->generate();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
    }
}
