<?php

namespace App\Services;

use App\Models\Protocol;
use Illuminate\Database\Eloquent\Builder;
use OverflowException;

/**
 * Calculates protocol numbers from the application-wide numbering settings.
 *
 * The original application supports two modes:
 *
 * - An active year creates an independent sequence for each protocol year.
 * - A blank active year continues one sequence across every year.
 *
 * Soft-deleted protocols remain part of the calculation because their
 * number/year pair is still present in the database and must not be reused.
 */
class ProtocolNumbering
{
    private const MAX_PROTOCOL_NUMBER = 4294967295;

    public function __construct(
        private readonly ApplicationSettings $settings
    ) {
    }

    /**
     * Return the year initially displayed on the creation form.
     */
    public function defaultYear(): int
    {
        return $this->settings->activeProtocolYear() ?? now()->year;
    }

    /**
     * Preview the next number without obtaining a database write lock.
     *
     * This value is informative only. Automatic numbering recalculates the
     * number inside the storage transaction immediately before insertion.
     */
    public function suggestedNumber(int $protocolYear): int
    {
        return $this->numberAfter(
            $this->highestNumber($protocolYear, false)
        );
    }

    /**
     * Calculate the next number while locking the current end of the sequence.
     *
     * ProtocolController calls this method only from an open transaction.
     */
    public function nextNumber(int $protocolYear): int
    {
        return $this->numberAfter(
            $this->highestNumber($protocolYear, true)
        );
    }

    /**
     * Whether protocol numbers are currently separated by year.
     */
    public function usesYearlySequence(): bool
    {
        return $this->settings->activeProtocolYear() !== null;
    }

    /**
     * Retrieve the greatest number in the applicable sequence.
     */
    private function highestNumber(
        int $protocolYear,
        bool $lockForUpdate
    ): ?int {
        /** @var Builder<Protocol> $query */
        $query = Protocol::withTrashed();

        if ($this->usesYearlySequence()) {
            $query->where('protocol_year', $protocolYear);
        }

        $query
            ->orderByDesc('protocol_number')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $protocol = $query->first(['protocol_number']);

        return $protocol?->protocol_number;
    }

    /**
     * Convert the current maximum into the next valid unsigned integer.
     */
    private function numberAfter(?int $highestNumber): int
    {
        if ($highestNumber === null) {
            return $this->settings->startingProtocolNumber();
        }

        if ($highestNumber >= self::MAX_PROTOCOL_NUMBER) {
            throw new OverflowException(
                'The protocol numbering sequence has been exhausted.'
            );
        }

        return $highestNumber + 1;
    }
}
