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
        Schema::create('protocol_assignments', function (Blueprint $table) {
            $table->id();

            // Removing a protocol permanently also removes its assignments.
            // A normal protocol soft deletion leaves them intact.
            $table->foreignId('protocol_id')
                ->constrained()
                ->cascadeOnDelete();

            // Stored as a string and cast to ProtocolAssignmentPurpose
            // by the ProtocolAssignment model.
            $table->string('purpose', 20);

            // Preserve the assignment if either user is later deleted.
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Support the per-user pending, information, and completed queues.
            $table->index(
                ['assigned_to', 'purpose', 'completed_at'],
                'protocol_assignments_assignee_queue_index'
            );

            // Support protocol assignment history and active-assignee lookup.
            $table->index(
                ['protocol_id', 'purpose', 'completed_at'],
                'protocol_assignments_protocol_queue_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('protocol_assignments');
    }
};
