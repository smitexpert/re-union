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
        Schema::create('reunion_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reunion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('tshirt_size', 10)->nullable();
            $table->unsignedTinyInteger('guest_count')->default(0);
            $table->decimal('total_fee', 10, 2)->default(0);
            $table->string('registration_status', 20)->default('pending'); // pending | confirmed | cancelled
            $table->string('payment_status', 20)->default('unpaid'); // unpaid | paid | refunded
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['reunion_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reunion_registrations');
    }
};
