<?php

use App\Models\ArchiveFolder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('archive folder catalogue has the normalized foundation columns', function () {
    expect(Schema::hasColumns('archive_folders', [
        'id',
        'parent_id',
        'code',
        'description',
        'retention_years',
        'retention_rule',
        'remarks',
        'is_selectable',
        'is_active',
        'sort_order',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('archive folder values are cast to their application types', function () {
    $folder = ArchiveFolder::factory()->create([
        'retention_years' => 5,
        'is_selectable' => true,
        'is_active' => false,
        'sort_order' => 12,
    ])->fresh();

    expect($folder->retention_years)->toBeInt()->toBe(5)
        ->and($folder->is_selectable)->toBeBool()->toBeTrue()
        ->and($folder->is_active)->toBeBool()->toBeFalse()
        ->and($folder->sort_order)->toBeInt()->toBe(12);
});

test('archive folders can be organised into parent and child categories', function () {
    $category = ArchiveFolder::factory()->category()->create([
        'code' => 'Φ.1',
        'description' => 'ΕΚΠΑΙΔΕΥΤΙΚΗ ΝΟΜΟΘΕΣΙΑ - ΝΟΜΟΛΟΓΙΑ',
        'sort_order' => 1,
    ]);

    $folder = ArchiveFolder::factory()->withRetentionRule('Κατά κρίση')->create([
        'parent_id' => $category->id,
        'code' => 'Φ.1.1',
        'description' => 'Νόμοι',
        'sort_order' => 2,
    ]);

    expect($folder->parent->is($category))->toBeTrue()
        ->and($category->children)->toHaveCount(1)
        ->and($category->children->first()->is($folder))->toBeTrue();
});

test('numeric and textual retention remain distinct', function () {
    $numeric = ArchiveFolder::factory()->retainedForYears(5)->create();
    $textual = ArchiveFolder::factory()->withRetentionRule('Διηνεκές')->create();
    $category = ArchiveFolder::factory()->category()->create();

    expect($numeric->hasNumericRetention())->toBeTrue()
        ->and($numeric->hasTextualRetention())->toBeFalse()
        ->and($textual->hasNumericRetention())->toBeFalse()
        ->and($textual->hasTextualRetention())->toBeTrue()
        ->and($category->hasNumericRetention())->toBeFalse()
        ->and($category->hasTextualRetention())->toBeFalse();
});

test('catalogue scopes filter and order folders consistently', function () {
    $later = ArchiveFolder::factory()->create([
        'code' => 'Φ.10.1',
        'sort_order' => 20,
    ]);

    $earlier = ArchiveFolder::factory()->create([
        'code' => 'Φ.2.1',
        'sort_order' => 10,
    ]);

    ArchiveFolder::factory()->category()->create([
        'code' => 'Φ.2',
        'sort_order' => 5,
    ]);

    ArchiveFolder::factory()->inactive()->create([
        'code' => 'Φ.3.1',
        'sort_order' => 1,
    ]);

    $folders = ArchiveFolder::query()
        ->active()
        ->selectable()
        ->ordered()
        ->get();

    expect($folders->modelKeys())->toBe([
        $earlier->id,
        $later->id,
    ]);
});

test('archive folder codes are unique', function () {
    ArchiveFolder::factory()->create(['code' => 'Φ.5.1']);

    expect(fn () => ArchiveFolder::factory()->create(['code' => 'Φ.5.1']))
        ->toThrow(QueryException::class);
});
