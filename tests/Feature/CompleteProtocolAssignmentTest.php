<?php

use App\Actions\Protocols\CompleteProtocolAssignment;
use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Carbon\Carbon;
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
        'subject' => 'Assignment completion action test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->assigner->id,
    ]);

    $this->assignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
        'due_at' => '2026-08-25 17:00:00',
    ]);

    $this->action = app(CompleteProtocolAssignment::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('it completes a pending processing assignment', function () {
    Carbon::setTestNow('2026-08-20 14:30:00');

    $completed = $this->action->execute($this->assignment);

    $this->assertSame($this->assignment->id, $completed->id);
    $this->assertSame(
        '2026-08-20 14:30:00',
        $completed->completed_at->format('Y-m-d H:i:s')
    );
    $this->assertNull($completed->superseded_at);
    $this->assertSame(0, ProtocolAssignment::pending()->count());
    $this->assertSame(1, ProtocolAssignment::completed()->count());
    $this->assertSame(0, ProtocolAssignment::superseded()->count());
});

test('an information assignment cannot be completed', function () {
    $information = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->officer->id,
    ]);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage(
        'Only processing assignments can be completed.'
    );

    try {
        $this->action->execute($information);
    } finally {
        $this->assertNull($information->fresh()->completed_at);
    }
});

test('a superseded processing assignment cannot be completed', function () {
    $this->assignment->update([
        'superseded_at' => '2026-08-20 09:00:00',
    ]);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage(
        'A superseded assignment cannot be completed.'
    );

    try {
        $this->action->execute($this->assignment);
    } finally {
        $this->assertNull($this->assignment->fresh()->completed_at);
    }
});

test('an already completed assignment cannot be completed again', function () {
    $originalCompletion = '2026-08-20 09:00:00';

    $this->assignment->update([
        'completed_at' => $originalCompletion,
    ]);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage(
        'The assignment has already been completed.'
    );

    try {
        $this->action->execute($this->assignment);
    } finally {
        $this->assertSame(
            $originalCompletion,
            $this->assignment->fresh()->completed_at->format('Y-m-d H:i:s')
        );
    }
});

test('an orphaned assignment cannot be completed', function () {
    $this->assignment->update([
        'assigned_to' => null,
    ]);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage(
        'An assignment without an assignee cannot be completed.'
    );

    try {
        $this->action->execute($this->assignment);
    } finally {
        $this->assertNull($this->assignment->fresh()->completed_at);
    }
});

test('an assignment cannot be completed after its protocol is deleted', function () {
    $this->protocol->delete();

    $this->expectException(
        \Illuminate\Database\Eloquent\ModelNotFoundException::class
    );

    try {
        $this->action->execute($this->assignment);
    } finally {
        $this->assertNull($this->assignment->fresh()->completed_at);
    }
});

test('an unsaved assignment cannot be completed', function () {
    $unsavedAssignment = new ProtocolAssignment([
        'purpose' => ProtocolAssignmentPurpose::Processing,
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(
        'The assignment must exist before it can be completed.'
    );

    $this->action->execute($unsavedAssignment);
});
