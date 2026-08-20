<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the predefined Greek archive-folder proposal from the original app.
 *
 * The source catalogue contains 255 rows. Its codes, descriptions, retention
 * values and legal remarks are preserved as data, while the storage structure
 * uses the normalized English column names introduced in Step 13.8A.
 */
class ArchiveFolderSeeder extends Seeder
{
    /**
     * Seed or refresh the original archive-folder proposal.
     *
     * The operation is idempotent: running it again updates the official rows
     * by their unique code, does not create duplicates, and does not remove any
     * custom folders that an administrator may have added.
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
                    // Parent identifiers are resolved after every code exists.
                    'parent_id' => null,
                    'code' => $folder['code'],
                    'description' => $folder['description'],
                    'retention_years' => $folder['retention_years'],
                    'retention_rule' => $folder['retention_rule'],
                    'remarks' => $folder['remarks'],

                    // The original selector exposed every proposal row. Keep
                    // that behaviour while retaining flags for future editing.
                    'is_selectable' => true,
                    'is_active' => true,
                    'sort_order' => $position + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            /*
             * Small chunks remain compatible with SQLite's parameter limit in
             * the test suite as well as MySQL in the deployed application.
             */
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

            /*
             * Codes contain their hierarchy: Φ.14.1.1 belongs to Φ.14.1,
             * while a top-level code such as Φ.14 has no parent.
             */
            foreach ($proposal as $folder) {
                $parentCode = $this->parentCode($folder['code']);

                if ($parentCode === null) {
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
        // Top-level codes contain only the separator after the Φ prefix.
        if (substr_count($code, '.') < 2) {
            return null;
        }

        $lastSeparator = strrpos($code, '.');

        return $lastSeparator === false
            ? null
            : substr($code, 0, $lastSeparator);
    }
}
