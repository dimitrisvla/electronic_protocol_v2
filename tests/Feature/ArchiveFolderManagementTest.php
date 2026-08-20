<?php

use App\Enums\UserRole;
use App\Models\ArchiveFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

/**
 * Return valid catalogue input that individual tests may override.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validArchiveFolderData(array $overrides = []): array
{
    return array_merge([
        'code' => 'Φ.60.1',
        'retention_years' => 5,
        'retention_rule' => null,
        'description' => 'Δοκιμαστικός φάκελος αρχείου',
        'remarks' => 'Δοκιμαστική παρατήρηση',
    ], $overrides);
}

test('guest cannot view the archive folder catalogue', function () {
    $this->get(route('admin.archive-folders.index'))
        ->assertRedirect(route('login'));
});

test('every authenticated role can consult the archive folder catalogue', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);
    $folder = ArchiveFolder::factory()->create([
        'code' => 'Φ.2.5',
        'description' => 'Απεργίες Εκπαιδευτικών',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('admin.archive-folders.index'));

    $response
        ->assertOk()
        ->assertSee(__('archive_folders.index.title'))
        ->assertSee($folder->code)
        ->assertSee($folder->description);
})->with([
    'administrator' => UserRole::Administrator,
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('administrator sees catalogue management controls and navigation', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $folder = ArchiveFolder::factory()->create();

    $response = $this
        ->actingAs($administrator)
        ->get(route('admin.archive-folders.index'));

    $response
        ->assertOk()
        ->assertSee(__('archive_folders.navigation'))
        ->assertSee(route('admin.archive-folders.index'), false)
        ->assertSee('name="retention_years"', false)
        ->assertSee(route('admin.archive-folders.edit', $folder), false)
        ->assertSee(route('admin.archive-folders.destroy', $folder), false);
});

test('retention fields provide immediate mutual exclusion in the browser', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('admin.archive-folders.index'));

    $response
        ->assertOk()
        ->assertSee('data-retention-years', false)
        ->assertSee('data-retention-rule', false)
        ->assertSee('synchronizeRetentionInputs', false)
        ->assertSee('yearsInput.disabled = hasRule', false)
        ->assertSee('ruleInput.disabled = hasYears', false);
});

test('non administrators see the catalogue without management controls', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);
    $folder = ArchiveFolder::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('admin.archive-folders.index'));

    $response
        ->assertOk()
        ->assertSee($folder->code)
        ->assertDontSee('name="retention_years"', false)
        ->assertDontSee(route('admin.archive-folders.edit', $folder), false)
        ->assertDontSee(route('admin.archive-folders.destroy', $folder), false);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('administrator can create a folder with numeric retention', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $parent = ArchiveFolder::factory()->create([
        'code' => 'Φ.60',
        'retention_years' => null,
        'description' => 'Δοκιμαστική κατηγορία',
    ]);

    $response = $this
        ->actingAs($administrator)
        ->post(
            route('admin.archive-folders.store'),
            validArchiveFolderData()
        );

    $response
        ->assertRedirect(route('admin.archive-folders.index'))
        ->assertSessionHas('success', __('flash.archive_folders.created'));

    $created = ArchiveFolder::query()->where('code', 'Φ.60.1')->firstOrFail();

    expect($created->retention_years)->toBe(5)
        ->and($created->retention_rule)->toBeNull()
        ->and($created->parent->is($parent))->toBeTrue()
        ->and($created->is_active)->toBeTrue()
        ->and($created->is_selectable)->toBeTrue();
});

test('administrator can create a folder with textual or unspecified retention', function (
    ?string $rule,
    string $code
) {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->post(route('admin.archive-folders.store'), validArchiveFolderData([
            'code' => $code,
            'retention_years' => null,
            'retention_rule' => $rule,
        ]));

    $response->assertRedirect(route('admin.archive-folders.index'));

    $this->assertDatabaseHas('archive_folders', [
        'code' => $code,
        'retention_years' => null,
        'retention_rule' => $rule,
    ]);
})->with([
    'textual retention' => ['Διηνεκές', 'Φ.61'],
    'unspecified retention' => [null, 'Φ.62'],
]);

test('archive folder creation validates code uniqueness and retention values', function (
    array $overrides,
    array $expectedErrors
) {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    if (($overrides['code'] ?? null) === 'Φ.60.1') {
        ArchiveFolder::factory()->create(['code' => 'Φ.60.1']);
    }

    $response = $this
        ->actingAs($administrator)
        ->from(route('admin.archive-folders.index'))
        ->post(
            route('admin.archive-folders.store'),
            validArchiveFolderData($overrides)
        );

    $response
        ->assertRedirect(route('admin.archive-folders.index'))
        ->assertSessionHasErrors($expectedErrors);
})->with([
    'invalid code prefix' => [
        ['code' => 'F.60.1'],
        ['code'],
    ],
    'duplicate code' => [
        ['code' => 'Φ.60.1'],
        ['code'],
    ],
    'retention below one year' => [
        ['retention_years' => 0],
        ['retention_years'],
    ],
    'both retention representations' => [
        ['retention_years' => 5, 'retention_rule' => 'Διηνεκές'],
        ['retention_years', 'retention_rule'],
    ],
    'missing description' => [
        ['description' => ''],
        ['description'],
    ],
]);

test('administrator can open and update an existing folder', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $folder = ArchiveFolder::factory()->retainedForYears(5)->create([
        'code' => 'Φ.70.1',
    ]);

    $this
        ->actingAs($administrator)
        ->get(route('admin.archive-folders.edit', $folder))
        ->assertOk()
        ->assertSee(__('archive_folders.form.edit_title', [
            'code' => $folder->code,
        ]))
        ->assertSee(route('admin.archive-folders.update', $folder), false);

    $response = $this
        ->actingAs($administrator)
        ->put(route('admin.archive-folders.update', $folder), [
            'code' => 'Φ.70.2',
            'retention_years' => null,
            'retention_rule' => 'Μέχρι τροποποίησης',
            'description' => 'Ενημερωμένη περιγραφή',
            'remarks' => '',
        ]);

    $response
        ->assertRedirect(route('admin.archive-folders.index'))
        ->assertSessionHas('success', __('flash.archive_folders.updated'));

    $this->assertDatabaseHas('archive_folders', [
        'id' => $folder->id,
        'code' => 'Φ.70.2',
        'retention_years' => null,
        'retention_rule' => 'Μέχρι τροποποίησης',
        'description' => 'Ενημερωμένη περιγραφή',
        'remarks' => null,
    ]);
});

test('administrator can delete a parent without deleting its children', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $parent = ArchiveFolder::factory()->create(['code' => 'Φ.80']);
    $child = ArchiveFolder::factory()->create([
        'parent_id' => $parent->id,
        'code' => 'Φ.80.1',
    ]);

    $response = $this
        ->actingAs($administrator)
        ->delete(route('admin.archive-folders.destroy', $parent));

    $response
        ->assertRedirect(route('admin.archive-folders.index'))
        ->assertSessionHas('success', __('flash.archive_folders.deleted'));

    $this->assertDatabaseMissing('archive_folders', ['id' => $parent->id]);
    $this->assertDatabaseHas('archive_folders', [
        'id' => $child->id,
        'parent_id' => null,
    ]);
});

test('non administrators cannot create update or delete folders', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);
    $folder = ArchiveFolder::factory()->create([
        'code' => 'Φ.90.1',
        'description' => 'Αρχική περιγραφή',
    ]);

    $this
        ->actingAs($user)
        ->get(route('admin.archive-folders.edit', $folder))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->post(
            route('admin.archive-folders.store'),
            validArchiveFolderData(['code' => 'Φ.90.2'])
        )
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->put(route('admin.archive-folders.update', $folder), validArchiveFolderData([
            'code' => $folder->code,
            'description' => 'Απαγορευμένη αλλαγή',
        ]))
        ->assertForbidden();

    $this
        ->actingAs($user)
        ->delete(route('admin.archive-folders.destroy', $folder))
        ->assertForbidden();

    $this->assertDatabaseMissing('archive_folders', ['code' => 'Φ.90.2']);
    $this->assertDatabaseHas('archive_folders', [
        'id' => $folder->id,
        'description' => 'Αρχική περιγραφή',
    ]);
})->with([
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('archive folder catalogue paginates fifteen rows', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);
    ArchiveFolder::factory()->count(16)->create();

    $this
        ->actingAs($user)
        ->get(route('admin.archive-folders.index'))
        ->assertOk()
        ->assertViewHas(
            'archiveFolders',
            fn (LengthAwarePaginator $folders): bool =>
                $folders->perPage() === 15
                && $folders->total() === 16
                && $folders->count() === 15
        );
});
