<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('department', 'option');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('option', 100)->nullable()->change();
        });

        // The column used to hold a free-text department. It now records the
        // degree option (Honors/Masters), so the legacy values carry no
        // meaning and are cleared instead of being kept as-is.
        DB::table('students')
            ->whereNotIn('option', ['Honors', 'Masters'])
            ->update(['option' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('option', 'department');
        });

        // The legacy department values were cleared in up() and cannot be
        // restored, so the column is emptied rather than left nullable.
        DB::table('students')->whereNull('department')->update(['department' => '']);

        Schema::table('students', function (Blueprint $table) {
            $table->string('department', 100)->nullable(false)->change();
        });
    }
};
