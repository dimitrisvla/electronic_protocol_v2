<?php

use App\Enums\UserRole;
use App\Models\ArchiveFolder;
use App\Models\Protocol;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Return valid form input for protocol archive-folder interface tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validProtocolArchiveFolderData(array $overrides = []): array
{
    return array_merge([
        'protocol_number' => 120,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-20',
        'direction' => 'incoming',
        'subject' => 'Δοκιμαστικό πρωτόκολλο ταξινόμησης',
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
    ], $overrides);
}

/**
 * Create a protocol owned by the supplied user without a ProtocolFactory.
 *
 * @param  array<string, mixed>  $overrides
 */
function createProtocolForArchiveInterface(
    User $owner,
    array $overrides = []
): Protocol {
    return Protocol::query()->create(array_merge(
        validProtocolArchiveFolderData(),
        ['created_by' => $owner->id],
        $overrides
    ));
}

test('create form lists only active selectable archive folders', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $available = ArchiveFolder::factory()->create([
        'code' => 'Φ.130.1',
        'description' => 'Διαθέσιμος φάκελος',
    ]);
    $inactive = ArchiveFolder::factory()->inactive()->create([
        'code' => 'Φ.130.2',
        'description' => 'Ανενεργός φάκελος',
    ]);
    $category = ArchiveFolder::factory()->category()->create([
        'code' => 'Φ.130.3',
        'description' => 'Μη επιλέξιμη κατηγορία',
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.create'));

    $response
        ->assertOk()
        ->assertSee('name="archive_folder_id"', false)
        ->assertSee($available->code)
        ->assertDontSee($inactive->code)
        ->assertDontSee($category->code);
});

test('authorized user can create a protocol with an archive folder', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    app(ApplicationSettings::class)
        ->setAutomaticProtocolNumbering(false);
    $folder = ArchiveFolder::factory()->retainedForYears(5)->create([
        'code' => 'Φ.131.1',
    ]);

    $response = $this
        ->actingAs($administrator)
        ->post(route('protocols.store'), validProtocolArchiveFolderData([
            'archive_folder_id' => $folder->id,
        ]));

    $protocol = Protocol::query()->where([
        'protocol_number' => 120,
        'protocol_year' => 2026,
    ])->firstOrFail();

    $response
        ->assertRedirect(route('protocols.show', $protocol))
        ->assertSessionHas('success', __('flash.protocols.created'));

    expect($protocol->archive_folder_id)->toBe($folder->id);
});

test('protocol creation rejects an unavailable archive folder', function (
    string $state
) {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);

    $folder = match ($state) {
        'inactive' => ArchiveFolder::factory()->inactive()->create(),
        'not selectable' => ArchiveFolder::factory()->category()->create(),
    };

    $this
        ->actingAs($administrator)
        ->from(route('protocols.create'))
        ->post(route('protocols.store'), validProtocolArchiveFolderData([
            'archive_folder_id' => $folder->id,
        ]))
        ->assertRedirect(route('protocols.create'))
        ->assertSessionHasErrors('archive_folder_id');

    $this->assertDatabaseMissing('protocols', [
        'protocol_number' => 120,
        'protocol_year' => 2026,
    ]);
})->with(['inactive', 'not selectable']);

test('administrator can update or clear a protocols archive folder', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $firstFolder = ArchiveFolder::factory()->create(['code' => 'Φ.132.1']);
    $secondFolder = ArchiveFolder::factory()->create(['code' => 'Φ.132.2']);
    $protocol = createProtocolForArchiveInterface($administrator, [
        'archive_folder_id' => $firstFolder->id,
    ]);

    $this
        ->actingAs($administrator)
        ->put(route('protocols.update', $protocol), validProtocolArchiveFolderData([
            'archive_folder_id' => $secondFolder->id,
        ]))
        ->assertRedirect(route('protocols.show', $protocol));

    expect($protocol->refresh()->archive_folder_id)->toBe($secondFolder->id);

    $this
        ->actingAs($administrator)
        ->put(route('protocols.update', $protocol), validProtocolArchiveFolderData([
            'archive_folder_id' => null,
        ]))
        ->assertRedirect(route('protocols.show', $protocol));

    expect($protocol->refresh()->archive_folder_id)->toBeNull();
});

test('edit form preserves a currently assigned retired archive folder', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $retiredFolder = ArchiveFolder::factory()->inactive()->create([
        'code' => 'Φ.133.1',
        'description' => 'Ιστορικός φάκελος',
    ]);
    $protocol = createProtocolForArchiveInterface($administrator, [
        'archive_folder_id' => $retiredFolder->id,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->get(route('protocols.edit', $protocol));

    $response
        ->assertOk()
        ->assertSee($retiredFolder->code)
        ->assertSee(__('protocols.archive.current_unavailable'))
        ->assertSee('value="'.$retiredFolder->id.'"', false);

    $this
        ->actingAs($administrator)
        ->put(route('protocols.update', $protocol), validProtocolArchiveFolderData([
            'archive_folder_id' => $retiredFolder->id,
        ]))
        ->assertRedirect(route('protocols.show', $protocol))
        ->assertSessionDoesntHaveErrors();

    expect($protocol->refresh()->archive_folder_id)->toBe($retiredFolder->id);
});

test('protocol page displays its numeric retention period', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $folder = ArchiveFolder::factory()->retainedForYears(5)->create([
        'code' => 'Φ.134.1',
        'description' => 'Φάκελος πενταετούς διατήρησης',
    ]);
    $protocol = createProtocolForArchiveInterface($viewer, [
        'archive_folder_id' => $folder->id,
    ]);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee($folder->code)
        ->assertSee($folder->description)
        ->assertSee(trans_choice(
            'protocols.archive.retention_years',
            5,
            ['count' => 5]
        ));
});

test('protocol page displays its textual retention rule', function () {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $folder = ArchiveFolder::factory()->withRetentionRule('Διηνεκές')->create([
        'code' => 'Φ.134.2',
    ]);
    $protocol = createProtocolForArchiveInterface($viewer, [
        'archive_folder_id' => $folder->id,
    ]);

    $this
        ->actingAs($viewer)
        ->get(route('protocols.show', $protocol))
        ->assertOk()
        ->assertSee($folder->code)
        ->assertSee('Διηνεκές');
});
