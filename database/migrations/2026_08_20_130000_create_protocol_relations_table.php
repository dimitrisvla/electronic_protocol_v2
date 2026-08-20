<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store each undirected protocol relationship as one canonical pair.
     *
     * The application always places the smaller protocol id in
     * first_protocol_id. The unique index therefore prevents both duplicate
     * A-B records and an accidental second B-A record created through the
     * Eloquent model.
     */
    public function up(): void
    {
        Schema::create('protocol_relations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('first_protocol_id')
                ->constrained('protocols')
                ->cascadeOnDelete();

            $table->foreignId('second_protocol_id')
                ->constrained('protocols')
                ->cascadeOnDelete();

            // Preserve the relationship if its creator account is removed.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['first_protocol_id', 'second_protocol_id'],
                'protocol_relations_canonical_pair_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_relations');
    }
};
