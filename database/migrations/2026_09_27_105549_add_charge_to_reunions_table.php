<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reunions', function (Blueprint $table) {
            $table->string('charge_type', 16)->nullable()->after('registration_fee');
            $table->decimal('charge_value', 10, 2)->nullable()->after('charge_type');
        });
    }

    public function down(): void
    {
        Schema::table('reunions', function (Blueprint $table) {
            $table->dropColumn(['charge_type', 'charge_value']);
        });
    }
};
