<?php

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this->assigner = User::factory()->create([
        'role' => UserRole::Assigner,
    ]);

    $this->officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->viewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->informationRecipient = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->protocol = Protocol::create([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-18',
        'direction' => 'incoming',
        'subject' => 'Assignment endpoint test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->officer->id,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a guest cannot update protocol assignments', function () {
    $response = $this->put(
        route('protocols.assignments.update', $this->protocol),
        validAssignmentPayload($this->officer)
    );

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('protocol_assignments', 0);
});

test('administrator and assigner can update all protocol assignments', function (
    string $actorProperty
) {
    $actor = $this->{$actorProperty};

    $response = $this
        ->actingAs($actor)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            validAssignmentPayload(
                $this->officer,
                [$this->informationRecipient, $this->administrator]
            )
        );

    $response
        ->assertRedirect(route('protocols.show', $this->protocol))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('protocol_assignments', [
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing->value,
        'assigned_by' => $actor->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-25 17:00:00',
        'completed_at' => null,
        'superseded_at' => null,
    ]);

    foreach ([$this->informationRecipient, $this->administrator] as $recipient) {
        $this->assertDatabaseHas('protocol_assignments', [
            'protocol_id' => $this->protocol->id,
            'purpose' => ProtocolAssignmentPurpose::Information->value,
            'assigned_by' => $actor->id,
            'assigned_to' => $recipient->id,
        ]);
    }

    $this->assertDatabaseCount('protocol_assignments', 3);
})->with([
    'administrator' => 'administrator',
    'assigner' => 'assigner',
]);

test('updating assignments supersedes the previous processing officer', function () {
    $previous = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->administrator->id,
        'assigned_to' => $this->officer->id,
    ]);

    $this
        ->actingAs($this->assigner)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            validAssignmentPayload($this->otherOfficer)
        )
        ->assertRedirect(route('protocols.show', $this->protocol));

    $this->assertNotNull($previous->fresh()->superseded_at);
    $this->assertDatabaseHas('protocol_assignments', [
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing->value,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->otherOfficer->id,
        'completed_at' => null,
        'superseded_at' => null,
    ]);
});

test('protocol officers and viewers cannot update assignments', function (
    string $actorProperty
) {
    $this
        ->actingAs($this->{$actorProperty})
        ->put(
            route('protocols.assignments.update', $this->protocol),
            validAssignmentPayload($this->officer)
        )
        ->assertForbidden();

    $this->assertDatabaseCount('protocol_assignments', 0);
})->with([
    'protocol officer' => 'officer',
    'viewer' => 'viewer',
]);

test('processing work must be assigned to a protocol officer', function () {
    $this
        ->actingAs($this->assigner)
        ->from(route('protocols.show', $this->protocol))
        ->put(
            route('protocols.assignments.update', $this->protocol),
            validAssignmentPayload($this->viewer)
        )
        ->assertRedirect(route('protocols.show', $this->protocol))
        ->assertSessionHasErrors('processing_assignee_id');

    $this->assertDatabaseCount('protocol_assignments', 0);
});

test('assignment validation rejects an invalid due date', function () {
    $this
        ->actingAs($this->assigner)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            array_replace(
                validAssignmentPayload($this->officer),
                ['due_at' => 'not-a-date']
            )
        )
        ->assertSessionHasErrors('due_at');

    $this->assertDatabaseCount('protocol_assignments', 0);
});

test('the same information recipient cannot be selected twice', function () {
    $payload = validAssignmentPayload($this->officer);
    $payload['information_recipient_ids'] = [
        $this->viewer->id,
        $this->viewer->id,
    ];

    $this
        ->actingAs($this->assigner)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            $payload
        )
        ->assertSessionHasErrors('information_recipient_ids.1');

    $this->assertDatabaseCount('protocol_assignments', 0);
});

test('the processing officer cannot also be an information recipient', function () {
    $payload = validAssignmentPayload($this->officer, [$this->officer]);

    $this
        ->actingAs($this->assigner)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            $payload
        )
        ->assertSessionHasErrors('information_recipient_ids.0');

    $this->assertDatabaseCount('protocol_assignments', 0);
});

