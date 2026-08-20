<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('protocol_assignments', function (Blueprint $table) {
            // A processing assignment receives this timestamp when a later
            // assignment replaces it. It is distinct from completed_at so
            // reassigned work never appears in the completed-work queue.
            $table->timestamp('superseded_at')
                ->nullable()
                ->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('protocol_assignments', function (Blueprint $table) {
            $table->dropColumn('superseded_at');
        });
    }
};