<?php

use App\Actions\Protocols\AssignProtocolForProcessing;
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

    $this->firstOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->secondOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->protocol = Protocol::create([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-18',
        'direction' => 'incoming',
        'subject' => 'Processing assignment action test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->assigner->id,
    ]);

    $this->action = app(AssignProtocolForProcessing::class);
});

test('it creates the first processing assignment', function () {
    $dueAt = Carbon::parse('2026-08-25 17:00:00');

    $assignment = $this->action->execute(
        $this->protocol,
        $this->assigner,
        $this->firstOfficer,
        $dueAt
    );

    $this->assertSame(
        ProtocolAssignmentPurpose::Processing,
        $assignment->purpose
    );
    $this->assertSame($this->assigner->id, $assignment->assigned_by);
    $this->assertSame($this->firstOfficer->id, $assignment->assigned_to);
    $this->assertSame(
        '2026-08-25 17:00:00',
        $assignment->due_at->format('Y-m-d H:i:s')
    );
    $this->assertNull($assignment->completed_at);
    $this->assertNull($assignment->superseded_at);
    $this->assertSame(1, ProtocolAssignment::pending()->count());
});

test('reassignment supersedes the previous active assignment', function () {
    $previous = $this->action->execute(
        $this->protocol,
        $this->assigner,
        $this->firstOfficer,
        Carbon::parse('2026-08-25 17:00:00')
    );

    $current = $this->action->execute(
        $this->protocol,
        $this->administrator,
        $this->secondOfficer,
        Carbon::parse('2026-08-27 17:00:00')
    );

    $previous->refresh();

    $this->assertNotNull($previous->superseded_at);
    $this->assertNull($previous->completed_at);
    $this->assertSame($this->secondOfficer->id, $current->assigned_to);
    $this->assertSame($this->administrator->id, $current->assigned_by);
    $this->assertNull($current->superseded_at);
    $this->assertSame(1, ProtocolAssignment::pending()->count());
    $this->assertSame(1, ProtocolAssignment::superseded()->count());
    $this->assertSame(0, ProtocolAssignment::completed()->count());
});

test('selecting the same officer updates the due date without a duplicate', function () {
    $original = $this->action->execute(
        $this->protocol,
        $this->assigner,
        $this->firstOfficer,
        Carbon::parse('2026-08-25 17:00:00')
    );

    $updated = $this->action->execute(
        $this->protocol,
        $this->administrator,
        $this->firstOfficer,
        Carbon::parse('2026-08-30 12:30:00')
    );

    $this->assertSame($original->id, $updated->id);
    $this->assertSame(1, ProtocolAssignment::count());
    $this->assertSame(1, ProtocolAssignment::pending()->count());
    $this->assertSame(0, ProtocolAssignment::superseded()->count());
    $this->assertSame(
        '2026-08-30 12:30:00',
        $updated->due_at->format('Y-m-d H:i:s')
    );
});

test('a completed assignment does not block a new active assignment', function () {
    $completed = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->firstOfficer->id,
        'completed_at' => '2026-08-20 09:00:00',
    ]);

    $current = $this->action->execute(
        $this->protocol,
        $this->assigner,
        $this->secondOfficer,
        Carbon::parse('2026-08-28 17:00:00')
    );

    $completed->refresh();

    $this->assertNull($completed->superseded_at);
    $this->assertNull($current->completed_at);
    $this->assertSame(1, ProtocolAssignment::completed()->count());
    $this->assertSame(1, ProtocolAssignment::pending()->count());
});

test('it repairs legacy duplicate pending assignments', function () {
    foreach ([$this->firstOfficer, $this->secondOfficer] as $officer) {
        ProtocolAssignment::create([
            'protocol_id' => $this->protocol->id,
            'purpose' => ProtocolAssignmentPurpose::Processing,
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $officer->id,
        ]);
    }

    $replacementOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $current = $this->action->execute(
        $this->protocol,
        $this->administrator,
        $replacementOfficer
    );

    $this->assertSame($replacementOfficer->id, $current->assigned_to);
    $this->assertSame(1, ProtocolAssignment::pending()->count());
    $this->assertSame(2, ProtocolAssignment::superseded()->count());
});

test('processing work cannot be assigned to a non officer', function () {
    $viewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage(
        'Processing work may be assigned only to a Protocol Officer.'
    );

    try {
        $this->action->execute(
            $this->protocol,
            $this->assigner,
            $viewer
        );
    } finally {
        $this->assertSame(0, ProtocolAssignment::count());
    }
});