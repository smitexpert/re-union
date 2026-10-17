<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->nullable()
                ->constrained('reunion_registrations')
                ->nullOnDelete();
            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->text('message');
            $table->string('trigger', 32);
            $table->string('status', 16);
            $table->unsignedInteger('http_status')->nullable();
            $table->text('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};