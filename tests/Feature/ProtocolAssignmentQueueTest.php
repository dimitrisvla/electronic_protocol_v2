<?php

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createQueueTestProtocol(User $creator, string $subject): Protocol
{
    static $protocolNumber = 0;

    $protocolNumber++;

    return Protocol::create([
        'protocol_number' => $protocolNumber,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-19',
        'direction' => 'incoming',
        'subject' => $subject,
        'sender' => 'Queue test sender',
        'recipient' => null,
        'notes' => null,
        'created_by' => $creator->id,
    ]);
}

function createQueueTestAssignment(
    Protocol $protocol,
    User $assigner,
    User $assignee,
    ProtocolAssignmentPurpose $purpose,
    ?string $dueAt = null,
    ?string $completedAt = null,
    ?string $supersededAt = null,
): ProtocolAssignment {
    return ProtocolAssignment::create([
        'protocol_id' => $protocol->id,
        'purpose' => $purpose,
        'assigned_by' => $assigner->id,
        'assigned_to' => $assignee->id,
        'due_at' => $dueAt,
        'completed_at' => $completedAt,
        'superseded_at' => $supersededAt,
    ]);
}

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
});

test('a guest cannot view assignment queues', function () {
    $this->get(route('assignments.index'))
        ->assertRedirect(route('login'));
});

test('every authenticated role can open its assignment queue', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertViewIs('assignments.index');
})->with([
    'administrator' => UserRole::Administrator,
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('administrator and assigner receive organization wide oversight', function (
    UserRole $role
) {
    $firstProtocol = createQueueTestProtocol(
        $this->assigner,
        'First oversight assignment'
    );
    $secondProtocol = createQueueTestProtocol(
        $this->assigner,
        'Second oversight assignment'
    );

    $first = createQueueTestAssignment(
        $firstProtocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing
    );
    $second = createQueueTestAssignment(
        $secondProtocol,
        $this->assigner,
        $this->otherOfficer,
        ProtocolAssignmentPurpose::Processing
    );

    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('assignments.index', ['queue' => 'processing']))
        ->assertOk()
        ->assertViewHas('canViewAllAssignments', true)
        ->assertViewHas('assignments', function ($assignments) use (
            $first,
            $second
        ): bool {
            $ids = collect($assignments->items())->pluck('id');

            return $ids->contains($first->id)
                && $ids->contains($second->id);
        });
})->with([
    'administrator' => UserRole::Administrator,
    'assigner' => UserRole::Assigner,
]);

test('non oversight roles see only assignments addressed to them', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);
    $ownProtocol = createQueueTestProtocol(
        $this->assigner,
        'Visible personal assignment'
    );
    $otherProtocol = createQueueTestProtocol(
        $this->assigner,
        'Hidden assignment for another user'
    );

    $own = createQueueTestAssignment(
        $ownProtocol,
        $this->assigner,
        $user,
        ProtocolAssignmentPurpose::Processing
    );
    createQueueTestAssignment(
        $otherProtocol,
        $this->assigner,
        $this->otherOfficer,
        ProtocolAssignmentPurpose::Processing
    );

    $this->actingAs($user)
        ->get(route('assignments.index', ['queue' => 'processing']))
        ->assertOk()
        ->assertViewHas('canViewAllAssignments', false)
        ->assertViewHas('assignments', function ($assignments) use (
            $own
        ): bool {
            return collect($assignments->items())->pluck('id')->all()
                === [$own->id];
        });
})->with([
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('a viewer defaults to the information queue', function () {
    $informationProtocol = createQueueTestProtocol(
        $this->assigner,
        'Information for the viewer'
    );
    $processingProtocol = createQueueTestProtocol(
        $this->assigner,
        'Processing work for the viewer'
    );

    $information = createQueueTestAssignment(
        $informationProtocol,
        $this->assigner,
        $this->viewer,
        ProtocolAssignmentPurpose::Information
    );
    createQueueTestAssignment(
        $processingProtocol,
        $this->assigner,
        $this->viewer,
        ProtocolAssignmentPurpose::Processing
    );

    $this->actingAs($this->viewer)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertViewHas('queue', 'information')
        ->assertViewHas('assignments', function ($assignments) use (
            $information
        ): bool {
            return collect($assignments->items())->pluck('id')->all()
                === [$information->id];
        });
});

test('processing completed and superseded assignments remain separate', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Assignment lifecycle separation'
    );

    $pending = createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing
    );
    $completed = createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing,
        completedAt: '2026-08-19 10:00:00'
    );
    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing,
        supersededAt: '2026-08-19 11:00:00'
    );

    $this->actingAs($this->officer)
        ->get(route('assignments.index', ['queue' => 'processing']))
        ->assertViewHas('assignments', function ($assignments) use (
            $pending
        ): bool {
            return collect($assignments->items())->pluck('id')->all()
                === [$pending->id];
        });

    $this->actingAs($this->officer)
        ->get(route('assignments.index', ['queue' => 'completed']))
        ->assertViewHas('assignments', function ($assignments) use (
            $completed
        ): bool {
            return collect($assignments->items())->pluck('id')->all()
                === [$completed->id];
        });
});

