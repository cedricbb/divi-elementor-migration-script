<?php

declare(strict_types=1);

namespace DiviToElementor\Injector;

readonly class RollbackResult
{
    public function __construct(
        private bool $success,
        private int $postId,
        private ?string $error,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
