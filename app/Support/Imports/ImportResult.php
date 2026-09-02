<?php

namespace App\Support\Imports;

class ImportResult
{
    /** @param  list<array{row: int, message: string}>  $errors */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {
    }

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
        $this->skipped++;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function totalProcessed(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    /** @return list<string> */
    public function flashLines(): array
    {
        $lines = [];

        if ($this->created > 0) {
            $lines[] = "{$this->created} record(s) imported.";
        }

        if ($this->updated > 0) {
            $lines[] = "{$this->updated} record(s) updated.";
        }

        if ($this->skipped > 0) {
            $lines[] = "{$this->skipped} row(s) skipped.";
        }

        foreach (array_slice($this->errors, 0, 5) as $error) {
            $lines[] = "Row {$error['row']}: {$error['message']}";
        }

        if (count($this->errors) > 5) {
            $lines[] = '…and '.(count($this->errors) - 5).' more error(s).';
        }

        return $lines;
    }
}
