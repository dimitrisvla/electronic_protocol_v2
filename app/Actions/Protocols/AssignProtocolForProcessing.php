<?php

namespace App\Actions\Protocols;

use App\Enums\ProtocolAssignmentPurpose;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignProtocolForProcessing
{
    /**
     * Assign or reassign a protocol to one Protocol Officer.
     *
     * Locking the protocol row serializes every processing-assignment change
     * for that protocol. This guarantees that callers using this action cannot
     * create two active processing assignments through concurrent requests.
     */
    public function execute(
        Protocol $protocol,
        User $actor,
        User $assignee,
        ?CarbonInterface $dueAt = null
    ): ProtocolAssignment {
        if (! $protocol->exists) {
            throw new InvalidArgumentException(
                'The protocol must exist before it can be assigned.'
            );
        }

        if (! $actor->exists) {
            throw new InvalidArgumentException(
                'The assigning user must exist.'
            );
        }

        if (! $assignee->exists || ! $assignee->isProtocolOfficer()) {
            throw new InvalidArgumentException(
                'Processing work may be assigned only to a Protocol Officer.'
            );
        }

        return DB::transaction(function () use (
            $protocol,
            $actor,
            $assignee,
            $dueAt
        ): ProtocolAssignment {
            // Every caller locks the same parent row first. Competing requests
            // for this protocol therefore execute this section one at a time.
            $lockedProtocol = Protocol::query()
                ->whereKey($protocol->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $pendingAssignments = ProtocolAssignment::query()
                ->where('protocol_id', $lockedProtocol->getKey())
                ->pending()
                ->lockForUpdate()
                ->get();

            $currentAssignment = $pendingAssignments->first();

            // Selecting the same officer updates the deadline in place. It
            // must not create a duplicate assignment or supersede the current
            // assignment, which also prevents duplicate recipient notices.
            if (
                $pendingAssignments->count() === 1
                && $currentAssignment !== null
                && (string) $currentAssignment->assigned_to
                    === (string) $assignee->getKey()
            ) {
                $currentAssignment->due_at = $dueAt;
                $currentAssignment->save();

                return $currentAssignment->refresh();
            }

            $supersededAt = now();

            // Normally this collection contains zero or one row. Closing all
            // rows also repairs any legacy duplicate active assignments before
            // creating the single new assignment.
            foreach ($pendingAssignments as $pendingAssignment) {
                $pendingAssignment->superseded_at = $supersededAt;
                $pendingAssignment->save();
            }

            return $lockedProtocol->assignments()->create([
                'purpose' => ProtocolAssignmentPurpose::Processing,
                'assigned_by' => $actor->getKey(),
                'assigned_to' => $assignee->getKey(),
                'due_at' => $dueAt,
            ]);
        }, 3);
    }
}