test('queue counts use the same role and lifecycle scope', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Visible queue counts'
    );
    $hiddenProtocol = createQueueTestProtocol(
        $this->assigner,
        'Hidden queue count'
    );

    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->viewer,
        ProtocolAssignmentPurpose::Processing
    );
    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->viewer,
        ProtocolAssignmentPurpose::Information
    );
    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->viewer,
        ProtocolAssignmentPurpose::Processing,
        completedAt: '2026-08-19 12:00:00'
    );
    createQueueTestAssignment(
        $hiddenProtocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Information
    );

    $this->actingAs($this->viewer)
        ->get(route('assignments.index'))
        ->assertViewHas('queueCounts', function ($counts): bool {
            return $counts->all() === [
                'processing' => 1,
                'information' => 1,
                'completed' => 1,
            ];
        });
});

test('soft deleted protocols are excluded from queues and counts', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Deleted protocol assignment'
    );

    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing
    );

    $protocol->delete();

    $this->actingAs($this->administrator)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertViewHas('assignments', function ($assignments): bool {
            return $assignments->total() === 0;
        })
        ->assertViewHas('queueCounts', function ($counts): bool {
            return $counts->sum() === 0;
        });
});

test('processing work is ordered by earliest known deadline with null last', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Deadline ordering'
    );

    $withoutDeadline = createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing
    );
    $later = createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing,
        dueAt: '2026-08-25 17:00:00'
    );
    $earlier = createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing,
        dueAt: '2026-08-20 17:00:00'
    );

    $this->actingAs($this->officer)
        ->get(route('assignments.index', ['queue' => 'processing']))
        ->assertViewHas('assignments', function ($assignments) use (
            $earlier,
            $later,
            $withoutDeadline
        ): bool {
            return collect($assignments->items())->pluck('id')->all() === [
                $earlier->id,
                $later->id,
                $withoutDeadline->id,
            ];
        });
});

test('queue rows eager load the protocol and assignment users', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Eager-loaded queue row'
    );

    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing
    );

    $this->actingAs($this->administrator)
        ->get(route('assignments.index'))
        ->assertViewHas('assignments', function ($assignments): bool {
            $assignment = collect($assignments->items())->first();

            return $assignment->relationLoaded('protocol')
                && $assignment->relationLoaded('assigner')
                && $assignment->relationLoaded('assignee');
        });
});

test('invalid queue input returns not found', function (string $url) {
    $this->actingAs($this->administrator)
        ->get($url)
        ->assertNotFound();
})->with([
    'unknown queue' => '/assignments?queue=unknown',
    'array queue' => '/assignments?queue[]=processing',
]);

