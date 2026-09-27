<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores the path of a profile photo uploaded by the student. The file
     * itself lives on the `public` disk under `students/` and is served via
     * the standard `/storage/...` symlink.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('permanent_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
