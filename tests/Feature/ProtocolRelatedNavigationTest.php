<?php

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createProtocolForRelatedNavigation(
    User $owner,
    int $number,
    int $year = 2026,
    string $direction = 'incoming',
    ?string $subject = null
): Protocol {
    return Protocol::query()->create([
        'protocol_number' => $number,
        'protocol_year' => $year,
        'protocol_date' => "{$year}-08-20",
        'direction' => $direction,
        'subject' => $subject ?? "Πρωτόκολλο {$number}/{$year}",
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
        'created_by' => $owner->id,
    ]);
}

test('every authenticated role can navigate to a related protocol', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);
    $protocol = createProtocolForRelatedNavigation($user, 101);
    $related = createProtocolForRelatedNavigation($user, 102, 2025);
    ProtocolRelation::connect($protocol, $related, $user);

    $this
        ->actingAs($user)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee(__('protocols.related.title'))
        ->assertSee('102/2025')
        ->assertSee(route('protocols.show', $related), false)
        ->assertSee(__('protocols.related.open'));
})->with([
    'administrator' => UserRole::Administrator,
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('navigation is automatically available from both protocols', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $first = createProtocolForRelatedNavigation($administrator, 201);
    $second = createProtocolForRelatedNavigation($administrator, 202);
    ProtocolRelation::connect($first, $second, $administrator);

    $this
        ->actingAs($administrator)
        ->get(route('protocols.show', $first))
        ->assertOk()
        ->assertSee(route('protocols.show', $second), false);

    $this
        ->actingAs($administrator)
        ->get(route('protocols.show', $second))
        ->assertOk()
        ->assertSee(route('protocols.show', $first), false);
});

test('related protocol rows display operational details', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createProtocolForRelatedNavigation($viewer, 301);
    $related = createProtocolForRelatedNavigation(
        $viewer,
        302,
        2025,
        'outgoing',
        'Θέμα σχετικού εξερχόμενου πρωτοκόλλου'
    );
    ProtocolRelation::connect($protocol, $related, $viewer);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee('302/2025')
        ->assertSee('20/08/2025')
        ->assertSee(__('protocols.directions.outgoing'))
        ->assertSee('Θέμα σχετικού εξερχόμενου πρωτοκόλλου');
});

test('unrelated protocols do not enter the navigation section', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createProtocolForRelatedNavigation($viewer, 401);
    $related = createProtocolForRelatedNavigation($viewer, 402);
    $unrelated = createProtocolForRelatedNavigation($viewer, 403);
    ProtocolRelation::connect($protocol, $related, $viewer);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee('402/2026')
        ->assertDontSee('403/2026')
        ->assertDontSee(route('protocols.show', $unrelated), false);
});

test('protocol without relations displays a useful empty state', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createProtocolForRelatedNavigation($viewer, 501);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee(__('protocols.related.title'))
        ->assertSee(__('protocols.related.empty'));
});

test('soft deleted related protocol is hidden and returns after restoration', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForRelatedNavigation($administrator, 601);
    $related = createProtocolForRelatedNavigation($administrator, 602);
    ProtocolRelation::connect($protocol, $related, $administrator);
    $related->delete();

    $this
        ->actingAs($administrator)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertDontSee('602/2026')
        ->assertDontSee(route('protocols.show', $related), false)
        ->assertSee(__('protocols.related.empty'));

    $related->restore();

    $this
        ->actingAs($administrator)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee('602/2026')
        ->assertSee(route('protocols.show', $related), false);
});

test('guest cannot follow a related protocol link', function () {
    $owner = User::factory()->create();
    $protocol = createProtocolForRelatedNavigation($owner, 701);

    $this
        ->get(route('protocols.show', $protocol))
        ->assertRedirect(route('login'));
});