test('queues paginate fifteen rows and preserve the selected queue', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Paginated information assignments'
    );

    foreach (range(1, 16) as $unused) {
        createQueueTestAssignment(
            $protocol,
            $this->assigner,
            $this->viewer,
            ProtocolAssignmentPurpose::Information
        );
    }

    $this->actingAs($this->viewer)
        ->get(route('assignments.index', ['queue' => 'information']))
        ->assertOk()
        ->assertViewHas('assignments', function ($assignments): bool {
            return $assignments->count() === 15
                && $assignments->total() === 16
                && str_contains(
                    $assignments->nextPageUrl(),
                    'queue=information'
                );
        });
});

test('assignment navigation adapts its label and destination to the role', function (
    UserRole $role,
    string $labelKey,
    string $queue
) {
    $user = User::factory()->create(['role' => $role]);
    $url = route('assignments.index', ['queue' => $queue]);

    $this->actingAs($user)
        ->get(route('protocols.index'))
        ->assertOk()
        ->assertSee(__($labelKey))
        ->assertSee($url, false);
})->with([
    'administrator oversight' => [
        UserRole::Administrator,
        'common.navigation.assignment_oversight',
        'processing',
    ],
    'assigner oversight' => [
        UserRole::Assigner,
        'common.navigation.assignment_oversight',
        'processing',
    ],
    'protocol officer work' => [
        UserRole::ProtocolOfficer,
        'common.navigation.my_processing_work',
        'processing',
    ],
    'viewer information' => [
        UserRole::Viewer,
        'common.navigation.for_information',
        'information',
    ],
]);

test('the assignment navigation link marks the queue as the current page', function () {
    $url = route('assignments.index', ['queue' => 'processing']);

    $this->actingAs($this->officer)
        ->get($url)
        ->assertOk()
        ->assertSeeInOrder([
            $url,
            'navigation-link-active',
            'aria-current="page"',
            __('common.navigation.my_processing_work'),
        ], false);
});

test('oversight queue renders organization details and assignment status', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Final queue interface assignment'
    );

    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->officer,
        ProtocolAssignmentPurpose::Processing,
        dueAt: '2026-08-25 17:00:00'
    );

    $this->actingAs($this->administrator)
        ->get(route('assignments.index', ['queue' => 'processing']))
        ->assertOk()
        ->assertSee(__('assignments.titles.processing.oversight'))
        ->assertSee(__('assignments.scope.organization'))
        ->assertSee(__('assignments.columns.assigned_to'))
        ->assertSee(__('assignments.columns.assigned_by'))
        ->assertSee($this->officer->name)
        ->assertSee($this->assigner->name)
        ->assertSee('Final queue interface assignment')
        ->assertSee(__('protocols.directions.incoming'))
        ->assertSee(__('assignments.actions.view_protocol'));
});

test('a personal information queue hides the organization assignee column', function () {
    $protocol = createQueueTestProtocol(
        $this->assigner,
        'Personal information queue assignment'
    );

    createQueueTestAssignment(
        $protocol,
        $this->assigner,
        $this->viewer,
        ProtocolAssignmentPurpose::Information
    );

    $this->actingAs($this->viewer)
        ->get(route('assignments.index', ['queue' => 'information']))
        ->assertOk()
        ->assertSee(__('assignments.titles.information.personal'))
        ->assertSee(__('assignments.scope.personal'))
        ->assertSee(__('assignments.columns.assigned_by'))
        ->assertDontSee(__('assignments.columns.assigned_to'))
        ->assertSee('Personal information queue assignment');
});

test('the queue interface provides accessible tabs and a useful empty state', function () {
    $this->actingAs($this->officer)
        ->get(route('assignments.index', ['queue' => 'completed']))
        ->assertOk()
        ->assertSee(
            'aria-label="' . __('assignments.aria.queue_tabs') . '"',
            false
        )
        ->assertSee('queue-tab-active', false)
        ->assertSee(__('assignments.tabs.processing'))
        ->assertSee(__('assignments.tabs.information'))
        ->assertSee(__('assignments.tabs.completed'))
        ->assertSee(trans_choice('assignments.count', 0, ['count' => 0]))
        ->assertSee(__('assignments.empty.title'))
        ->assertSee(__('assignments.empty.completed'));
});
