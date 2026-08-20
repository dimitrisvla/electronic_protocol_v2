<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the application-wide settings store.
     *
     * Keys and database values remain in English. Typed conversion and
     * defaults are centralised in ApplicationSettings instead of being
     * repeated throughout controllers and views.
     */
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove the application settings store.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_settings');
    }
};
