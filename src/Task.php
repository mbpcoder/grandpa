<?php

declare(strict_types=1);

namespace Grandpa;

class Task implements ITask
{
    public function __construct(
        private readonly string $name,
        private readonly \Closure $callback,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function run(): void
    {
        ($this->callback)();
    }
}
