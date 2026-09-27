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
        Schema::table('reunion_guest_fees', function (Blueprint $table) {
            $table->enum('guest_type', ['Child', 'Adult'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reunion_guest_fees', function (Blueprint $table) {
            $table->string('guest_type', 50)->change();
        });
    }
};
