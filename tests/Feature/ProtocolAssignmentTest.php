<?php

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->assigner = User::factory()->create([
        'role' => UserRole::Assigner,
    ]);

    $this->officer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->protocol = Protocol::create([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-18',
        'direction' => 'incoming',
        'subject' => 'Assignment foundation test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->assigner->id,
    ]);
});

test('a processing assignment stores enum dates and relationships', function () {
    $assignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-25 17:00:00',
    ]);

    $assignment->refresh();

    $this->assertSame(
        ProtocolAssignmentPurpose::Processing,
        $assignment->purpose
    );
    $this->assertSame(
        '2026-08-25 17:00:00',
        $assignment->due_at->format('Y-m-d H:i:s')
    );
    $this->assertNull($assignment->completed_at);
    $this->assertNull($assignment->superseded_at);
    $this->assertTrue($assignment->protocol->is($this->protocol));
    $this->assertTrue($assignment->assigner->is($this->assigner));
    $this->assertTrue($assignment->assignee->is($this->officer));
    $this->assertTrue(
        $this->protocol->assignments()->firstOrFail()->is($assignment)
    );
    $this->assertTrue(
        $this->assigner->assignmentsCreated()->firstOrFail()->is($assignment)
    );
    $this->assertTrue(
        $this->officer->assignmentsReceived()->firstOrFail()->is($assignment)
    );

    $this->assertDatabaseHas('protocol_assignments', [
        'id' => $assignment->id,
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing->value,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
        'completed_at' => null,
        'superseded_at' => null,
    ]);
});

test('a protocol supports multiple information recipients', function () {
    $firstViewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $secondViewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    foreach ([$firstViewer, $secondViewer] as $viewer) {
        ProtocolAssignment::create([
            'protocol_id' => $this->protocol->id,
            'purpose' => ProtocolAssignmentPurpose::Information,
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $viewer->id,
        ]);
    }

    $this->assertSame(2, $this->protocol->assignments()->count());
    $this->assertSame(2, ProtocolAssignment::information()->count());
    $this->assertSame(1, $firstViewer->assignmentsReceived()->count());
    $this->assertSame(1, $secondViewer->assignmentsReceived()->count());
});

test('assignment scopes separate pending information and completed work', function () {
    $pending = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-25 17:00:00',
    ]);

    $completed = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-20 17:00:00',
        'completed_at' => '2026-08-19 12:00:00',
    ]);

    $superseded = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-21 17:00:00',
        'superseded_at' => '2026-08-20 09:00:00',
    ]);

    $information = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
    ]);

    $this->assertEquals(
        [$pending->id],
        ProtocolAssignment::pending()->pluck('id')->all()
    );
    $this->assertEquals(
        [$information->id],
        ProtocolAssignment::information()->pluck('id')->all()
    );
    $this->assertEquals(
        [$completed->id],
        ProtocolAssignment::completed()->pluck('id')->all()
    );
    $this->assertEquals(
        [$superseded->id],
        ProtocolAssignment::superseded()->pluck('id')->all()
    );
    $this->assertSame(
        '2026-08-20 09:00:00',
        $superseded->superseded_at->format('Y-m-d H:i:s')
    );
});

test('permanently deleting a protocol removes its assignments', function () {
    $assignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
    ]);

    $this->protocol->forceDelete();

    $this->assertDatabaseMissing('protocol_assignments', [
        'id' => $assignment->id,
    ]);
});

test('deleting assignment users preserves the assignment history', function () {
    $assignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
    ]);

    $this->assigner->delete();
    $this->officer->delete();

    $assignment->refresh();

    $this->assertNull($assignment->assigned_by);
    $this->assertNull($assignment->assigned_to);
    $this->assertNull($assignment->assigner);
    $this->assertNull($assignment->assignee);
});