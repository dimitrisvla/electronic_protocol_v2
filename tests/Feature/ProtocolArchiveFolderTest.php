<?php

use App\Models\ArchiveFolder;
use App\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Create a protocol without depending on a ProtocolFactory.
 *
 * @param  array<string, mixed>  $overrides
 */
function createProtocolForArchiveFolderTest(array $overrides = []): Protocol
{
    return Protocol::query()->create(array_merge([
        'protocol_number' => 1,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-20',
        'direction' => 'incoming',
        'subject' => 'Δοκιμαστικό πρωτόκολλο αρχειοθέτησης',
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
        'created_by' => null,
    ], $overrides));
}

test('protocols table has an optional archive folder reference', function () {
    expect(Schema::hasColumn('protocols', 'archive_folder_id'))->toBeTrue();

    $protocol = createProtocolForArchiveFolderTest();

    expect($protocol->archive_folder_id)->toBeNull()
        ->and($protocol->archiveFolder)->toBeNull();
});

test('a protocol can belong to an archive folder', function () {
    $folder = ArchiveFolder::factory()->retainedForYears(5)->create([
        'code' => 'Φ.120.1',
        'description' => 'Δοκιμαστικός φάκελος πρωτοκόλλων',
    ]);
    $protocol = createProtocolForArchiveFolderTest([
        'archive_folder_id' => $folder->id,
    ]);

    expect($protocol->archiveFolder->is($folder))->toBeTrue()
        ->and($folder->protocols()->whereKey($protocol->id)->exists())->toBeTrue();
});

test('removing an archive folder preserves its protocols', function () {
    $folder = ArchiveFolder::factory()->create([
        'code' => 'Φ.120.2',
    ]);
    $protocol = createProtocolForArchiveFolderTest([
        'archive_folder_id' => $folder->id,
    ]);

    $folder->delete();

    $protocol->refresh();

    expect($protocol->archive_folder_id)->toBeNull()
        ->and(Protocol::query()->whereKey($protocol->id)->exists())->toBeTrue();
});

test('deleting a protocol does not delete its archive folder', function () {
    $folder = ArchiveFolder::factory()->create([
        'code' => 'Φ.120.3',
    ]);
    $protocol = createProtocolForArchiveFolderTest([
        'archive_folder_id' => $folder->id,
    ]);

    $protocol->delete();

    expect(ArchiveFolder::query()->whereKey($folder->id)->exists())->toBeTrue()
        ->and(Protocol::onlyTrashed()->whereKey($protocol->id)->exists())->toBeTrue();
});
