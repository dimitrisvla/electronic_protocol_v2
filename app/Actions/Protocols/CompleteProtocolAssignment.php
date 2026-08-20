<?php

namespace App\Actions\Protocols;

use App\Enums\ProtocolAssignmentPurpose;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompleteProtocolAssignment
{
    /**
     * Mark one active processing assignment as completed.
     *
     * The protocol row is locked before the assignment row. Reassignment uses
     * the same lock order, so completion and reassignment cannot race or create
     * a database deadlock by acquiring the two rows in opposite orders.
     */
    public function execute(
        ProtocolAssignment $assignment
    ): ProtocolAssignment {
        if (! $assignment->exists) {
            throw new InvalidArgumentException(
                'The assignment must exist before it can be completed.'
            );
        }

        return DB::transaction(function () use (
            $assignment
        ): ProtocolAssignment {
            // The default Protocol query excludes soft-deleted protocols.
            // Completion therefore stops if the parent protocol was deleted.
            $lockedProtocol = Protocol::query()
                ->whereKey($assignment->protocol_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAssignment = ProtocolAssignment::query()
                ->whereKey($assignment->getKey())
                ->where('protocol_id', $lockedProtocol->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedAssignment->purpose
                    !== ProtocolAssignmentPurpose::Processing
            ) {
                throw new DomainException(
                    'Only processing assignments can be completed.'
                );
            }

            if ($lockedAssignment->completed_at !== null) {
                throw new DomainException(
                    'The assignment has already been completed.'
                );
            }

            if ($lockedAssignment->superseded_at !== null) {
                throw new DomainException(
                    'A superseded assignment cannot be completed.'
                );
            }

            if ($lockedAssignment->assigned_to === null) {
                throw new DomainException(
                    'An assignment without an assignee cannot be completed.'
                );
            }

            $lockedAssignment->completed_at = now();
            $lockedAssignment->save();

            return $lockedAssignment->refresh();
        }, 3);
    }
}
