<?php

namespace App\Services\Shifts;

class ShiftValidationResult
{
    /** @param  list<array{code: string, level: string, message: string}>  $issues */
    public function __construct(private array $issues = []) {}

    public static function make(): self
    {
        return new self;
    }

    public function critical(string $code, string $message): self
    {
        $this->issues[] = ['code' => $code, 'level' => 'critical', 'message' => $message];

        return $this;
    }

    public function warning(string $code, string $message): self
    {
        $this->issues[] = ['code' => $code, 'level' => 'warning', 'message' => $message];

        return $this;
    }

    /** @return list<array{code: string, level: string, message: string}> */
    public function all(): array
    {
        return $this->issues;
    }

    /** @return list<array{code: string, level: string, message: string}> */
    public function criticals(): array
    {
        return array_values(array_filter($this->issues, fn ($i) => $i['level'] === 'critical'));
    }

    /** @return list<array{code: string, level: string, message: string}> */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, fn ($i) => $i['level'] === 'warning'));
    }

    public function hasCritical(): bool
    {
        return count($this->criticals()) > 0;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings()) > 0;
    }

    public function isClean(): bool
    {
        return $this->issues === [];
    }

    /** @return list<string> */
    public function criticalMessages(): array
    {
        return array_map(fn ($i) => $i['message'], $this->criticals());
    }

    /** @return list<string> */
    public function warningMessages(): array
    {
        return array_map(fn ($i) => $i['message'], $this->warnings());
    }
}
