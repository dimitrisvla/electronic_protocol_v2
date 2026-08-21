<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports every archive folder currently defined in the data file.
 *
 * Add or remove entries in data/archive_folders.php. The seeder has no fixed
 * catalogue-size limit, so every valid entry in that file becomes visible.
 */
class ArchiveFolderSeeder extends Seeder
{
    /**
     * Seed or refresh all configured archive-folder entries.
     *
     * The operation is idempotent: running it again updates existing rows by
     * their unique code without duplicating or removing custom database rows.
     */
    public function run(): void
    {
        /** @var list<array{code: string, retention_years: int|null, retention_rule: string|null, description: string, remarks: string|null}> $proposal */
        $proposal = require __DIR__.'/data/archive_folders.php';

        DB::transaction(function () use ($proposal): void {
            $now = now();
            $rows = [];

            foreach ($proposal as $position => $folder) {
                $rows[] = [
                    'parent_id' => null,
                    'code' => $folder['code'],
                    'description' => $folder['description'],
                    'retention_years' => $folder['retention_years'],
                    'retention_rule' => $folder['retention_rule'],
                    'remarks' => $folder['remarks'],
                    'is_selectable' => true,
                    'is_active' => true,
                    'sort_order' => $position + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 75) as $chunk) {
                DB::table('archive_folders')->upsert(
                    $chunk,
                    ['code'],
                    [
                        'parent_id',
                        'description',
                        'retention_years',
                        'retention_rule',
                        'remarks',
                        'is_selectable',
                        'is_active',
                        'sort_order',
                        'updated_at',
                    ]
                );
            }

            $idsByCode = DB::table('archive_folders')
                ->whereIn('code', array_column($proposal, 'code'))
                ->pluck('id', 'code');

            foreach ($proposal as $folder) {
                $parentCode = $this->parentCode($folder['code']);

                if (
                    $parentCode === null
                    || ! isset($idsByCode[$parentCode])
                ) {
                    continue;
                }

                DB::table('archive_folders')
                    ->where('id', $idsByCode[$folder['code']])
                    ->update([
                        'parent_id' => $idsByCode[$parentCode],
                        'updated_at' => $now,
                    ]);
            }
        });
    }

    /**
     * Extract the immediate parent from a hierarchical Greek folder code.
     */
    private function parentCode(string $code): ?string
    {
        if (substr_count($code, '.') < 2) {
            return null;
        }

        $lastSeparator = strrpos($code, '.');

        return $lastSeparator === false
            ? null
            : substr($code, 0, $lastSeparator);
    }
}
