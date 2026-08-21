<?php

use App\Models\ArchiveFolder;
use Database\Seeders\ArchiveFolderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the seeder imports every folder configured in the data file', function () {
    $proposal = require database_path('seeders/data/archive_folders.php');

    $this->seed(ArchiveFolderSeeder::class);

    $expectedCount = count($proposal);

    expect(ArchiveFolder::query()->count())->toBe($expectedCount)
        ->and(ArchiveFolder::query()->distinct()->count('code'))
        ->toBe($expectedCount)
        ->and(ArchiveFolder::query()->active()->selectable()->count())
        ->toBe($expectedCount);
});

test('the proposal preserves representative retention values', function () {
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
        'code' => 'Φ.1.1',
        'retention_years' => null,
        'retention_rule' => 'Κατά κρίση',
        'description' => 'Νόμοι',
    ]);
});

test('numeric textual and unspecified retention values remain distinct', function () {
    $this->seed(ArchiveFolderSeeder::class);

    $numeric = ArchiveFolder::query()
        ->whereNotNull('retention_years')
        ->whereNull('retention_rule')
        ->count();

    $textual = ArchiveFolder::query()
        ->whereNull('retention_years')
        ->whereNotNull('retention_rule')
        ->count();

    $unspecified = ArchiveFolder::query()
        ->whereNull('retention_years')
        ->whereNull('retention_rule')
        ->count();

    $conflicting = ArchiveFolder::query()
        ->whereNotNull('retention_years')
        ->whereNotNull('retention_rule')
        ->count();

    expect($numeric + $textual + $unspecified)
        ->toBe(ArchiveFolder::query()->count())
        ->and($conflicting)->toBe(0);
});

test('the imported folder hierarchy resolves available immediate parents', function () {
    $this->seed(ArchiveFolderSeeder::class);

    $topLevel = ArchiveFolder::query()
        ->where('code', 'Φ.3')
        ->firstOrFail();

    $folder = ArchiveFolder::query()
        ->where('code', 'Φ.3.1')
        ->firstOrFail();

    expect($topLevel->parent)->toBeNull()
        ->and($folder->parent->is($topLevel))->toBeTrue();
});

test('catalogue ordering follows the complete data-file sequence', function () {
    $proposal = require database_path('seeders/data/archive_folders.php');

    $this->seed(ArchiveFolderSeeder::class);

    $expectedCodes = array_column($proposal, 'code');
    $actualCodes = ArchiveFolder::query()
        ->ordered()
        ->pluck('code')
        ->all();

    expect($actualCodes)->toBe($expectedCodes);
});

test('the proposal seeder is idempotent and preserves custom folders', function () {
    $proposal = require database_path('seeders/data/archive_folders.php');

    $this->seed(ArchiveFolderSeeder::class);

    ArchiveFolder::factory()->create([
        'code' => 'Φ.ΠΡΟΣΑΡΜΟΣΜΕΝΟ',
        'description' => 'Προσαρμοσμένος φάκελος οργανισμού',
    ]);

    $this->seed(ArchiveFolderSeeder::class);

    expect(ArchiveFolder::query()->count())->toBe(count($proposal) + 1)
        ->and(ArchiveFolder::query()->where('code', 'Φ.1')->count())->toBe(1)
        ->and(
            ArchiveFolder::query()
                ->where('code', 'Φ.ΠΡΟΣΑΡΜΟΣΜΕΝΟ')
                ->exists()
        )->toBeTrue();
});
