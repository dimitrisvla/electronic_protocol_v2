<?php

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->administrator = User::factory()->create([
        'name' => 'Administrator User',
        'role' => UserRole::Administrator,
    ]);

    $this->assigner = User::factory()->create([
        'name' => 'Assignment Manager',
        'role' => UserRole::Assigner,
    ]);

    $this->assignedOfficer = User::factory()->create([
        'name' => 'Assigned Officer',
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->otherOfficer = User::factory()->create([
        'name' => 'Unrelated Officer',
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->informationViewer = User::factory()->create([
        'name' => 'Information Viewer',
        'role' => UserRole::Viewer,
    ]);

    $this->unrelatedViewer = User::factory()->create([
        'name' => 'Unrelated Viewer',
        'role' => UserRole::Viewer,
    ]);

    $this->protocol = Protocol::create([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-19',
        'direction' => 'incoming',
        'subject' => 'Assignment interface test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->otherOfficer->id,
    ]);
});

test('administrator and assigner see the assignment management form', function (
    string $actorProperty
) {
    $this
        ->actingAs($this->{$actorProperty})
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertSee(__('protocols.assignments.manage'))
        ->assertSee(__('protocols.assignments.processing_officer'))
        ->assertSee(__('protocols.assignments.information_recipients'))
        ->assertSee(__('protocols.assignments.save'))
        ->assertSee('Assigned Officer')
        ->assertSee('Information Viewer');
})->with([
    'administrator' => 'administrator',
    'assigner' => 'assigner',
]);

test('protocol officers and viewers do not see assignment management controls', function (
    string $actorProperty
) {
    $this
        ->actingAs($this->{$actorProperty})
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertDontSee(__('protocols.assignments.manage'))
        ->assertDontSee(__('protocols.assignments.save'));
})->with([
    'protocol officer' => 'assignedOfficer',
    'viewer' => 'informationViewer',
]);

test('the assigned officer sees their pending work and completion action', function () {
    createInterfaceAssignment(
        $this->protocol,
        $this->assigner,
        $this->assignedOfficer,
        ProtocolAssignmentPurpose::Processing
    );

    $this
        ->actingAs($this->assignedOfficer)
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertSee(__('protocols.assignments.current_processing'))
        ->assertSee('Assigned Officer')
        ->assertSee(__('protocols.statuses.pending'))
        ->assertSee(__('protocols.assignments.complete'))
        ->assertDontSee(__('protocols.assignments.manage'));
});

test('an unrelated officer cannot see another officers assignment', function () {
    createInterfaceAssignment(
        $this->protocol,
        $this->assigner,
        $this->assignedOfficer,
        ProtocolAssignmentPurpose::Processing
    );

    $this
        ->actingAs($this->otherOfficer)
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertDontSee('Assigned Officer')
        ->assertDontSee(__('protocols.assignments.complete'))
        ->assertSee(__('protocols.assignments.no_active_processing'));
});

test('an information recipient sees only their information assignment', function () {
    createInterfaceAssignment(
        $this->protocol,
        $this->assigner,
        $this->assignedOfficer,
        ProtocolAssignmentPurpose::Processing
    );

    createInterfaceAssignment(
        $this->protocol,
        $this->assigner,
        $this->informationViewer,
        ProtocolAssignmentPurpose::Information
    );

    $this
        ->actingAs($this->informationViewer)
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertSee('Information Viewer')
        ->assertDontSee('Assigned Officer')
        ->assertDontSee(__('protocols.assignments.complete'))
        ->assertDontSee(__('protocols.assignments.manage'));
});

test('operational roles see processing and information assignment history', function () {
    ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->administrator->id,
        'assigned_to' => $this->otherOfficer->id,
        'superseded_at' => '2026-08-18 10:00:00',
    ]);

    ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->assignedOfficer->id,
        'completed_at' => '2026-08-19 11:00:00',
    ]);

    createInterfaceAssignment(
        $this->protocol,
        $this->assigner,
        $this->informationViewer,
        ProtocolAssignmentPurpose::Information
    );

    $this
        ->actingAs($this->assigner)
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertSee(__('protocols.assignments.history'))
        ->assertSee(__('protocols.statuses.superseded'))
        ->assertSee(__('protocols.statuses.completed'))
        ->assertSee('Unrelated Officer')
        ->assertSee('Assigned Officer')
        ->assertSee('Information Viewer');
});

test('a completed assignment no longer displays a completion action', function () {
    ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->assignedOfficer->id,
        'completed_at' => '2026-08-19 12:00:00',
    ]);

    $this
        ->actingAs($this->assignedOfficer)
        ->get(route('protocols.show', $this->protocol))
        ->assertOk()
        ->assertSee(__('protocols.assignments.history'))
        ->assertSee(__('protocols.statuses.completed'))
        ->assertDontSee(__('protocols.assignments.complete'));
});

function createInterfaceAssignment(
    Protocol $protocol,
    User $assigner,
    User $assignee,
    ProtocolAssignmentPurpose $purpose
): ProtocolAssignment {
    return ProtocolAssignment::create([
        'protocol_id' => $protocol->id,
        'purpose' => $purpose,
        'assigned_by' => $assigner->id,
        'assigned_to' => $assignee->id,
        'due_at' => $purpose === ProtocolAssignmentPurpose::Processing
            ? '2026-08-25 17:00:00'
            : null,
    ]);
}
