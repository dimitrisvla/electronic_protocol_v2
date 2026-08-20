<?php

use App\Models\ArchiveFolder;
use Database\Seeders\ArchiveFolderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the original greek archive proposal contains all 255 folders', function () {
    $this->seed(ArchiveFolderSeeder::class);

    expect(ArchiveFolder::query()->count())->toBe(255)
        ->and(ArchiveFolder::query()->distinct()->count('code'))->toBe(255)
        ->and(ArchiveFolder::query()->active()->selectable()->count())->toBe(255);
});

test('the proposal preserves representative original retention values', function () {
    $this->seed(ArchiveFolderSeeder::class);

    $this->assertDatabaseHas('archive_folders', [
        'code' => 'Φ.2.5',
        'retention_years' => 5,
        'retention_rule' => null,
        'description' => 'Απεργίες Εκπαιδευτικών – Δήλωση Απεργιών',
        'remarks' => '§ 30',
    ]);

    $this->assertDatabaseHas('archive_folders', [
        'code' => 'Φ.1.4',
        'retention_years' => null,
        'retention_rule' => 'Διηνεκές',
    ]);

    $this->assertDatabaseHas('archive_folders', [
        'code' => 'Φ.48',
        'retention_rule' => 'Κατά κρίση',
        'description' => 'ΦΑΚΕΛΟΣ mySCHOOL',
    ]);
});

test('numeric textual and unspecified retention values remain distinct', function () {
    $this->seed(ArchiveFolderSeeder::class);

    $numeric = ArchiveFolder::query()->whereNotNull('retention_years')->count();
    $textual = ArchiveFolder::query()->whereNotNull('retention_rule')->count();
    $unspecified = ArchiveFolder::query()
        ->whereNull('retention_years')
        ->whereNull('retention_rule')
        ->count();
    $conflicting = ArchiveFolder::query()
        ->whereNotNull('retention_years')
        ->whereNotNull('retention_rule')
        ->count();

    expect($numeric)->toBe(79)
        ->and($textual)->toBe(117)
        ->and($unspecified)->toBe(59)
        ->and($conflicting)->toBe(0);
});

test('the imported folder hierarchy resolves immediate parents', function () {
    $this->seed(ArchiveFolderSeeder::class);

    $topLevel = ArchiveFolder::query()->where('code', 'Φ.14')->firstOrFail();
    $section = ArchiveFolder::query()->where('code', 'Φ.14.1')->firstOrFail();
    $folder = ArchiveFolder::query()->where('code', 'Φ.14.1.1')->firstOrFail();

    expect($topLevel->parent)->toBeNull()
        ->and($section->parent->is($topLevel))->toBeTrue()
        ->and($folder->parent->is($section))->toBeTrue();
});

test('proposal ordering follows its original sequence rather than lexical codes', function () {
    $this->seed(ArchiveFolderSeeder::class);

    $codes = ArchiveFolder::query()->ordered()->pluck('code');

    expect($codes->first())->toBe('Φ.1')
        ->and($codes->search('Φ.2'))->toBeLessThan($codes->search('Φ.10'))
        ->and($codes->last())->toBe('Φ.48');
});

test('the proposal seeder is idempotent and preserves custom folders', function () {
    $this->seed(ArchiveFolderSeeder::class);

    ArchiveFolder::factory()->create([
        'code' => 'Φ.ΠΡΟΣΑΡΜΟΣΜΕΝΟ',
        'description' => 'Προσαρμοσμένος φάκελος οργανισμού',
    ]);

    // A second run must refresh proposal values without duplicating records or
    // deleting organization-specific additions.
    $this->seed(ArchiveFolderSeeder::class);

    expect(ArchiveFolder::query()->count())->toBe(256)
        ->and(ArchiveFolder::query()->where('code', 'Φ.1')->count())->toBe(1)
        ->and(ArchiveFolder::query()->where('code', 'Φ.ΠΡΟΣΑΡΜΟΣΜΕΝΟ')->exists())
        ->toBeTrue();
});
