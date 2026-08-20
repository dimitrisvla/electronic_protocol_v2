<?php

namespace App\Actions\Protocols;

use App\Models\Protocol;
use App\Models\ProtocolRelation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Synchronize the active related protocols selected in a protocol form.
 */
class SyncRelatedProtocols
{
    /**
     * @param  array<int, array{protocol_number: mixed, protocol_year: mixed}>  $references
     */
    public function execute(
        Protocol $protocol,
        array $references,
        ?User $creator = null
    ): void {
        DB::transaction(function () use (
            $protocol,
            $references,
            $creator
        ): void {
            $desiredProtocolIds = collect($references)
                ->map(function (array $reference): int {
                    return (int) Protocol::query()
                        ->where(
                            'protocol_number',
                            (int) $reference['protocol_number']
                        )
                        ->where(
                            'protocol_year',
                            (int) $reference['protocol_year']
                        )
                        ->firstOrFail()
                        ->getKey();
                })
                ->unique()
                ->values();

            $currentRelations = ProtocolRelation::query()
                ->containing($protocol)
                ->with(['firstProtocol', 'secondProtocol'])
                ->get();

            foreach ($currentRelations as $relation) {
                $otherProtocol = $relation->otherProtocol($protocol);

                if ($desiredProtocolIds->contains($otherProtocol->getKey())) {
                    continue;
                }

                /*
                 * A relation whose other endpoint is temporarily in the
                 * recycle bin is not shown in the edit form. Preserve it so
                 * restoring that protocol also restores the navigation link.
                 */
                if ($otherProtocol->trashed()) {
                    continue;
                }

                $relation->delete();
            }

            foreach ($desiredProtocolIds as $relatedProtocolId) {
                ProtocolRelation::connect(
                    $protocol,
                    Protocol::query()->findOrFail($relatedProtocolId),
                    $creator
                );
            }
        });
    }
}
