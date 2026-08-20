<?php

namespace App\Http\Controllers;

use App\Actions\Protocols\AssignProtocolForProcessing;
use App\Actions\Protocols\CompleteProtocolAssignment;
use App\Actions\Protocols\SyncProtocolInformationRecipients;
use App\Http\Requests\UpdateProtocolAssignmentsRequest;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProtocolAssignmentController extends Controller
{
    /**
     * Synchronize the processing and information assignments for a protocol.
     */
    public function update(
        UpdateProtocolAssignmentsRequest $request,
        Protocol $protocol,
        AssignProtocolForProcessing $assignForProcessing,
        SyncProtocolInformationRecipients $syncInformationRecipients
    ): RedirectResponse {
        $validated = $request->validated();

        $processingAssignee = User::query()->findOrFail(
            $validated['processing_assignee_id']
        );

        $informationRecipientIds = collect(
            $validated['information_recipient_ids'] ?? []
        )->map(fn (mixed $id): int => (int) $id);

        $informationRecipientsById = User::query()
            ->whereKey($informationRecipientIds->all())
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->getKey());

        $informationRecipients = $informationRecipientIds
            ->map(fn (int $id): User => $informationRecipientsById->get(
                (string) $id
            ));

        $dueAt = filled($validated['due_at'] ?? null)
            ? CarbonImmutable::parse($validated['due_at'])
            : null;

        DB::transaction(function () use (
            $request,
            $protocol,
            $processingAssignee,
            $informationRecipients,
            $dueAt,
            $assignForProcessing,
            $syncInformationRecipients
        ): void {
            $actor = $request->user();

            $assignForProcessing->execute(
                $protocol,
                $actor,
                $processingAssignee,
                $dueAt
            );

            $syncInformationRecipients->execute(
                $protocol,
                $actor,
                $informationRecipients
            );
        }, 3);

        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', __('flash.assignments.updated'));
    }

    /**
     * Complete one active processing assignment belonging to the protocol.
     */
    public function complete(
        Protocol $protocol,
        ProtocolAssignment $protocolAssignment,
        CompleteProtocolAssignment $completeAssignment
    ): RedirectResponse {
        abort_unless(
            (int) $protocolAssignment->protocol_id === (int) $protocol->id,
            404
        );

        Gate::authorize('complete', $protocolAssignment);

        try {
            $completeAssignment->execute($protocolAssignment);
        } catch (DomainException) {
            return redirect()
                ->route('protocols.show', $protocol)
                ->withErrors([
                    'assignment' => __('flash.assignments.completion_failed'),
                ]);
        }

        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', __('flash.assignments.completed'));
    }
}
