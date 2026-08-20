<?php

namespace App\Actions\Protocols;

use App\Enums\ProtocolAssignmentPurpose;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncProtocolInformationRecipients
{
    /**
     * Synchronize the information-only recipients for one protocol.
     *
     * Existing recipients keep their assignment rows, new recipients receive
     * new rows, and recipients no longer selected are removed. The returned
     * models preserve Laravel's wasRecentlyCreated flag so notifications can
     * later be sent only to newly added recipients.
     *
     * @param  iterable<int, User>  $recipients
     * @return Collection<int, ProtocolAssignment>
     */
    public function execute(
        Protocol $protocol,
        User $actor,
        iterable $recipients
    ): Collection {
        if (! $protocol->exists) {
            throw new InvalidArgumentException(
                'The protocol must exist before recipients can be assigned.'
            );
        }

        if (! $actor->exists) {
            throw new InvalidArgumentException(
                'The assigning user must exist.'
            );
        }

        $recipientModels = collect($recipients)
            ->map(function (mixed $recipient): User {
                if (! $recipient instanceof User || ! $recipient->exists) {
                    throw new InvalidArgumentException(
                        'Every information recipient must be an existing user.'
                    );
                }

                return $recipient;
            })
            ->unique(fn (User $recipient): string =>
                (string) $recipient->getKey())
            ->values();

        return DB::transaction(function () use (
            $protocol,
            $actor,
            $recipientModels
        ): Collection {
            // Every assignment-changing action locks the protocol parent row
            // first, serializing processing and information recipient changes.
            $lockedProtocol = Protocol::query()
                ->whereKey($protocol->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $desiredRecipientKeys = $recipientModels->mapWithKeys(
                fn (User $recipient): array => [
                    (string) $recipient->getKey() => true,
                ]
            );

            $existingAssignments = ProtocolAssignment::query()
                ->where('protocol_id', $lockedProtocol->getKey())
                ->information()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var Collection<string, ProtocolAssignment> $currentByRecipient */
            $currentByRecipient = collect();

            foreach ($existingAssignments as $existingAssignment) {
                $recipientKey = $existingAssignment->assigned_to === null
                    ? null
                    : (string) $existingAssignment->assigned_to;

                // Delete recipients that were removed, orphaned rows, and any
                // duplicate legacy row after preserving its oldest occurrence.
                if (
                    $recipientKey === null
                    || ! $desiredRecipientKeys->has($recipientKey)
                    || $currentByRecipient->has($recipientKey)
                ) {
                    $existingAssignment->delete();

                    continue;
                }

                $currentByRecipient->put(
                    $recipientKey,
                    $existingAssignment
                );
            }

            foreach ($recipientModels as $recipient) {
                $recipientKey = (string) $recipient->getKey();

                if ($currentByRecipient->has($recipientKey)) {
                    continue;
                }

                $assignment = $lockedProtocol->assignments()->create([
                    'purpose' => ProtocolAssignmentPurpose::Information,
                    'assigned_by' => $actor->getKey(),
                    'assigned_to' => $recipient->getKey(),
                ]);

                $currentByRecipient->put($recipientKey, $assignment);
            }

            // Preserve the order supplied by the caller and retain each model's
            // wasRecentlyCreated value for later notification dispatch.
            return $recipientModels
                ->map(fn (User $recipient): ProtocolAssignment =>
                    $currentByRecipient->get(
                        (string) $recipient->getKey()
                    ))
                ->values();
        }, 3);
    }
}
