<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the normalized archive-folder catalogue.
     *
     * The legacy system mixed category rows and selectable folders in one
     * flat table. We preserve its visible structure while adding an explicit
     * parent relationship and flags that make each record's purpose clear.
     */
    public function up(): void
    {
        Schema::create('archive_folders', function (Blueprint $table) {
            $table->id();

            // Self-reference used for structures such as Φ.1 -> Φ.1.1.
            // Removing a category must not automatically remove its children.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('archive_folders')
                ->nullOnDelete();

            // The official visible classification code from the archive plan.
            $table->string('code', 50)->unique();
            $table->text('description');

            // Numeric values can later produce an attachment expiration date.
            // Textual rules cover values such as "Διηνεκές" or "Κατά κρίση".
            $table->unsignedSmallInteger('retention_years')->nullable();
            $table->string('retention_rule', 100)->nullable();
            $table->text('remarks')->nullable();

            // These independent flags allow an entry to remain historically
            // available while being hidden from new folder selections.
            $table->boolean('is_selectable')->default(true);
            $table->boolean('is_active')->default(true);

            // Lexical ordering is unsuitable for codes such as Φ.2 and Φ.10.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['is_active', 'is_selectable', 'sort_order'],
                'archive_folders_catalogue_index'
            );
        });
    }

    /**
     * Remove the archive-folder catalogue.
     */
    public function down(): void
    {
        Schema::dropIfExists('archive_folders');
    }
};
