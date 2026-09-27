<?php

namespace Tests\Unit\Support;

use App\Support\Errors\DatabaseFailureMessage;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class DatabaseFailureMessageTest extends TestCase
{
    public function test_duplicate_employee_id_is_a_business_message(): void
    {
        $classified = DatabaseFailureMessage::classify($this->queryException(
            '23000',
            1062,
            "Duplicate entry 'PSG001' for key 'guards.guards_employment_id_unique'",
        ));

        $this->assertSame('conflict', $classified['kind']);
        $this->assertSame('Employee ID PSG001 already exists.', $classified['message']);
        $this->assertStringNotContainsString('SQLSTATE', $classified['message']);
    }

    public function test_overlapping_shift_constraint_is_specific(): void
    {
        $classified = DatabaseFailureMessage::classify($this->queryException(
            '23000',
            1062,
            "Duplicate entry '1|2026-10-01|day' for key 'shifts.shifts_same_shift_slot_unique'",
        ));

        $this->assertSame('This guard is already assigned to another overlapping shift.', $classified['message']);
    }

    public function test_connection_loss_is_unavailable_not_a_conflict(): void
    {
        $classified = DatabaseFailureMessage::classify($this->queryException(
            'HY000',
            2002,
            'SQLSTATE[HY000] [2002] Connection refused',
        ));

        $this->assertSame('unavailable', $classified['kind']);
        $this->assertSame(503, $classified['status']);
        $this->assertFalse(DatabaseFailureMessage::isExpectedConflict($this->queryException(
            'HY000',
            2002,
            'SQLSTATE[HY000] [2002] Connection refused',
        )));
    }

    private function queryException(string $sqlState, int $driverCode, string $detail): QueryException
    {
        $previous = new PDOException($detail);
        $previous->errorInfo = [$sqlState, $driverCode, $detail];

        return new QueryException('mysql', 'insert into guards (employment_id) values (?)', ['PSG001'], $previous);
    }
}
