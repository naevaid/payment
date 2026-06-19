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
        Schema::create('midtrans_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->index();
            $table->string('order_id')->nullable()->index();
            $table->string('midtrans_transaction_id')->nullable()->index();
            $table->string('transaction_status')->nullable();
            $table->text('signature_key')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->boolean('is_signature_valid')->default(false);
            $table->string('processing_status')->default('received')->index();
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('midtrans_webhook_logs');
    }
};
