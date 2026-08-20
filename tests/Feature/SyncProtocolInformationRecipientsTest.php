<?php

use App\Actions\Protocols\SyncProtocolInformationRecipients;
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

    $this->firstViewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->secondViewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->protocolOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->protocol = Protocol::create([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-18',
        'direction' => 'incoming',
        'subject' => 'Information recipient synchronization test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->assigner->id,
    ]);

    $this->action = app(SyncProtocolInformationRecipients::class);
});

test('it creates multiple information recipient assignments', function () {
    $assignments = $this->action->execute(
        $this->protocol,
        $this->assigner,
        [$this->firstViewer, $this->secondViewer]
    );

    $this->assertCount(2, $assignments);
    $this->assertSame(2, ProtocolAssignment::information()->count());

    foreach ($assignments as $assignment) {
        $this->assertSame(
            ProtocolAssignmentPurpose::Information,
            $assignment->purpose
        );
        $this->assertSame($this->assigner->id, $assignment->assigned_by);
        $this->assertTrue($assignment->wasRecentlyCreated);
    }

    $this->assertEquals(
        [$this->firstViewer->id, $this->secondViewer->id],
        $assignments->pluck('assigned_to')->all()
    );
});

test('it preserves unchanged recipients and synchronizes added and removed users', function () {
    $initial = $this->action->execute(
        $this->protocol,
        $this->assigner,
        [$this->firstViewer, $this->secondViewer]
    );

    $preservedId = $initial->first()->id;
    $removedId = $initial->last()->id;

    $current = $this->action->execute(
        $this->protocol,
        $this->assigner,
        [$this->firstViewer, $this->protocolOfficer]
    );

    $this->assertCount(2, $current);
    $this->assertSame($preservedId, $current->first()->id);
    $this->assertFalse($current->first()->wasRecentlyCreated);
    $this->assertTrue($current->last()->wasRecentlyCreated);
    $this->assertSame($this->protocolOfficer->id, $current->last()->assigned_to);

    $this->assertDatabaseMissing('protocol_assignments', [
        'id' => $removedId,
    ]);
    $this->assertSame(2, ProtocolAssignment::information()->count());
});

test('duplicate recipient selections create only one assignment', function () {
    $assignments = $this->action->execute(
        $this->protocol,
        $this->assigner,
        [$this->firstViewer, $this->firstViewer, $this->firstViewer]
    );

    $this->assertCount(1, $assignments);
    $this->assertSame(1, ProtocolAssignment::information()->count());
    $this->assertSame($this->firstViewer->id, $assignments->first()->assigned_to);
});

test('an empty recipient list removes every information assignment', function () {
    $this->action->execute(
        $this->protocol,
        $this->assigner,
        [$this->firstViewer, $this->secondViewer]
    );

    $assignments = $this->action->execute(
        $this->protocol,
        $this->assigner,
        []
    );

    $this->assertCount(0, $assignments);
    $this->assertSame(0, ProtocolAssignment::information()->count());
});

test('it repairs duplicate and orphaned legacy information assignments', function () {
    $oldest = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->firstViewer->id,
    ]);

    $duplicate = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->firstViewer->id,
    ]);

    $orphaned = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => null,
    ]);

    $assignments = $this->action->execute(
        $this->protocol,
        $this->assigner,
        [$this->firstViewer]
    );

    $this->assertCount(1, $assignments);
    $this->assertSame($oldest->id, $assignments->first()->id);
    $this->assertFalse($assignments->first()->wasRecentlyCreated);
    $this->assertDatabaseMissing('protocol_assignments', [
        'id' => $duplicate->id,
    ]);
    $this->assertDatabaseMissing('protocol_assignments', [
        'id' => $orphaned->id,
    ]);
});

test('any existing user role may receive an information assignment', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $assignments = $this->action->execute(
        $this->protocol,
        $this->assigner,
        [
            $administrator,
            $this->assigner,
            $this->protocolOfficer,
            $this->firstViewer,
        ]
    );

    $this->assertCount(4, $assignments);
    $this->assertEquals(
        [
            $administrator->id,
            $this->assigner->id,
            $this->protocolOfficer->id,
            $this->firstViewer->id,
        ],
        $assignments->pluck('assigned_to')->all()
    );
});

test('an unsaved user cannot be used as an information recipient', function () {
    $unsavedUser = new User([
        'name' => 'Unsaved User',
        'email' => 'unsaved@example.com',
        'password' => 'password123',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(
        'Every information recipient must be an existing user.'
    );

    try {
        $this->action->execute(
            $this->protocol,
            $this->assigner,
            [$unsavedUser]
        );
    } finally {
        $this->assertSame(0, ProtocolAssignment::count());
    }
});
