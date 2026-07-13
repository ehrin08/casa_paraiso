<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('action', 32);
            $table->decimal('previous_amount', 10, 2);
            $table->decimal('new_amount', 10, 2);
            $table->decimal('previous_amount_paid', 10, 2);
            $table->decimal('new_amount_paid', 10, 2);
            $table->decimal('payment_delta', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transaction_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_adjustments');
    }
};
