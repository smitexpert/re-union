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
        Schema::table('students', function (Blueprint $table) {
            $table->text('present_address')->nullable()->after('current_occupation');
            $table->text('permanent_address')->nullable()->after('present_address');
            $table->text('office_address')->nullable()->after('permanent_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['present_address', 'permanent_address', 'office_address']);
        });
    }
};
