<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link each protocol to an optional entry in the archive catalogue.
     *
     * Existing protocols remain valid because the relationship is nullable.
     * If an administrator later removes a catalogue entry, the protocol itself
     * must be preserved and only its folder reference is cleared.
     */
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->foreignId('archive_folder_id')
                ->nullable()
                ->after('notes')
                ->constrained('archive_folders')
                ->nullOnDelete();
        });
    }

    /**
     * Remove the archive-folder relationship without affecting either table.
     */
    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archive_folder_id');
        });
    }
};
