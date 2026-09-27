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
        Schema::create('reunion_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('reunion_registrations')->cascadeOnDelete();
            $table->enum('guest_type', ['Child', 'Adult']);
            $table->decimal('fee', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reunion_guests');
    }
};
