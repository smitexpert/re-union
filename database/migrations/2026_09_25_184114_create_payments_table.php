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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('reunion_registrations')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30); // bkash | nagad | bank_transfer | cash | card
            $table->string('transaction_id', 100)->nullable()->index();
            $table->string('send_from_mobile_number', 20)->nullable();
            $table->string('status', 20)->default('pending'); // pending | completed | failed | refunded
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