test('invalid input leaves existing assignments unchanged', function () {
    $processing = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->administrator->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-22 12:00:00',
    ]);

    $information = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->administrator->id,
        'assigned_to' => $this->informationRecipient->id,
    ]);

    $this
        ->actingAs($this->assigner)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            [
                'processing_assignee_id' => $this->otherOfficer->id,
                'due_at' => 'invalid',
                'information_recipient_ids' => [],
            ]
        )
        ->assertSessionHasErrors('due_at');

    $this->assertSame(
        $this->officer->id,
        $processing->fresh()->assigned_to
    );
    $this->assertNull($processing->fresh()->superseded_at);
    $this->assertModelExists($information);
    $this->assertDatabaseCount('protocol_assignments', 2);
});

test('a deleted protocol cannot be updated through the assignment endpoint', function () {
    $this->protocol->delete();

    $this
        ->actingAs($this->assigner)
        ->put(
            route('protocols.assignments.update', $this->protocol),
            validAssignmentPayload($this->officer)
        )
        ->assertNotFound();
});

test('an assigned officer and an administrator can complete processing work', function (
    string $actorProperty
) {
    Carbon::setTestNow('2026-08-20 14:30:00');

    $assignment = createProcessingAssignment(
        $this->protocol,
        $this->assigner,
        $this->officer
    );

    $this
        ->actingAs($this->{$actorProperty})
        ->patch(route('protocols.assignments.complete', [
            $this->protocol,
            $assignment,
        ]))
        ->assertRedirect(route('protocols.show', $this->protocol))
        ->assertSessionHas('success');

    $this->assertSame(
        '2026-08-20 14:30:00',
        $assignment->fresh()->completed_at->format('Y-m-d H:i:s')
    );
})->with([
    'assigned protocol officer' => 'officer',
    'administrator' => 'administrator',
]);

test('unauthorized users cannot complete processing work', function (
    string $actorProperty
) {
    $assignment = createProcessingAssignment(
        $this->protocol,
        $this->assigner,
        $this->officer
    );

    $this
        ->actingAs($this->{$actorProperty})
        ->patch(route('protocols.assignments.complete', [
            $this->protocol,
            $assignment,
        ]))
        ->assertForbidden();

    $this->assertNull($assignment->fresh()->completed_at);
})->with([
    'assigner' => 'assigner',
    'different protocol officer' => 'otherOfficer',
    'viewer' => 'viewer',
]);

test('an information assignment cannot be completed through the endpoint', function () {
    $assignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
    ]);

    $this
        ->actingAs($this->administrator)
        ->patch(route('protocols.assignments.complete', [
            $this->protocol,
            $assignment,
        ]))
        ->assertForbidden();

    $this->assertNull($assignment->fresh()->completed_at);
});

test('an assignment cannot be completed through the wrong protocol', function () {
    $assignment = createProcessingAssignment(
        $this->protocol,
        $this->assigner,
        $this->officer
    );

    $otherProtocol = Protocol::create([
        'protocol_number' => 2,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-18',
        'direction' => 'outgoing',
        'subject' => 'Different protocol',
        'sender' => null,
        'recipient' => 'Example recipient',
        'notes' => null,
        'created_by' => $this->administrator->id,
    ]);

    $this
        ->actingAs($this->administrator)
        ->patch(route('protocols.assignments.complete', [
            $otherProtocol,
            $assignment,
        ]))
        ->assertNotFound();

    $this->assertNull($assignment->fresh()->completed_at);
});

test('a guest cannot complete a processing assignment', function () {
    $assignment = createProcessingAssignment(
        $this->protocol,
        $this->assigner,
        $this->officer
    );

    $this
        ->patch(route('protocols.assignments.complete', [
            $this->protocol,
            $assignment,
        ]))
        ->assertRedirect(route('login'));

    $this->assertNull($assignment->fresh()->completed_at);
});

/**
 * Build a valid request body for the assignment update endpoint.
 *
 * @param  array<int, User>  $informationRecipients
 * @return array<string, mixed>
 */
function validAssignmentPayload(
    User $processingOfficer,
    array $informationRecipients = []
): array {
    return [
        'processing_assignee_id' => $processingOfficer->id,
        'due_at' => '2026-08-25 17:00:00',
        'information_recipient_ids' => array_map(
            fn (User $recipient): int => $recipient->id,
            $informationRecipients
        ),
    ];
}

function createProcessingAssignment(
    Protocol $protocol,
    User $assigner,
    User $officer
): ProtocolAssignment {
    return ProtocolAssignment::create([
        'protocol_id' => $protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $assigner->id,
        'assigned_to' => $officer->id,
    ]);
}