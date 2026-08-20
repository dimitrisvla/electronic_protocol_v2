<?php

/* We modify the class so we can define the form of the DB.
 * ===> Basically the columns of a table. 
*/


// Imports: 
// Laravel's Migration class.
use Illuminate\Database\Migrations\Migration;
// Laravel provides methods for defining the table's columns and indexes.
use Illuminate\Database\Schema\Blueprint; 
// Laravel's interface for creating, adding or deleting DB tables.
use Illuminate\Support\Facades\Schema;


/*
  The class inherits from Migration class.
  Older versions of Laravel could use a named class.
*/
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a table named protocols.
        Schema::create('protocols', function (Blueprint $table) {
            $table->id(); // primary key (automatically created by Laravel)

            // Start creating columns of different types.
            $table->unsignedInteger('protocol_number');
            $table->unsignedInteger('protocol_year');
            $table->date('protocol_date');
            
            $table->string('direction');
            $table->string('subject');
            $table->string('sender')->nullable();    // can be null
            $table->string('recipient')->nullable();  
            $table->text('notes')->nullable();      

            /**
              * Null ==> a protocol is permitted to have no associated user. 
              * 
              * A protocol remains in the DB even if the user
              * who created it is later deleted.
             */
            $table->foreignId('created_by')  // created_by: will contain the ID of a user.
                ->nullable()
                ->constrained('users')  // Creates a foreign key constraint pointing to the users table.
                ->nullOnDelete();  // Controls what happens if the referenced user is deleted.
                /*  Before deletion:

                    created_by = 12

                    User 12 deleted

                    After:

                    created_by = NULL ==> no associated user
                */
            $table->timestamps();  // Laravel 13 creates 2 columns: created_at,  updated_at

            // Must add the deleted_at column for the recycle bin.
            $table->softDeletes(); 

            /* A Protocol number x can't appear twice in the same year.
            *  But it can appear in different years.
            */
            $table->unique(
                ['protocol_number', 'protocol_year'],  // columns to check
                'protocols_number_year_unique'         // name of the constraint
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("protocols");  // Deletes the protocols table (only if it exists).
    }
};
