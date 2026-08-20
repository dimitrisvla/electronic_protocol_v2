<?php

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this->assigner = User::factory()->create([
        'role' => UserRole::Assigner,
    ]);

    $this->assignedOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);

    $this->informationViewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->unrelatedViewer = User::factory()->create([
        'role' => UserRole::Viewer,
    ]);

    $this->protocol = Protocol::create([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-18',
        'direction' => 'incoming',
        'subject' => 'Assignment authorization test',
        'sender' => 'Example sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $this->otherOfficer->id,
    ]);

    $this->processingAssignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->assignedOfficer->id,
        'due_at' => '2026-08-25 17:00:00',
    ]);

    $this->informationAssignment = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Information,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->informationViewer->id,
    ]);
});

test('administrator and assigner can assign and reassign active protocols', function () {
    foreach ([$this->administrator, $this->assigner] as $user) {
        $this->assertTrue(
            Gate::forUser($user)->allows('assign', $this->protocol)
        );
        $this->assertTrue(
            Gate::forUser($user)->allows('reassign', $this->protocol)
        );
    }

    foreach (
        [$this->assignedOfficer, $this->otherOfficer, $this->unrelatedViewer]
        as $user
    ) {
        $this->assertFalse(
            Gate::forUser($user)->allows('assign', $this->protocol)
        );
        $this->assertFalse(
            Gate::forUser($user)->allows('reassign', $this->protocol)
        );
    }
});

test('deleted protocols cannot be assigned or reassigned', function () {
    $this->protocol->delete();

    foreach ([$this->administrator, $this->assigner] as $user) {
        $this->assertFalse(
            Gate::forUser($user)->allows('assign', $this->protocol)
        );
        $this->assertFalse(
            Gate::forUser($user)->allows('reassign', $this->protocol)
        );
    }
});

test('assigner can create and edit protocols but cannot delete them', function () {
    $gate = Gate::forUser($this->assigner);

    $this->assertTrue($gate->allows('create', Protocol::class));
    $this->assertTrue($gate->allows('update', $this->protocol));
    $this->assertFalse($gate->allows('delete', $this->protocol));
    $this->assertFalse($gate->allows('restore', $this->protocol));
    $this->assertFalse($gate->allows('forceDelete', $this->protocol));
});

test('all authenticated roles may access their scoped assignment queue', function () {
    $users = [
        $this->administrator,
        $this->assigner,
        $this->assignedOfficer,
        $this->informationViewer,
        $this->unrelatedViewer,
    ];

    foreach ($users as $user) {
        $this->assertTrue(
            Gate::forUser($user)->allows('viewAny', ProtocolAssignment::class)
        );
    }
});

test('assignment records are visible only to operational or assigned users', function () {
    foreach ([$this->administrator, $this->assigner, $this->assignedOfficer] as $user) {
        $this->assertTrue(
            Gate::forUser($user)->allows(
                'view',
                $this->processingAssignment
            )
        );
    }

    foreach ([$this->otherOfficer, $this->unrelatedViewer] as $user) {
        $this->assertFalse(
            Gate::forUser($user)->allows(
                'view',
                $this->processingAssignment
            )
        );
    }

    $this->assertTrue(
        Gate::forUser($this->informationViewer)->allows(
            'view',
            $this->informationAssignment
        )
    );
    $this->assertFalse(
        Gate::forUser($this->assignedOfficer)->allows(
            'view',
            $this->informationAssignment
        )
    );
});

test('administrator and assigned officer can complete pending processing work', function () {
    $this->assertTrue(
        Gate::forUser($this->administrator)->allows(
            'complete',
            $this->processingAssignment
        )
    );
    $this->assertTrue(
        Gate::forUser($this->assignedOfficer)->allows(
            'complete',
            $this->processingAssignment
        )
    );

    foreach (
        [$this->assigner, $this->otherOfficer, $this->informationViewer]
        as $user
    ) {
        $this->assertFalse(
            Gate::forUser($user)->allows(
                'complete',
                $this->processingAssignment
            )
        );
    }
});

test('information completed and superseded assignments cannot be completed', function () {
    $completed = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->assignedOfficer->id,
        'completed_at' => '2026-08-20 09:00:00',
    ]);

    $superseded = ProtocolAssignment::create([
        'protocol_id' => $this->protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $this->assigner->id,
        'assigned_to' => $this->assignedOfficer->id,
        'superseded_at' => '2026-08-20 10:00:00',
    ]);

    foreach (
        [$this->informationAssignment, $completed, $superseded]
        as $assignment
    ) {
        $this->assertFalse(
            Gate::forUser($this->administrator)->allows(
                'complete',
                $assignment
            )
        );
        $this->assertFalse(
            Gate::forUser($this->assignedOfficer)->allows(
                'complete',
                $assignment
            )
        );
    }
});