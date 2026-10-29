<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained();
            $table->foreignId('recipient_id')->constrained('users');
            $table->string('type', 20);                       // reminder | overdue
            $table->string('status', 30)->default('PENDING'); // PENDING SENT SENT_AFTER_RETRY FAILED_NEEDS_HUMAN HANDLED_MANUALLY
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->timestamp('handled_at')->nullable();
            $table->string('handled_note', 255)->nullable();
            $table->timestamps();

            $table->index(['borrow_request_id', 'type', 'recipient_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_notifications');
    }
};
