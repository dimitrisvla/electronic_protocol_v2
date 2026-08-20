<?php

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolRelation;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validRelatedProtocolManagementData(array $overrides = []): array
{
    return array_merge([
        'protocol_number' => 500,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-20',
        'direction' => 'incoming',
        'subject' => 'Δοκιμή διαχείρισης σχετικών πρωτοκόλλων',
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
        'related_protocols' => [],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createProtocolForRelationManagement(
    User $owner,
    int $number,
    int $year = 2026,
    array $overrides = []
): Protocol {
    $data = array_merge(
        validRelatedProtocolManagementData([
            'protocol_number' => $number,
            'protocol_year' => $year,
        ]),
        ['created_by' => $owner->id],
        $overrides
    );

    unset($data['related_protocols']);

    return Protocol::query()->create($data);
}

beforeEach(function () {
    app(ApplicationSettings::class)->setAutomaticProtocolNumbering(false);
});

test('create form provides repeatable number and year controls', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this
        ->actingAs($administrator)
        ->get(route('protocols.create'))
        ->assertOk()
        ->assertSee(__('protocols.related.title'))
        ->assertSee(
            'name="related_protocols[0][protocol_number]"',
            false
        )
        ->assertSee(
            'name="related_protocols[0][protocol_year]"',
            false
        )
        ->assertSee('id="add-related-protocol"', false);
});

test('authorized user can create a protocol with symmetric relations', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $firstRelated = createProtocolForRelationManagement(
        $administrator,
        101,
        2025
    );
    $secondRelated = createProtocolForRelationManagement(
        $administrator,
        102,
        2026
    );

    $response = $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validRelatedProtocolManagementData([
            'related_protocols' => [
                [
                    'protocol_number' => 101,
                    'protocol_year' => 2025,
                ],
                [
                    'protocol_number' => '',
                    'protocol_year' => '',
                ],
                [
                    'protocol_number' => 102,
                    'protocol_year' => 2026,
                ],
            ],
        ]));

    $protocol = Protocol::query()
        ->where('protocol_number', 500)
        ->where('protocol_year', 2026)
        ->firstOrFail();

    $response
        ->assertRedirect(route('protocols.show', $protocol))
        ->assertSessionHasNoErrors();

    expect($protocol->relatedProtocols()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$firstRelated->id, $secondRelated->id])
            ->sort()
            ->values()
            ->all())
        ->and($firstRelated->relatedProtocols()->sole()->is($protocol))
        ->toBeTrue()
        ->and(ProtocolRelation::count())->toBe(2);
});

test('each related protocol row requires both number and year', function (
    array $row,
    string $errorKey
) {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $this
        ->actingAs($administrator)
        ->from(route('protocols.create'))
        ->post(route('protocols.store'), validRelatedProtocolManagementData([
            'related_protocols' => [$row],
        ]))
        ->assertRedirect(route('protocols.create'))
        ->assertSessionHasErrors($errorKey);

    expect(Protocol::query()->count())->toBe(0)
        ->and(ProtocolRelation::query()->count())->toBe(0);
})->with([
    'number without year' => [
        ['protocol_number' => 101, 'protocol_year' => ''],
        'related_protocols.0.protocol_year',
    ],
    'year without number' => [
        ['protocol_number' => '', 'protocol_year' => 2026],
        'related_protocols.0.protocol_number',
    ],
]);

test('missing and duplicate protocol references are rejected', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    createProtocolForRelationManagement($administrator, 201, 2026);

    $response = $this
        ->actingAs($administrator)
        ->from(route('protocols.create'))
        ->post(route('protocols.store'), validRelatedProtocolManagementData([
            'related_protocols' => [
                ['protocol_number' => 201, 'protocol_year' => 2026],
                ['protocol_number' => 201, 'protocol_year' => 2026],
                ['protocol_number' => 999, 'protocol_year' => 2026],
            ],
        ]));

    $response
        ->assertRedirect(route('protocols.create'))
        ->assertSessionHasErrors([
            'related_protocols.1.protocol_number',
            'related_protocols.2.protocol_number',
        ]);

    expect(ProtocolRelation::query()->count())->toBe(0);
});

test('edit form displays every active existing relation', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForRelationManagement($administrator, 301);
    $related = createProtocolForRelationManagement($administrator, 302, 2025);
    ProtocolRelation::connect($protocol, $related, $administrator);

    $this
        ->actingAs($administrator)
        ->get(route('protocols.edit', $protocol))
        ->assertOk()
        ->assertSee(__('protocols.related.title'))
        ->assertSee('value="302"', false)
        ->assertSee('value="2025"', false);
});

test('updating a protocol synchronizes active relations', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForRelationManagement($administrator, 401);
    $removed = createProtocolForRelationManagement($administrator, 402);
    $retained = createProtocolForRelationManagement($administrator, 403);
    $added = createProtocolForRelationManagement($administrator, 404, 2025);
    ProtocolRelation::connect($protocol, $removed, $administrator);
    ProtocolRelation::connect($protocol, $retained, $administrator);

    $this
        ->actingAs($administrator)
        ->put(route('protocols.update', $protocol),
            validRelatedProtocolManagementData([
                'protocol_number' => 401,
                'related_protocols' => [
                    ['protocol_number' => 403, 'protocol_year' => 2026],
                    ['protocol_number' => 404, 'protocol_year' => 2025],
                ],
            ]))
        ->assertRedirect(route('protocols.show', $protocol))
        ->assertSessionHasNoErrors();

    expect($protocol->relatedProtocols()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$retained->id, $added->id])
            ->sort()
            ->values()
            ->all())
        ->and($removed->relatedProtocols())->toHaveCount(0);
});

test('a protocol cannot be related to itself through the update form', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForRelationManagement($administrator, 501);

    $this
        ->actingAs($administrator)
        ->from(route('protocols.edit', $protocol))
        ->put(route('protocols.update', $protocol),
            validRelatedProtocolManagementData([
                'protocol_number' => 501,
                'related_protocols' => [[
                    'protocol_number' => 501,
                    'protocol_year' => 2026,
                ]],
            ]))
        ->assertRedirect(route('protocols.edit', $protocol))
        ->assertSessionHasErrors(
            'related_protocols.0.protocol_number'
        );

    expect(ProtocolRelation::query()->count())->toBe(0);
});

test('unauthorized user cannot change another protocols relations', function () {
    $owner = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);
    $otherOfficer = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);
    $protocol = createProtocolForRelationManagement($owner, 601);
    $related = createProtocolForRelationManagement($owner, 602);

    $this
        ->actingAs($otherOfficer)
        ->put(route('protocols.update', $protocol),
            validRelatedProtocolManagementData([
                'protocol_number' => 601,
                'related_protocols' => [[
                    'protocol_number' => 602,
                    'protocol_year' => 2026,
                ]],
            ]))
        ->assertForbidden();

    expect(ProtocolRelation::query()->count())->toBe(0);
});

test('editing preserves relations hidden by a soft deleted endpoint', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $protocol = createProtocolForRelationManagement($administrator, 701);
    $related = createProtocolForRelationManagement($administrator, 702);
    $relation = ProtocolRelation::connect(
        $protocol,
        $related,
        $administrator
    );
    $related->delete();

    $this
        ->actingAs($administrator)
        ->put(route('protocols.update', $protocol),
            validRelatedProtocolManagementData([
                'protocol_number' => 701,
                'related_protocols' => [],
            ]))
        ->assertRedirect(route('protocols.show', $protocol))
        ->assertSessionHasNoErrors();

    expect(ProtocolRelation::query()->find($relation->id))->not->toBeNull();
});
