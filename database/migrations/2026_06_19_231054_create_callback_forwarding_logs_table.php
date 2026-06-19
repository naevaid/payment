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
        Schema::create('callback_forwarding_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('callback_url');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('event_type')->default('payment.status.updated');
            $table->json('payload');
            $table->json('request_headers')->nullable();
            $table->unsignedSmallInteger('response_status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->boolean('success')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('dispatched_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('callback_forwarding_logs');
    }
};
